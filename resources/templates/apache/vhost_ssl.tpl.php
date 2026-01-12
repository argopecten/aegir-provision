<VirtualHost *:<?php print (int) $http_ssl_port; ?>>
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

  <IfModule mod_http2.c>
    Protocols h2 http/1.1
  </IfModule>

  SSLEngine on
  SSLCertificateFile <?php print $ssl_cert; ?>
  SSLCertificateKeyFile <?php print $ssl_key; ?>
<?php if (!empty($ssl_chain)) : ?>
  SSLCertificateChainFile <?php print $ssl_chain; ?>
<?php endif; ?>

<?php if (!empty($canonical_host)) : ?>
  <IfModule mod_rewrite.c>
    RewriteEngine on
    RewriteCond %{HTTP_HOST} !^<?php print $canonical_host; ?>$ [NC]
    RewriteRule ^/(.*)$ https://<?php print $canonical_host; ?>/$1 [R=301,L]
  </IfModule>
<?php endif; ?>

<?php if (!empty($extra_config)) : ?>
<?php print $extra_config; ?>
<?php endif; ?>
</VirtualHost>
