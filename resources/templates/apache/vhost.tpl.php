<VirtualHost *:<?php print (int) $http_port; ?>>
  ServerName <?php print $server_name; ?>
<?php if (!empty($server_aliases)) : ?>
<?php foreach ($server_aliases as $alias) : ?>
  ServerAlias <?php print $alias; ?>
<?php endforeach; ?>
<?php endif; ?>

  DocumentRoot <?php print $docroot; ?>

  <Directory <?php print $docroot; ?>>
    AllowOverride All
    Require all granted
  </Directory>

<?php if (!empty($canonical_host) || !empty($ssl_redirect)) : ?>
  <IfModule mod_rewrite.c>
    RewriteEngine on
<?php if (!empty($ssl_redirect)) : ?>
    RewriteCond %{HTTPS} off
    RewriteCond %{REQUEST_URI} !^/.well-known/acme-challenge/
    RewriteRule ^/(.*)$ https://%{HTTP_HOST}/$1 [R=301,L]
<?php endif; ?>
<?php if (!empty($canonical_host)) : ?>
    RewriteCond %{HTTP_HOST} !^<?php print $canonical_host; ?>$ [NC]
    RewriteRule ^/(.*)$ <?php print (!empty($ssl_redirect) ? 'https' : 'http'); ?>://<?php print $canonical_host; ?>/$1 [R=301,L]
<?php endif; ?>
  </IfModule>
<?php endif; ?>

<?php if (!empty($extra_config)) : ?>
<?php print $extra_config; ?>
<?php endif; ?>
</VirtualHost>
