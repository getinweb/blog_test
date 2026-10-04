<?php

declare(strict_types=1);

namespace App\Image;

use finfo;
use InvalidArgumentException;

final readonly class LocalImageStorage implements ImageStorageInterface
{
    /** @var positive-int */
    private int $maxBytes;

    public function __construct(private string $directory, int $maxBytes)
    {
        if ($maxBytes < 1) {
            throw new InvalidArgumentException('Image size limit must be positive.');
        }

        $this->maxBytes = $maxBytes;
    }

    public function store(string $sourcePath): string
    {
        $source = realpath($sourcePath);

        if ($source === false || !is_file($source) || !is_readable($source)) {
            throw new ImageException('Image source must be a readable local file.');
        }

        // Read at most one byte beyond the limit, even if the source changes while being read.
        $contents = @file_get_contents($source, false, null, 0, min($this->maxBytes, PHP_INT_MAX - 1) + 1);

        if ($contents === false || $contents === '') {
            throw new ImageException('Image file is empty or unreadable.');
        }

        if (strlen($contents) > $this->maxBytes) {
            throw new ImageException('Image exceeds the configured size limit of ' . $this->maxBytes . ' bytes.');
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($contents);
        $extension = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw new ImageException('Only JPEG, PNG and WebP images are supported.'),
        };
        $dimensions = @getimagesizefromstring($contents);

        if ($dimensions === false || $dimensions[0] < 1 || $dimensions[1] < 1 || $dimensions['mime'] !== $mime) {
            throw new ImageException('Image header is invalid.');
        }

        if (!is_dir($this->directory) && !@mkdir($this->directory, 0755, true) && !is_dir($this->directory)) {
            throw new ImageException('Cannot create image storage directory.');
        }

        $hash = hash('sha256', $contents);
        $filename = $hash . '.' . $extension;
        $target = $this->directory . '/' . $filename;

        if (file_exists($target)) {
            if (!is_file($target) || hash_file('sha256', $target) !== $hash) {
                throw new ImageException('Stored image content does not match its filename.');
            }

            return 'images/' . $filename;
        }

        $temporary = @tempnam($this->directory, '.image-');

        if ($temporary === false) {
            throw new ImageException('Cannot create temporary image file.');
        }

        try {
            if (@file_put_contents($temporary, $contents) !== strlen($contents)
                || !@chmod($temporary, 0644)
                || !@rename($temporary, $target)) {
                throw new ImageException('Cannot save image file.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }

        return 'images/' . $filename;
    }
}
