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
    $drush_lock_ctrl = d()->root . '/local_drush_locked.pid';
    if (!provision_file()->exists($drush_lock_ctrl)->status()) {
      // local drush is unlocked, function might break
      drush_log(dt('Local Drush is unlocked. Lock local Drush before proceeding', 'error'));
    }

    if (drush_drupal_major_version() < 12) {
      drush_bootstrap(DRUSH_BOOTSTRAP_DRUPAL_FULL);
    }
    else {
      drush_bootstrap(DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION);
      drush_log("Skipping FULL bootstrap for drupal 12+ in drush_provision_drupal_provision_import");
    }
    drush_include_engine('drupal', 'import', drush_drupal_major_version());
    drush_set_option('installed', TRUE, 'site');
  }
}

function drush_provision_drupal_post_provision_import() {
  if (d()->type === 'site') {
    if (drush_drupal_major_version() == 6) {
      d()->profile = drush_set_option('profile', drush_get_option('profile', 'default', 'site'));
    }
    elseif (drush_drupal_major_version() == 7) {
      d()->profile = drush_set_option('profile', drush_get_option('profile', 'standard', 'site'));
    }
    elseif (drush_drupal_major_version() >= 8) {
      d()->profile = drush_set_option('profile', \Drupal\Core\Site\Settings::get('install_profile', 'standard'), 'site');
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

    drush_include_engine('drupal', 'cron_key');

    provision_reload_config('site');
  }
}
