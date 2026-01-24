# Config Documentation

This directory contains documentation for configuration and template rendering.

## Overview

The `Config/` directory handles template-based generation of:
- Apache/Nginx vhost configurations
- Drupal `settings.php` files
- Drush alias files

Key component:
- `TemplateRenderer` - Renders PHP templates with variable substitution

Templates use simple PHP syntax with variables passed from service implementations.

See: [../doc/provision-d11.md](../doc/provision-d11.md) for configuration generation patterns.
