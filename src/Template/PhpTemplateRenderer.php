<?php

declare(strict_types=1);

namespace App\Template;

final class PhpTemplateRenderer
{
    public function __construct(
        private readonly string $projectDir,
    ) {
    }

    public function render(string $template, array $context = []): string
    {
        $templatePath = $this->projectDir . '/templates/' . ltrim($template, '/');

        if (!is_file($templatePath)) {
            throw new \RuntimeException(sprintf('Template "%s" was not found.', $template));
        }

        extract($context, EXTR_SKIP);

        ob_start();
        include $templatePath;

        return (string) ob_get_clean();
    }
}
