<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\View\TemplateRendererInterface;
use PHPUnit\Framework\TestCase;

final class SmartyRendererTest extends TestCase
{
    public function testTemplateOutputIsEscapedByDefault(): void
    {
        $html = $this->renderer()->render(__DIR__ . '/../Fixtures/templates/variable.tpl', [
            'value' => '<script>alert("x")</script>&',
        ]);

        self::assertSame('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;&amp;', trim($html));
    }

    public function testVariablesDoNotLeakIntoTheNextRender(): void
    {
        $renderer = $this->renderer();
        $template = __DIR__ . '/../Fixtures/templates/variable.tpl';

        self::assertSame('first render', trim($renderer->render($template, ['value' => 'first render'])));
        self::assertSame('', trim($renderer->render($template)));
    }

    private function renderer(): TemplateRendererInterface
    {
        $factory = require dirname(__DIR__, 2) . '/config/container.php';

        return $factory(getenv())->get(TemplateRendererInterface::class);
    }
}
