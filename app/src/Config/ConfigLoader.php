<?php

declare(strict_types=1);

namespace App\Config;

use DateTimeZone;

final readonly class ConfigLoader implements ConfigLoaderInterface
{
    public function __construct(private string $directory)
    {
    }

    public function load(array $environment): AppConfig
    {
        $name = $environment['APP_ENV'] ?? 'prod';

        if (!in_array($name, ['dev', 'prod', 'test'], true)) {
            throw new ConfigurationException('APP_ENV must be dev, prod or test.');
        }

        $values = array_replace($this->settings('common'), $this->settings($name), $environment);
        $timezone = $this->required($values, 'APP_TIMEZONE');

        if (!in_array($timezone, DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true)) {
            throw new ConfigurationException('APP_TIMEZONE must be a valid timezone identifier.');
        }

        return new AppConfig(
            environment: $name,
            debug: match ($this->required($values, 'APP_DEBUG')) {
                '0' => false,
                '1' => true,
                default => throw new ConfigurationException('APP_DEBUG must be 0 or 1.'),
            },
            timezone: $timezone,
            database: new DatabaseConfig(
                host: $this->required($values, 'DB_HOST'),
                port: $this->positiveInteger($values, 'DB_PORT', 65535),
                database: $this->required($values, 'DB_DATABASE'),
                username: $this->required($values, 'DB_USERNAME'),
                password: $this->required($values, 'DB_PASSWORD'),
            ),
            articlesPerPage: $this->positiveInteger($values, 'ARTICLES_PER_PAGE'),
            imageMaxBytes: $this->positiveInteger($values, 'IMAGE_MAX_BYTES'),
        );
    }

    /** @return array<string, string> */
    private function settings(string $environment): array
    {
        return require $this->directory . '/' . $environment . '/app.php';
    }

    /** @param array<string, string> $values */
    private function required(array $values, string $name): string
    {
        $value = $values[$name] ?? '';

        if ($value === '') {
            throw new ConfigurationException($name . ' must not be empty.');
        }

        return $value;
    }

    /** @param array<string, string> $values */
    private function positiveInteger(array $values, string $name, int $maximum = PHP_INT_MAX): int
    {
        $value = filter_var($this->required($values, $name), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => $maximum],
        ]);

        if (!is_int($value)) {
            throw new ConfigurationException($name . ' must be an integer between 1 and ' . $maximum . '.');
        }

        return $value;
    }
}
