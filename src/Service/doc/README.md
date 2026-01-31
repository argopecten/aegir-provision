# Service Package

**Location**: `src/Service/`  
**Purpose**: Modular service implementations for server, platform, and site management

---

## Overview

The Service package implements pluggable services for different hosting concerns:

- **HTTP Services** - Web server configuration (Apache)
- **Database Services** - MySQL database and user management
- **SSL Services** - SSL certificate management
- **Drupal Services** - Drupal-specific configuration

---

## Service Packages

### Http Services

**Location**: `Service/Http/`

#### ApacheService

**File**: `ApacheService.php`

Manages Apache web server configuration for sites and platforms.

**Key Methods**:
- `createSiteVhost(Context $site, Context $platform, Context $server): void`
  - Generates HTTP vhost configuration
  - Writes to `{config_path}/apache/vhost.d/{uri}`
  
- `createSslVhost(Context $site, Context $platform, Context $server): void`
  - Generates HTTPS vhost configuration
  - Writes to `{config_path}/apache/vhost_ssl.d/{uri}`
  
- `createPlatformConfig(Context $platform, Context $server): void`
  - Generates platform-level Apache config
  - Writes to `{config_path}/apache/platform.d/{platform_name}`
  
- `removeSiteVhost(Context $site, Context $server): void`
  - Removes vhost configuration files
  
- `restart(Context $server): void`
  - Restarts Apache service (`systemctl restart apache2`)

**Configuration Locations**:
```
{aegir_root}/.config/apache/
├── vhost.d/          # HTTP vhosts (port 80)
├── vhost_ssl.d/      # HTTPS vhosts (port 443)
└── platform.d/       # Platform-level configs
```

**Templates Used**:
- `resources/templates/apache/vhost.tpl.php`
- `resources/templates/apache/vhost_ssl.tpl.php`

---

### Db Services

**Location**: `Service/Db/`

#### MySqlService

**File**: `MySqlService.php`

Manages MySQL databases, users, and grants.

**Key Methods**:
- `ensureDatabase(string $dbName, string $dbUser, string $dbPass): void`
  - Creates database if not exists
  - Creates user if not exists
  - Sets user password
  
- `grant(string $dbName, string $dbUser, string $host = 'localhost'): void`
  - Grants ALL PRIVILEGES to user on database
  
- `dropDatabase(string $dbName): void`
  - Drops database
  
- `dropUser(string $dbUser): void`
  - Drops user
  
- `dump(string $dbName, string $outputFile): void`
  - Creates mysqldump backup
  
- `restore(string $dbName, string $inputFile): void`
  - Restores database from dump file

**Authentication**:
Uses environment variables or `.my.cnf` for root credentials:
- `MYSQL_PWD` environment variable
- `~/.my.cnf` client configuration

**Commands Used**:
- `mysql` - Interactive SQL execution
- `mysqldump` - Database backup
- `mysqlshow` - Database verification

---

### Ssl Services

**Location**: `Service/Ssl/`

#### SslManager

**File**: `SslManager.php`

Manages SSL certificates for sites.

**Key Methods**:
- `ensureCertificate(Context $site, Context $server): string`
  - Resolves or generates certificate
  - Returns certificate directory path
  
- `getCertificatePath(Context $site, Context $server): string`
  - Returns path to certificate files
  
- `generateSelfSigned(Context $site, Context $server): void`
  - Generates self-signed certificate using OpenSSL

**Supported Certificate Types**:
1. **Self-signed** - Generated via OpenSSL
2. **LetsEncrypt** - Integration planned (future)
3. **Custom** - User-provided certificates

**Certificate Storage**:
```
{aegir_root}/.config/ssl/{uri}/
├── cert.pem          # Certificate
├── key.pem           # Private key
└── chain.pem         # Certificate chain (optional)
```

---

### Drupal Services

**Location**: `Service/Drupal/`

#### SettingsWriter

**File**: `SettingsWriter.php`

Generates Drupal `settings.php` with database credentials and file paths.

**Key Methods**:
- `writeSettings(Context $site, Context $platform): void`
  - Generates `settings.php` from template
  - Writes to `{docroot}/sites/{uri}/settings.php`
  - Sets file permissions (0640)
  
- `ensureDirectories(Context $site, Context $platform): void`
  - Creates `sites/{uri}` directory
  - Creates `files/` directory
  - Sets correct permissions and ownership

**Template Used**:
- `resources/templates/drupal/settings.php.tpl.php`

**Generated Settings Include**:
- Database connection (`$databases['default']`)
- File paths (public, private, temp)
- Salt and hash_salt
- Trusted host patterns

---

## Service Pattern

### Dependency Injection

Services receive their dependencies via constructor:

```php
class ApacheService {
    public function __construct(
        private readonly TemplateRenderer $templates,
        private readonly Filesystem $filesystem,
        private readonly ProcessRunner $runner,
        private readonly LoggerInterface $logger
    ) {}
}
```

### Context-Based Operations

Services operate on Context objects:

```php
public function createSiteVhost(
    Context $site,
    Context $platform,
    Context $server
): void {
    $uri = $site->get('uri');
    $docroot = $platform->get('root') . '/web';
    $configPath = $server->get('config_path');
    // ... generate vhost ...
}
```

### Error Handling

Services throw exceptions for error conditions:
- `RuntimeException` - General errors
- `InvalidArgumentException` - Invalid input

---

## Future: Service Plugin System

Planned enhancement to support alternative implementations:
- `HttpServiceInterface` - Abstract web server operations
- `DbServiceInterface` - Abstract database operations
- `ServiceRegistry` - Plugin discovery and registration

This would enable:
- **Nginx** as alternative to Apache
- **PostgreSQL** as alternative to MySQL
- **Third-party service plugins**

See [Extension System](../../../doc/guides/extension-system.md) for current extensibility via events.

---

## Related Documentation

- [D11 Architecture](../../../doc/provision-d11.md) - Service integration
- [API Reference](../../../doc/guides/api-reference.md) - Complete API documentation
- [Extension System](../../../doc/guides/extension-system.md) - Event-based extensibility
