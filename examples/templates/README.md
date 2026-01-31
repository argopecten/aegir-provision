# Custom Template Examples

This directory contains example template overrides demonstrating the template override system.

## Directory Structure

```
examples/templates/
├── apache/
│   └── vhost.tpl.php          # Custom Apache vhost with enhanced features
└── drupal/
    └── settings.php.tpl.php   # (Optional) Custom Drupal settings template
```

## Using Custom Templates

### Method 1: Event Subscriber

Register templates automatically using an event subscriber:

```php
use Aegir\Provision\Config\TemplateRenderer;
use Aegir\Provision\Event\ProvisionEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CustomTemplateSubscriber implements EventSubscriberInterface
{
    public function __construct(private TemplateRenderer $templates) {}
    
    public static function getSubscribedEvents(): array {
        return [
            ProvisionEvents::VALIDATE_INSTALL => ['registerTemplates', 1000],
        ];
    }
    
    public function registerTemplates(): void {
        $this->templates->registerTemplatePath(__DIR__ . '/templates', 100);
    }
}
```

### Method 2: Service Provider

Register templates during service container initialization:

```php
public static function register(ContainerInterface $container): void {
    $templates = $container->get(TemplateRenderer::class);
    $templates->registerTemplatePath('/path/to/templates', 50);
}
```

### Method 3: Direct Registration

Register templates programmatically:

```php
$templates->registerTemplatePath('/path/to/custom/templates', 100);
```

## Priority Levels

Template directories are searched in priority order (highest first):

- **200+**: Development/debug templates (temporary overrides)
- **100**: User/site-specific customizations
- **50**: Extension-provided templates
- **10**: Package default templates
- **0**: Core built-in templates (fallback)

## Template Variables

### Apache vhost.tpl.php

Available variables:
- `$server_name` - Primary domain name
- `$server_aliases` - Array of alias domains
- `$http_port` - HTTP port number
- `$docroot` - Document root path
- `$site_path` - Site-specific directory
- `$ssl_redirect` - Boolean: redirect HTTP to HTTPS
- `$canonical_host` - Canonical hostname for redirects
- `$extra_config` - Additional Apache directives

### Apache vhost_ssl.tpl.php

Includes all vhost.tpl.php variables plus:
- `$ssl_cert` - Path to SSL certificate
- `$ssl_key` - Path to SSL private key
- `$ssl_chain` - Path to SSL certificate chain (optional)
- `$http_ssl_port` - HTTPS port number

### Drupal settings.php.tpl.php

Available variables:
- `$db_host` - Database hostname
- `$db_port` - Database port
- `$db_name` - Database name
- `$db_user` - Database username
- `$db_password` - Database password
- `$trusted_host` - Trusted host pattern
- Custom variables passed via options

## Enhanced Features in Example Templates

The example `apache/vhost.tpl.php` includes:

1. **Per-site logging**: Separate access/error logs for each site
2. **Security headers**: X-Content-Type-Options, X-Frame-Options, etc.
3. **Performance optimization**: File caching and compression
4. **Custom error documents**: Branded error pages
5. **Better SSL redirects**: With Let's Encrypt support

## Testing Template Overrides

Debug which template will be used:

```php
$path = $templates->getTemplatePath('apache/vhost.tpl.php');
echo "Template loaded from: {$path}\n";

// List all search paths
foreach ($templates->getTemplatePaths() as $dir) {
    echo "Search path: {$dir}\n";
}
```

## See Also

- [CustomTemplateRegistration.php](../CustomTemplateRegistration.php) - Complete registration examples
- [Extension System Documentation](../../doc/guides/extension-system.md#template-override-system)
