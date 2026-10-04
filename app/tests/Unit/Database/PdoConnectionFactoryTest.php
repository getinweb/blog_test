<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use App\Config\DatabaseConfig;
use App\Database\PdoConnectionFactory;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PdoConnectionFactoryTest extends TestCase
{
    #[DataProvider('invalidDsnParts')]
    public function testDsnSeparatorsAreRejectedBeforeConnecting(string $host, string $database): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new PdoConnectionFactory())->create(new DatabaseConfig($host, 3306, $database, 'test', 'secret'));
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidDsnParts(): iterable
    {
        yield 'host separator' => ['mysql;dbname=other', 'blog'];
        yield 'database separator' => ['mysql', 'blog;charset=latin1'];
        yield 'null byte' => ['mysql', "blog\0other"];
    }
}
