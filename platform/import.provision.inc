<?php

/**
 * Provision import command
 *
 * Import existing drupal sites
 */


/**
 * Import the information about the existing site, and return it in the $data context array
 */
function drush_provision_drupal_provision_import() {
  if (d()->type === 'site') {
    drush_bootstrap(DRUSH_BOOTSTRAP_DRUPAL_FULL);
    $platform = provision_platform();
    if (!$platform) {
      return;
    }
    $platform->import();
    drush_set_option('installed', TRUE, 'site');
  }
}

function drush_provision_drupal_post_provision_import() {
  if (d()->type === 'site') {
    if (drush_drupal_major_version() >= 8) {
      d()->profile = drush_set_option('profile', \Drupal\Core\Site\Settings::get('install_profile', 'standard'), 'site');
    }
    else {
      return drush_set_error('PROVISION_UNSUPPORTED_DRUPAL_VERSION', dt('Drupal 6 and 7 are not supported.'));
    }
    d()->language = drush_set_option('language', drush_get_option('language', 'en', 'site'));
    d()->aliases = drush_set_option('aliases', provision_drupal_find_aliases());
    d()->write_alias();

    // set this in the site options too so it recurrs.
    drush_set_option('aliases', drush_get_option('aliases'),  'site');

    // generate the drushrc
    provision_save_site_data();

    // Do not automatically save the drushrc at the end of the command.
    drush_set_option('provision_save_config', false);

    // We still run this extra backend sub-task to properly register packages etc.
    provision_backend_invoke(d()->name, 'provision-verify');
    sleep(5); // A small trick to avoid high load and race conditions.

    // We run this extra verify via frontend to avoid issue #1004526.
    provision_backend_invoke('@hostmaster', 'hosting-task', array(d()->name, 'verify'), array('force' => TRUE));

    $platform = provision_platform();
    if ($platform) {
      $platform->cronKey();
    }

    provision_reload_config('site');
  }
}
