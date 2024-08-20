<?php

/**
 * Provision enable command
 *
 * Enable a site that has been disabled
 */
function drush_provision_drupal_provision_enable_validate() {
  if (d()->type == 'site') {
    d()->site_enabled = true;
    $drush_lock_ctrl = d()->root . '/local_drush_locked.pid';
    if (!provision_file()->exists($drush_lock_ctrl)->status()) {
      // local drush is unlocked, function might break
      drush_log(dt('Local Drush is unlocked. Lock local Drush before proceeding', 'error'));
    }
    drush_bootstrap(DRUSH_BOOTSTRAP_DRUPAL_SITE);
  }
}

function drush_provision_drupal_post_provision_enable() {
  if (d()->type == 'site') {
    d()->write_alias();
  }
}
