<?php

/**
 * Make sure we have a valid site being restored, and that the file being restored from exists
 */
function drush_provision_drupal_provision_restore_validate($restore_file = null) {
  $drush_lock_ctrl = d()->root . '/local_drush_locked.pid';
  if (!provision_file()->exists($drush_lock_ctrl)->status()) {
    // local drush is unlocked, function might break
    drush_log(dt('Local Drush is unlocked. Lock local Drush before proceeding', 'error'));
  }
  drush_bootstrap(DRUSH_BOOTSTRAP_DRUPAL_SITE);

  $exists = provision_file()->exists($restore_file)
    ->succeed('Restoring site from @path')
    ->fail('Could not find backup file @path', 'PROVISION_BACKUP_NOT_FOUND')
    ->status();
  if ($exists) {
    drush_set_option('restore_file', $restore_file);
  }
}

/**
 * Make a backup before making any changes, and add extract the file we are restoring from
 */
function drush_provision_drupal_pre_provision_restore($restore_file) {
  drush_log(dt("Putting site under maintenance"));
  d()->site_enabled = false;
  _provision_drupal_create_settings_file();
  drush_log(dt('Invoking drush provision-backup in drush_provision_drupal_pre_provision_restore'));
  drush_invoke('provision-backup');
}

function drush_provision_drupal_pre_provision_restore_rollback($restore_file) {
  $success =  provision_file()->unlink(drush_get_option('backup_file'))
    ->succeed('Removed unused migration site package')
    ->fail('Could not remove unused migration site package');
}

/**
 * Switch the restore directories around now that we have the new db installed
 */
function drush_provision_drupal_provision_restore($restore_file) {
  // Build an options array to pass to the deploy task. This allows injection
  // of data into the deploy task. See: https://www.drupal.org/node/2471805
  $deploy_options = array(
    'old_uri' => d()->uri,
    'strict' => 0,
  );
  drush_command_invoke_all_ref('provision_deploy_options_alter', $deploy_options, 'restore');

  provision_backend_invoke(d()->name, 'provision-deploy', array(drush_get_option('restore_file')), $deploy_options);
  if (!drush_get_error()) {
    provision_backend_invoke(d()->name, 'provision-verify');
    drush_set_option('installed', false);
  }
}
