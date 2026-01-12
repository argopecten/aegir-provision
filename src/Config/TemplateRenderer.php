<?php

declare(strict_types=1);

namespace Aegir\ProvisionD11\Config;

final class TemplateRenderer {
  private string $templateRoot;

  public function __construct(?string $templateRoot = NULL) {
    $this->templateRoot = $templateRoot ?? dirname(__DIR__, 2) . '/resources/templates';
  }

  public function render(string $template, array $vars = []): string {
    $path = $this->templateRoot . '/' . ltrim($template, '/');
    if (!is_readable($path)) {
      throw new \RuntimeException('Template not found: ' . $path);
    }

    extract($vars, EXTR_SKIP);
    ob_start();
    include $path;
    $content = ob_get_clean();

    return $content === FALSE ? '' : $content;
  }
}
