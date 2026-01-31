<?php
/**
 * @file
 * Example custom Apache vhost template override.
 *
 * This demonstrates how to override core templates. Place custom templates
 * in your extension's templates directory and register it with TemplateRenderer.
 *
 * Variables available:
 * - $server_name: Primary domain name
 * - $server_aliases: Array of alias domains
 * - $http_port: HTTP port (default: 80)
 * - $docroot: Document root path
 * - $site_path: Site directory path
 * - $ssl_redirect: Whether to redirect HTTP to HTTPS
 * - $canonical_host: Canonical hostname for redirects
 * - $extra_config: Additional Apache directives
 *
 * This custom template adds:
 * - Custom logging with separate access/error logs per site
 * - Security headers
 * - Better performance tuning
 * - Custom error documents
 */
?>
<VirtualHost *:<?php print (int) $http_port; ?>>
  ServerName <?php print $server_name; ?>
<?php if (!empty($server_aliases)) : ?>
<?php foreach ($server_aliases as $alias) : ?>
  ServerAlias <?php print $alias; ?>
<?php endforeach; ?>
<?php endif; ?>

  DocumentRoot <?php print $docroot; ?>

  # Custom logging per-site
  ErrorLog ${APACHE_LOG_DIR}/<?php print str_replace('.', '_', $server_name); ?>-error.log
  CustomLog ${APACHE_LOG_DIR}/<?php print str_replace('.', '_', $server_name); ?>-access.log combined

  # Security headers
  <IfModule mod_headers.c>
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
  </IfModule>

  <Directory <?php print $docroot; ?>>
    AllowOverride All
    Require all granted

    # Performance: Enable file caching
    <IfModule mod_expires.c>
      ExpiresActive On
      ExpiresByType image/jpg "access plus 1 year"
      ExpiresByType image/jpeg "access plus 1 year"
      ExpiresByType image/gif "access plus 1 year"
      ExpiresByType image/png "access plus 1 year"
      ExpiresByType image/svg+xml "access plus 1 year"
      ExpiresByType text/css "access plus 1 month"
      ExpiresByType application/javascript "access plus 1 month"
      ExpiresByType application/x-javascript "access plus 1 month"
    </IfModule>

    # Enable compression
    <IfModule mod_deflate.c>
      AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css
      AddOutputFilterByType DEFLATE application/javascript application/x-javascript
    </IfModule>
  </Directory>

<?php if (!empty($canonical_host) || !empty($ssl_redirect)) : ?>
  <IfModule mod_rewrite.c>
    RewriteEngine on
<?php if (!empty($ssl_redirect)) : ?>
    # Redirect HTTP to HTTPS (except Let's Encrypt challenges)
    RewriteCond %{HTTPS} off
    RewriteCond %{REQUEST_URI} !^/.well-known/acme-challenge/
    RewriteRule ^/(.*)$ https://%{HTTP_HOST}/$1 [R=301,L]
<?php endif; ?>
<?php if (!empty($canonical_host)) : ?>
    # Canonical hostname redirect
    RewriteCond %{HTTP_HOST} !^<?php print $canonical_host; ?>$ [NC]
    RewriteRule ^/(.*)$ <?php print (!empty($ssl_redirect) ? 'https' : 'http'); ?>://<?php print $canonical_host; ?>/$1 [R=301,L]
<?php endif; ?>
  </IfModule>
<?php endif; ?>

  # Custom error documents
  ErrorDocument 403 /error/403.html
  ErrorDocument 404 /error/404.html
  ErrorDocument 500 /error/500.html
  ErrorDocument 503 /error/503.html

<?php if (!empty($extra_config)) : ?>
  # Site-specific custom configuration
<?php print $extra_config; ?>
<?php endif; ?>
</VirtualHost>
