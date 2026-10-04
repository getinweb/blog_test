<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Category;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CategoryTest extends TestCase
{
    public function testNameLimitCountsUnicodeCharacters(): void
    {
        $name = str_repeat('Я', 255);
        $category = new Category(1, $name, 'Описание');

        self::assertSame($name, $category->name);
    }

    #[DataProvider('invalidCategories')]
    public function testInvalidCategoryIsRejected(int $id, string $name): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Category($id, $name, 'Описание');
    }

    /** @return iterable<string, array{int, string}> */
    public static function invalidCategories(): iterable
    {
        yield 'zero id' => [0, 'PHP'];
        yield 'negative id' => [-1, 'PHP'];
        yield 'empty name' => [1, ''];
        yield 'whitespace name' => [1, '   '];
        yield 'name too long' => [1, str_repeat('Я', 256)];
    }
}
