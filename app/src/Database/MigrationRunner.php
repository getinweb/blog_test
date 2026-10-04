<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use PDOException;
use PDOStatement;

final readonly class MigrationRunner implements MigrationRunnerInterface
{
    public function __construct(private PDO $connection, private string $directory)
    {
    }

    public function status(): array
    {
        return $this->compare($this->files(), $this->history());
    }

    public function migrate(): array
    {
        if ($this->connection->inTransaction()) {
            throw new MigrationException('Migrations cannot run inside an existing transaction.');
        }

        // Named locks are connection-scoped and survive the implicit commits of DDL.
        $locked = $this->query("SELECT GET_LOCK(SHA2(CONCAT('blog:migrations:', DATABASE()), 256), 0)")->fetchColumn();

        if ($locked !== 1) {
            throw new MigrationException('Another migration process is running or the migration lock is unavailable.');
        }

        try {
            $files = $this->files();
            $status = $this->compare($files, $this->history());
            $this->connection->exec(<<<'SQL'
                CREATE TABLE IF NOT EXISTS schema_migrations (
                    version VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
                    checksum CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB
                SQL);
            $applied = [];

            foreach ($status as $migration) {
                if ($migration['applied']) {
                    continue;
                }

                $version = $migration['version'];
                $sql = $files[$version];

                try {
                    // One SQL statement per file. MySQL DDL cannot be rolled back.
                    $this->connection->exec($sql);
                    $this->query('INSERT INTO schema_migrations (version, checksum) VALUES (:version, :checksum)', [
                        'version' => $version,
                        'checksum' => hash('sha256', $sql),
                    ]);
                } catch (PDOException $exception) {
                    throw new MigrationException(
                        'Migration ' . $version . ' failed: ' . $exception->getMessage()
                        . ' MySQL DDL is not rolled back; inspect the schema before retrying.',
                        previous: $exception,
                    );
                }

                $applied[] = $version;
            }

            return $applied;
        } finally {
            $this->query("SELECT RELEASE_LOCK(SHA2(CONCAT('blog:migrations:', DATABASE()), 256))");
        }
    }

    /** @return array<string, string> Filename => SQL. */
    private function files(): array
    {
        if (!is_dir($this->directory)) {
            throw new MigrationException('Migration directory is missing.');
        }

        $paths = glob($this->directory . '/*.sql');

        if ($paths === false) {
            throw new MigrationException('Cannot list migration files.');
        }

        $files = [];

        foreach ($paths as $path) {
            $version = basename($path);

            if (!preg_match('/^[0-9]{3}_[a-z0-9_]+\.sql$/D', $version)) {
                throw new MigrationException('Invalid migration filename: ' . $version);
            }

            $sql = file_get_contents($path);

            if ($sql === false || trim($sql) === '') {
                throw new MigrationException('Migration file is empty or unreadable: ' . $version);
            }

            $files[$version] = $sql;
        }

        ksort($files, SORT_STRING);

        return $files;
    }

    /** @return array<string, string> Filename => SHA-256. */
    private function history(): array
    {
        $exists = $this->query("SELECT COUNT(*) FROM information_schema.tables
            WHERE table_schema = DATABASE() AND table_name = 'schema_migrations'")->fetchColumn();

        if ($exists === 0) {
            return [];
        }

        /** @var array<string, string> $history */
        $history = $this->query('SELECT version, checksum FROM schema_migrations')->fetchAll(PDO::FETCH_KEY_PAIR);

        return $history;
    }

    /**
     * @param array<string, string> $files
     * @param array<string, string> $history
     * @return list<array{version: string, applied: bool}>
     */
    private function compare(array $files, array $history): array
    {
        foreach ($history as $version => $checksum) {
            if (!isset($files[$version])) {
                throw new MigrationException('Applied migration file is missing: ' . $version);
            }

            if (!hash_equals($checksum, hash('sha256', $files[$version]))) {
                throw new MigrationException('Applied migration file has changed: ' . $version);
            }
        }

        $lastApplied = $history === [] ? null : max(array_keys($history));
        $status = [];

        foreach ($files as $version => $sql) {
            $applied = isset($history[$version]);

            if (!$applied && $lastApplied !== null && strcmp($version, $lastApplied) < 0) {
                throw new MigrationException('New migration breaks application order: ' . $version);
            }

            $status[] = ['version' => $version, 'applied' => $applied];
        }

        return $status;
    }

    /** @param array<string, string> $parameters */
    private function query(string $sql, array $parameters = []): PDOStatement
    {
        $statement = $this->connection->prepare($sql);

        if ($statement === false) {
            throw new MigrationException('Cannot prepare migration query.');
        }

        $statement->execute($parameters);

        return $statement;
    }
}
