<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Image\ImageException;
use App\Image\ImageStorageInterface;
use App\Image\LocalImageStorage;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ImageStorageTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/blog-images-' . bin2hex(random_bytes(8));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/stored/*') ?: [] as $file) {
            unlink($file);
        }

        if (is_dir($this->directory . '/stored')) {
            rmdir($this->directory . '/stored');
        }

        foreach (glob($this->directory . '/*') ?: [] as $file) {
            unlink($file);
        }

        rmdir($this->directory);
    }

    #[DataProvider('images')]
    public function testSupportedFilesAreCopiedWithContentBasedNames(string $file, string $extension): void
    {
        $source = $this->fixture($file);
        $storage = new LocalImageStorage($this->directory . '/stored', 5242880);
        $path = $storage->store($source);

        self::assertSame('images/' . hash_file('sha256', $source) . '.' . $extension, $path);
        $target = $this->directory . '/stored/' . basename($path);
        self::assertFileEquals($source, $target);
        self::assertSame(0644, fileperms($target) & 0777);
        self::assertSame($path, $storage->store($source));
        self::assertCount(1, glob($this->directory . '/stored/*') ?: []);
        self::assertFileExists($source);
    }

    public function testExtensionComesFromContentsInsteadOfTheSourceName(): void
    {
        $source = $this->directory . '/untrusted.php';
        copy($this->fixture('php.png'), $source);

        $path = (new LocalImageStorage($this->directory . '/stored', 5242880))->store($source);

        self::assertStringEndsWith('.png', $path);
        self::assertStringNotContainsString('untrusted', $path);
    }

    public function testSizeLimitIncludesTheExactBoundary(): void
    {
        $source = $this->fixture('php.png');
        $size = filesize($source);
        self::assertIsInt($size);
        $path = (new LocalImageStorage($this->directory . '/stored', $size))->store($source);
        self::assertFileExists($this->directory . '/stored/' . basename($path));
        $this->expectException(ImageException::class);
        $this->expectExceptionMessageIsOrContains('size limit');

        (new LocalImageStorage($this->directory . '/stored', $size - 1))->store($source);
    }

    #[DataProvider('invalidImages')]
    public function testInvalidContentsAreRejectedWithoutCreatingOutput(string $contents): void
    {
        $source = $this->directory . '/image.png';
        file_put_contents($source, $contents);

        try {
            (new LocalImageStorage($this->directory . '/stored', 5242880))->store($source);
            self::fail('Invalid image must not be stored.');
        } catch (ImageException) {
            self::assertDirectoryDoesNotExist($this->directory . '/stored');
        }
    }

    public function testMissingSourceIsReported(): void
    {
        $this->expectException(ImageException::class);

        (new LocalImageStorage($this->directory . '/stored', 5242880))->store($this->directory . '/missing');
    }

    public function testStreamSourcesAreRejected(): void
    {
        $this->expectException(ImageException::class);

        (new LocalImageStorage($this->directory . '/stored', 5242880))->store('data://text/plain,test');
    }

    public function testStorageFailureIsReported(): void
    {
        $file = $this->directory . '/occupied';
        file_put_contents($file, 'Not a directory');
        $this->expectException(ImageException::class);

        (new LocalImageStorage($file, 5242880))->store($this->fixture('php.png'));
    }

    public function testExistingDifferentContentIsNotOverwritten(): void
    {
        $storage = new LocalImageStorage($this->directory . '/stored', 5242880);
        $path = $storage->store($this->fixture('php.png'));
        $target = $this->directory . '/stored/' . basename($path);
        file_put_contents($target, 'Changed content');

        try {
            $storage->store($this->fixture('php.png'));
            self::fail('An existing different file must not be overwritten.');
        } catch (ImageException) {
            self::assertSame('Changed content', file_get_contents($target));
        }
    }

    public function testImageLimitIsTakenFromApplicationConfiguration(): void
    {
        $factory = require dirname(__DIR__, 2) . '/config/container.php';
        $container = $factory(array_replace(getenv(), ['IMAGE_MAX_BYTES' => '1']));
        $this->expectException(ImageException::class);
        $this->expectExceptionMessageIsOrContains('size limit');

        $container->get(ImageStorageInterface::class)->store($this->fixture('php.png'));
    }

    public function testNonPositiveLimitIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new LocalImageStorage($this->directory, 0);
    }

    /** @return iterable<string, array{string, string}> */
    public static function images(): iterable
    {
        yield 'PNG' => ['php.png', 'png'];
        yield 'JPEG' => ['mysql.jpg', 'jpg'];
        yield 'WebP' => ['scss.webp', 'webp'];
    }

    /** @return iterable<string, array{string}> */
    public static function invalidImages(): iterable
    {
        yield 'empty' => [''];
        yield 'PHP code' => ['<?php echo "not an image";'];
        yield 'SVG' => ['<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"></svg>'];
        yield 'broken PNG header' => ["\x89PNG\r\n\x1a\n"];
        yield 'broken JPEG header' => ["\xff\xd8\xff\xe0\x00\x10JFIF\x00"];
    }

    private function fixture(string $name): string
    {
        return dirname(__DIR__, 2) . '/db/seeds/images/' . $name;
    }
}
