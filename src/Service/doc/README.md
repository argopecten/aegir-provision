# Service Documentation

This directory contains documentation for service implementations.

## Overview

The `Service/` directory implements modular services:

### HTTP Services (Service/Http/)
- **ApacheService** - Apache vhost generation, SSL certificate management
- Generates configs in `{config_path}/apache/{vhost.d, vhost_ssl.d, platform.d}`

### Database Services (Service/Db/)
- **MySqlService** - MySQL database/user creation, grants, dump/restore
- Uses mysql CLI with environment variable credentials

### SSL Services (Service/Ssl/)
- **SslManager** - SSL certificate resolution and deployment
- Supports self-signed, LetsEncrypt, and Cloudflare certificates

### Drupal Services (Service/Drupal/)
- **SettingsWriter** - Generates Drupal `settings.php` with DB credentials
- Manages file directory permissions (public/private files)

## Service Pattern

Services implement context-specific operations and config generation. They are instantiated by ProvisionManager and receive context data.

See: [../doc/provision-d11.md](../doc/provision-d11.md) for service architecture details.
