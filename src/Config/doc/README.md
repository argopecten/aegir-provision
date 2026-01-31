# Config Package

**Location**: `src/Config/`  
**Purpose**: Template-based configuration file generation

---

## Overview

The Config package provides template rendering for generating configuration files:

- Apache/Nginx vhost configurations
- Drupal `settings.php` files
- Drush alias files
- SSL certificate configurations

---

## Classes

### TemplateRenderer

**File**: `TemplateRenderer.php`

Renders PHP templates with variable substitution using native PHP `include()`.

**Constructor**:
```php
public function __construct(?string $templateRoot = null)
```
- `$templateRoot` - Base directory for templates (default: `resources/templates/`)

**Methods**:
```php
public function render(string $template, array $vars = []): string
```
- `$template` - Template path relative to template root (e.g., `apache/vhost.tpl.php`)
- `$vars` - Associative array of variables to extract into template scope
- Returns rendered template as string
- Throws `RuntimeException` if template not found

**Usage Example**:
```php
$renderer = new TemplateRenderer();
$content = $renderer->render('apache/vhost.tpl.php', [
    'uri' => 'example.com',
    'docroot' => '/var/aegir/platforms/drupal-11/web',
    'server_name' => 'example.com',
]);
```

---

## Templates

Templates are stored in `resources/templates/`:

```
resources/templates/
├── apache/
│   ├── vhost.tpl.php          # HTTP vhost
│   └── vhost_ssl.tpl.php      # HTTPS vhost
└── drupal/
    └── settings.php.tpl.php   # Drupal settings.php
```

Templates use simple PHP syntax with extracted variables:

```php
<?php // vhost.tpl.php ?>
<VirtualHost *:80>
  ServerName <?php echo $server_name; ?>
  DocumentRoot <?php echo $docroot; ?>
</VirtualHost>
```

---

## Related Documentation

- [D11 Architecture](../../../doc/provision-d11.md) - Template usage in service implementations
- [API Reference](../../../doc/guides/api-reference.md) - Complete API documentation
- Service implementations use TemplateRenderer:
  - [Http/ApacheService](../Service/Http/ApacheService.php) - Vhost templates
  - [Drupal/SettingsWriter](../Service/Drupal/SettingsWriter.php) - Settings templates
