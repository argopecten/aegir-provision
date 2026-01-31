<?php
/**
 * @file
 * Example registering custom template directories.
 *
 * This demonstrates how to register custom template overrides in your
 * Aegir Provision extension.
 */

declare(strict_types=1);

namespace Aegir\Provision\Examples;

use Aegir\Provision\Config\TemplateRenderer;
use Aegir\Provision\Event\ProvisionEvents;
use Aegir\Provision\Event\ProvisionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber that registers custom template directories.
 *
 * This subscriber runs early in the workflow to ensure custom templates
 * are available for all operations.
 */
class CustomTemplateSubscriber implements EventSubscriberInterface
{
    private TemplateRenderer $templates;
    private string $customTemplatePath;

    public function __construct(TemplateRenderer $templates, string $customTemplatePath)
    {
        $this->templates = $templates;
        $this->customTemplatePath = $customTemplatePath;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        // Register templates early, before any operations
        return [
            ProvisionEvents::VALIDATE_INSTALL => ['registerTemplates', 1000],
            ProvisionEvents::VALIDATE_VERIFY => ['registerTemplates', 1000],
            ProvisionEvents::VALIDATE_ENABLE => ['registerTemplates', 1000],
        ];
    }

    /**
     * Register custom template directory with high priority.
     */
    public function registerTemplates(ProvisionEvent $event): void
    {
        // Register custom templates with priority 100 (higher than default 10)
        if (!$this->templates->templateExists('apache/vhost.tpl.php')) {
            return; // Already registered or core templates not available
        }

        $this->templates->registerTemplatePath($this->customTemplatePath, 100);
    }
}

/**
 * Example: Register custom templates in a Drush service provider
 */
class ExampleTemplateServiceProvider
{
    public static function register(\Psr\Container\ContainerInterface $container): void
    {
        // Get the template renderer from the container
        $templates = $container->get(TemplateRenderer::class);
        
        // Register your extension's template directory
        $extensionPath = dirname(__DIR__);
        $templatePath = $extensionPath . '/templates';
        
        if (is_dir($templatePath)) {
            // Priority 50 = higher than core (0) but lower than user overrides (100)
            $templates->registerTemplatePath($templatePath, 50);
        }
    }
}

/**
 * Example: Register templates programmatically
 */
function registerCustomTemplates(TemplateRenderer $templates): void
{
    // Register multiple template directories with different priorities
    
    // Extension templates (priority 50)
    $templates->registerTemplatePath('/var/aegir/custom-extension/templates', 50);
    
    // Site-specific templates (priority 100 - highest)
    $templates->registerTemplatePath('/var/aegir/site-templates', 100);
    
    // Development/testing templates (priority 200 - overrides everything)
    if (getenv('AEGIR_DEV_MODE') === 'true') {
        $templates->registerTemplatePath('/tmp/aegir-dev-templates', 200);
    }
}

/**
 * Example: Check which template will be used
 */
function debugTemplateResolution(TemplateRenderer $templates, string $template): void
{
    $path = $templates->getTemplatePath($template);
    
    if ($path === null) {
        echo "Template not found: {$template}\n";
        echo "Searched paths:\n";
        foreach ($templates->getTemplatePaths() as $dir) {
            echo "  - {$dir}\n";
        }
    } else {
        echo "Template '{$template}' will be loaded from:\n{$path}\n";
    }
}

/**
 * Example: Temporarily override templates for testing
 */
function withCustomTemplates(TemplateRenderer $templates, string $tempPath, callable $callback): mixed
{
    // Register temporary template path
    $templates->registerTemplatePath($tempPath, 999);
    
    try {
        // Run code with custom templates
        return $callback();
    } finally {
        // Clean up
        $templates->unregisterTemplatePath($tempPath);
    }
}
