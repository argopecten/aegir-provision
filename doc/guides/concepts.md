# Core Concepts

Understanding Aegir Provision's architecture and fundamental concepts.

---

## The Context System

Aegir Provision operates on a **context-based** architecture. Contexts are immutable data structures that represent different aspects of your hosting environment.

### Three Context Types

#### 1. Server Context

Represents an infrastructure node that provides services.

**Properties**:
- `aegir_root` - Base directory for Aegir data (`/var/aegir`)
- `web_group` - Web server user group (`www-data`)
- `http_port` - HTTP port (default: 80)
- `http_ssl_port` - HTTPS port (default: 443)
- `http_restart_cmd` - Command to restart/reload web server
- `ip_address` - Server IP address (optional, for DNS)

**Example**:
```yaml
# ~/.drush/sites/server_master.server.yml
type: server
aegir_root: /var/aegir
web_group: www-data
http_port: 80
http_ssl_port: 443
http_restart_cmd: sudo systemctl reload apache2
```

#### 2. Platform Context

Represents a Drupal codebase (directory with Drupal code).

**Properties**:
- `root` - Path to Drupal codebase
- `server` - Reference to server context
- `site_suffix` - Subdirectory for multisite (optional, default: `sites`)

**Example**:
```yaml
# ~/.drush/sites/platform_d11.platform.yml
type: platform
root: /var/aegir/platforms/drupal-11
server: server_master
```

Aegir automatically detects:
- Drupal version
- Docroot location (`/web`, `/docroot`, or root)
- Composer structure

#### 3. Site Context

Represents an individual Drupal site installation.

**Properties**:
- `uri` - Site domain name
- `platform` - Reference to platform context
- `db_server` - Reference to server context (for database)
- `db_name` - Database name
- `db_user` - Database username
- `db_passwd` - Database password
- `db_host` - Database host (default: localhost)
- `root` - Docroot path (auto-set during install)
- `ssl_enabled` - Enable SSL (default: false)

**Example**:
```yaml
# ~/.drush/sites/example.com.site.yml
type: site
uri: example.com
platform: platform_d11
db_server: server_master
db_name: example_com
db_user: example_com_user
db_passwd: generated_secure_password
root: /var/aegir/platforms/drupal-11/web
ssl_enabled: false
```

---

## Context Storage

Contexts are stored as **Drush site alias files** in `~/.drush/sites/`:

```
~/.drush/sites/
├── server_master.server.yml
├── platform_d11.platform.yml
├── example.com.site.yml
└── staging.example.com.site.yml
```

### File Naming Convention

- Format: `{context_name}.{type}.yml`
- Types: `server`, `platform`, `site`
- Context names use underscores or dots (become part of alias)

### Alias References

Reference contexts using Drush alias syntax:

```bash
drush provision-verify @server_master
drush provision-verify @platform_d11
drush provision-install @example.com
```

---

## Immutability

Contexts are **immutable** - changes create new versions rather than modifying existing data.

**Always use** `provision-save` to update contexts:

```bash
# ✓ CORRECT
drush provision-save @example.com --data='{"ssl_enabled":true}'

# ✗ WRONG - Don't edit YAML files directly
nano ~/.drush/sites/example.com.site.yml
```

This ensures:
- Proper validation of context data
- Automatic updates to dependent configurations
- Audit trail of changes

---

## Context Relationships

Contexts form a **hierarchy**:

```
Server (@server_master)
  ├── Platform (@platform_d11)
  │     ├── Site (@example.com)
  │     └── Site (@staging.example.com)
  └── Platform (@platform_d10)
        └── Site (@legacy.example.com)
```

### Dependency Resolution

Operations automatically resolve dependencies:

```bash
# Installing a site automatically:
# 1. Loads site context
# 2. Resolves platform context
# 3. Resolves server context
# 4. Uses all three for configuration
drush provision-install @example.com
```

---

## Service Architecture

Aegir Provision delegates infrastructure operations to **service classes**:

### HTTP Service (Apache)

- Generates vhost configurations using `ApacheVhostConfig` value objects
- Manages site enable/disable
- Handles SSL certificates
- Reloads web server

**Configuration Files**:
- Platform vhost: `/etc/apache2/sites-available/{server}-platform-{platform}.conf`
- Site vhost: `/etc/apache2/sites-available/{site}.conf`

### Database Service (MySQL)

- Creates databases and users using PDO with prepared statements
- Returns `DatabaseCredentials` value objects (no plain arrays)
- Grants privileges securely
- Drops databases/users on deletion
- Manages backups (mysqldump)

### Settings Service (Drupal)

- Generates `settings.php` from templates
- Accepts `DatabaseCredentials` value object for type-safe configuration
- Sets trusted host patterns
- Configures file paths

### Path Resolution Service

- Returns `ServerPaths` value objects with validated directory paths
- Ensures directory structure exists with correct permissions
- Provides immutable path configuration

### SSL Service

- Manages Let's Encrypt integration (planned)
- Certificate deployment
- HTTPS redirect configuration

---

## Template System

Configuration files are generated from **PHP templates**:

```
resources/templates/
├── apache/
│   ├── vhost.tpl.php        # HTTP vhost template
│   └── vhost_ssl.tpl.php    # HTTPS vhost template
└── drupal/
    └── settings.php.tpl.php # Drupal settings template
```

Templates receive context data and generate output:

```php
// Example: vhost.tpl.php
<VirtualHost *:<?php print $http_port; ?>>
  ServerName <?php print $uri; ?>
  DocumentRoot <?php print $docroot; ?>
  
  <Directory "<?php print $docroot; ?>">
    Options FollowSymLinks
    AllowOverride All
    Require all granted
  </Directory>
</VirtualHost>
```

---

## Operation Lifecycle

All provision operations follow a standard lifecycle:

### 1. Validation Phase

Check preconditions before making changes:
- Context exists and is valid
- Required properties are present
- Dependencies can be resolved
- Filesystem permissions are correct

### 2. Before Phase

Prepare for the operation:
- Create backup (if needed)
- Allocate resources
- Set up temporary files

### 3. Execution Phase

Perform the main operation:
- Database operations
- File generation
- Service configuration
- External commands

### 4. After Phase

Finalize and cleanup:
- Reload services
- Update context data
- Remove temporary files
- Log success

### 5. Rollback Phase (on failure)

Restore previous state if execution fails:
- Restore from backup
- Remove partial changes
- Clean up resources
- Log error

---

## Event System

Operations emit **events** at each lifecycle phase:

```php
// Example: Install lifecycle
ProvisionEvents::VALIDATE_INSTALL  // Check if install is possible
ProvisionEvents::BEFORE_INSTALL    // Prepare for installation
// ... main install logic ...
ProvisionEvents::AFTER_INSTALL     // Post-install tasks
ProvisionEvents::ROLLBACK_INSTALL  // Cleanup on failure
```

Extensions can subscribe to these events to:
- Add custom validation
- Inject additional logic
- Send notifications
- Integrate with external systems

See [Extension System](extension-system.md) for details.

---

## Workflow Patterns

### Standard Site Deployment

1. Create server context (one-time setup)
2. Create platform context (per Drupal codebase)
3. Verify platform
4. Create site context
5. Install site
6. Verify site works

### Drupal Core Update

1. Deploy new codebase as new platform
2. Verify new platform
3. Migrate site to new platform
4. Test site on new platform
5. Remove old platform (when safe)

### Multi-Site Platform

1. Create one platform context
2. Create multiple site contexts (same platform)
3. Each site gets its own database and vhost
4. All sites share the same codebase

### Backup/Restore Workflow

1. `provision-backup @site` - Creates timestamped tarball
2. Store backup securely
3. `provision-restore @site /path/to/backup.tar.gz` - Restores on failure
4. Or `provision-deploy` to deploy backup to different site

---

## Best Practices

### Context Naming

- Use descriptive names: `@production.example.com`, not `@site1`
- Use consistent patterns: `@{env}.{domain}` for sites
- Server contexts: `@server_{name}` (e.g., `@server_master`, `@server_web1`)
- Platform contexts: `@platform_{version}` (e.g., `@platform_d11`, `@platform_d11_dev`)

### Directory Structure

```
/var/aegir/
├── backups/              # Site backups
│   └── example.com/
├── platforms/            # Drupal codebases
│   ├── drupal-11/
│   └── drupal-11-dev/
└── config/               # Generated configs (Apache, etc.)
```

### Security

- Use strong, unique database passwords
- Set restrictive file permissions (0600 for context YAML files)
- Never commit context files to version control
- Run commands as non-root `aegir` user
- Use sudo only for Apache reload

### Testing

- Always verify after creating/updating contexts
- Test migrations on staging before production
- Keep backups before major changes
- Monitor Apache/MySQL logs for errors

---

## Next Steps

- Try the [Quick Start Guide](quickstart.md) to set up your first site
- Read about the [Extension System](extension-system.md) for customization
- See [provision-d11.md](../provision-d11.md) for architecture deep-dive

---

## Further Reading

- **Drush Site Aliases**: https://www.drush.org/13.x/site-aliases/
- **Symfony Process Component**: https://symfony.com/doc/current/components/process.html
- **Symfony EventDispatcher**: https://symfony.com/doc/current/components/event_dispatcher.html
