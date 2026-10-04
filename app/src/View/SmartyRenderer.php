<?php

declare(strict_types=1);

namespace App\View;

use Smarty\Exception;
use Smarty\Smarty;

final readonly class SmartyRenderer implements TemplateRendererInterface
{
    public function __construct(private Smarty $smarty)
    {
    }

    /** @throws Exception */
    public function render(string $template, array $data = []): string
    {
        $view = $this->smarty->createTemplate($template);
        $view->assign($data);

        return $view->fetch();
    }
}
