<?php

declare(strict_types=1);

namespace Aegir\Provision\Config;

/**
 * Template renderer with support for custom template directories.
 *
 * Supports priority-based template resolution:
 * 1. Custom directories (highest priority, registered at runtime)
 * 2. Package templates (default location)
 * 3. Core templates (lowest priority, built-in fallback)
 */
final class TemplateRenderer {
  /**
   * Default template root (core templates).
   */
  private string $coreTemplateRoot;

  /**
   * Registered custom template directories, ordered by priority (highest first).
   *
   * @var array<int,string>
   */
  private array $customTemplatePaths = [];

  /**
   * Template cache: rendered templates keyed by cache key.
   *
   * @var array<string,string>
   */
  private array $cache = [];

  /**
   * Cache statistics.
   *
   * @var array{hits: int, misses: int}
   */
  private array $cacheStats = ['hits' => 0, 'misses' => 0];

  /**
   * Whether caching is enabled.
   */
  private bool $cachingEnabled = true;

  /**
   * @param string|null $coreTemplateRoot Core template directory (default: resources/templates)
   * @param bool $cachingEnabled Whether to enable template caching (default: true)
   */
  public function __construct(?string $coreTemplateRoot = null, bool $cachingEnabled = true) {
    $this->coreTemplateRoot = $coreTemplateRoot ?? dirname(__DIR__, 2) . '/resources/templates';
    $this->cachingEnabled = $cachingEnabled;
  }

  /**
   * Register a custom template directory with optional priority.
   *
   * Higher priority directories are searched first. Default priority is 10.
   * Core templates have priority 0.
   *
   * @param string $path Absolute path to template directory
   * @param int $priority Priority level (higher = searched first)
   */
  public function registerTemplatePath(string $path, int $priority = 10): void {
    if (!is_dir($path)) {
      throw new \InvalidArgumentException("Template directory does not exist: {$path}");
    }

    $this->customTemplatePaths[$priority] = $path;
    krsort($this->customTemplatePaths); // Sort by priority descending
  }

  /**
   * Unregister a custom template directory.
   *
   * @param string $path Absolute path to template directory
   */
  public function unregisterTemplatePath(string $path): void {
    $this->customTemplatePaths = array_filter(
      $this->customTemplatePaths,
      fn($p) => $p !== $path
    );
  }

  /**
   * Get all registered template paths in priority order.
   *
   * @return array<string> Array of template directory paths
   */
  public function getTemplatePaths(): array {
    return array_merge(array_values($this->customTemplatePaths), [$this->coreTemplateRoot]);
  }

  /**
   * Clear all custom template paths.
   */
  public function clearCustomPaths(): void {
    $this->customTemplatePaths = [];
    $this->clearCache(); // Clear cache when paths change
  }

  /**
   * Enable or disable template caching.
   *
   * @param bool $enabled Whether to enable caching
   */
  public function setCachingEnabled(bool $enabled): void {
    $this->cachingEnabled = $enabled;
    if (!$enabled) {
      $this->clearCache();
    }
  }

  /**
   * Check if caching is enabled.
   *
   * @return bool True if caching is enabled
   */
  public function isCachingEnabled(): bool {
    return $this->cachingEnabled;
  }

  /**
   * Clear the template cache.
   */
  public function clearCache(): void {
    $this->cache = [];
  }

  /**
   * Get cache statistics.
   *
   * @return array{hits: int, misses: int, size: int, hit_rate: float} Cache statistics
   */
  public function getCacheStats(): array {
    $total = $this->cacheStats['hits'] + $this->cacheStats['misses'];
    $hitRate = $total > 0 ? ($this->cacheStats['hits'] / $total) * 100 : 0.0;
    
    return [
      'hits' => $this->cacheStats['hits'],
      'misses' => $this->cacheStats['misses'],
      'size' => count($this->cache),
      'hit_rate' => round($hitRate, 2),
    ];
  }

  /**
   * Generate a cache key for a template and variables.
   *
   * @param string $template Template path
   * @param array<string,mixed> $vars Template variables
   * @return string Cache key
   */
  private function generateCacheKey(string $template, array $vars): string {
    // Include template path and serialized vars in cache key
    // Also include template file modification time to auto-invalidate on changes
    $templatePath = $this->findTemplate($template);
    $mtime = $templatePath ? filemtime($templatePath) : 0;
    
    return md5($template . '|' . serialize($vars) . '|' . $mtime);
  }

  /**
   * Render a template with variables.
   *
   * Searches for the template in priority order:
   * 1. Custom directories (by priority, highest first)
   * 2. Core template directory
   *
   * @param string $template Relative template path (e.g., 'apache/vhost.tpl.php')
   * @param array<string,mixed> $vars Variables to extract into template scope
   * @return string Rendered template content
   * @throws \RuntimeException If template not found in any location
   */
  public function render(string $template, array $vars = []): string {
    // Check cache first if enabled
    if ($this->cachingEnabled) {
      $cacheKey = $this->generateCacheKey($template, $vars);
      
      if (isset($this->cache[$cacheKey])) {
        $this->cacheStats['hits']++;
        return $this->cache[$cacheKey];
      }
      
      $this->cacheStats['misses']++;
    }

    $templatePath = $this->findTemplate($template);

    if ($templatePath === null) {
      $searchedPaths = implode(', ', $this->getTemplatePaths());
      throw new \RuntimeException(
        "Template not found: {$template} (searched in: {$searchedPaths})"
      );
    }

    extract($vars, EXTR_SKIP);
    ob_start();
    include $templatePath;
    $content = ob_get_clean();

    $renderedContent = $content === false ? '' : $content;

    // Store in cache if enabled
    if ($this->cachingEnabled) {
      $this->cache[$cacheKey] = $renderedContent;
    }

    return $renderedContent;
  }

  /**
   * Find a template file by searching all registered paths.
   *
   * @param string $template Relative template path
   * @return string|null Absolute path to template file, or null if not found
   */
  public function findTemplate(string $template): ?string {
    $template = ltrim($template, '/');

    // Search custom paths first (by priority)
    foreach ($this->customTemplatePaths as $customPath) {
      $path = $customPath . '/' . $template;
      if (is_readable($path)) {
        return $path;
      }
    }

    // Fall back to core templates
    $corePath = $this->coreTemplateRoot . '/' . $template;
    if (is_readable($corePath)) {
      return $corePath;
    }

    return null;
  }

  /**
   * Check if a template exists in any registered path.
   *
   * @param string $template Relative template path
   * @return bool True if template exists
   */
  public function templateExists(string $template): bool {
    return $this->findTemplate($template) !== null;
  }

  /**
   * Get the source path of a template (where it would be loaded from).
   *
   * Useful for debugging which template will be used.
   *
   * @param string $template Relative template path
   * @return string|null Absolute path if found, null otherwise
   */
  public function getTemplatePath(string $template): ?string {
    return $this->findTemplate($template);
  }
}
