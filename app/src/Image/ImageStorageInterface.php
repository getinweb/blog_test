<?php

declare(strict_types=1);

namespace App\Image;

interface ImageStorageInterface
{
    /** Imports a local JPEG, PNG or WebP file and returns its path relative to the public URL root. */
    public function store(string $sourcePath): string;
}
