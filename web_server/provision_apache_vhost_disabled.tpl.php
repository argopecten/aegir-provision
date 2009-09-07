<VirtualHost *:80>
    <?php if ($site_mail) : ?>
      ServerAdmin <?php  print $site_mail; ?> 
    <?php endif;?>
    DocumentRoot <?php print $publish_path; ?> 
    
    ServerName <?php print $site_url; ?>

    RewriteEngine on
    # the ? at the end is to remove any query string in the original url
    RewriteRule ^(.*)$ <?php print $redirect_url . '/' . $site_url ?>?

    <?php if (is_array($aliases)) :
     foreach ($aliases as $alias) : ?>
       ServerAlias <?php print $alias; ?>
     <?php
       endforeach;
     endif; ?>

</VirtualHost>