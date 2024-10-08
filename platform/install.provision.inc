<?php
/**
 * @file
 *   Provision hooks for the provision install command.
 */

/**
 * Provision install command
 *
 * These are the hooks that will be executed by the drush_invoke function
 * when doing a provision_install.
 */

/**
 * Check that we are trying to install a new site , and a new site only
 */
function drush_provision_drupal_provision_install_validate() {

  if (!d()->uri) {
    return drush_set_error("PROVISION_URL_REQUIRED", dt("You need to specify a valid url to install a site"));
  }
  if (_provision_drupal_site_exists()) {

    // If "force-reinstall" option is set, delete the database and files.
    if (drush_get_option('force-reinstall', FALSE)) {

      drush_log(dt('Forcing reinstall...'), 'ok');

      // Drop the database if we can figure out what it is.
      // If last install failed, the database and the site folder is deleted.
      if (file_exists(d()->site_path . '/drushrc.php')) {

        // Load the current database name from drushrc.php.
        // I cannot find another way to find the current db_name!
        require_once(d()->site_path . '/drushrc.php');
        $old_db_name = $options['db_name'];

        if (d()->service('db')->database_exists($old_db_name)) {
          d()->service('db')->drop_database($old_db_name);
          drush_log(dt('Dropped database @database.', array(
            '@database' => $old_db_name,
          )), 'ok');
        }
      }

      // Destroy site_path.
      if (file_exists(d()->site_path)) {
        provision_file()->chmod(d()->site_path, 0755, TRUE);
        _provision_recursive_delete( d()->site_path );
        drush_log(dt('Deleted @site_path.', array('@site_path' => d()->site_path)), 'ok');
      }
    }

    // Check again if site does not exist after the forced reinstall.
    if (_provision_drupal_site_exists()) {
      return drush_set_error('PROVISION_SITE_INSTALLED',  dt('The file !file still exists. Provision was unable to delete it.', array(
        '!file' => d()->site_path . '/settings.php',
      )));
    }
  }
}

/**
 * Set up the directories and settings.php file that we need.
 */
function drush_provision_drupal_pre_provision_install() {

  $drush_lock_ctrl = d()->root . '/local_drush_locked.pid';
  if (!provision_file()->exists($drush_lock_ctrl)->status()) {
    // local drush is unlocked, install might break
    drush_log(dt('Local Drush is unlocked. Lock local Drush before proceeding', 'error'));
  }

  // This is the actual drupal provisioning requirements.
  _provision_drupal_create_directories();
}

/**
 * If the install went south, and the site is not PROVISION_SITE_INSTALLED, clean up behind ourselves
 */
function drush_provision_drupal_pre_provision_install_rollback() {
  provision_file()->chmod(d()->site_path, 0755);
  _provision_recursive_delete( d()->site_path );
  drush_invoke_process('@none', 'provision-save', array(d()->name), array('delete' => TRUE));
  foreach (d()->drush_aliases as $alias) {
    drush_invoke_process('@none', 'provision-save', array($alias), array('delete' => TRUE));
  }
}


/**
 * Install Drupal with the pre-configured settings, by calling an external script
 *
 * This is an external script so that php is running in it's own namespace, and
 * weird problems such as the multiple database connections don't confuse drupal.
 */
function drush_provision_drupal_provision_install() {
 // Requires at least the database settings to complete.
  provision_prepare_environment();
  _provision_drupal_create_settings_file();
  provision_drupal_push_site($override_slave_authority = TRUE);
  provision_save_site_data();
  drush_bootstrap(DRUSH_BOOTSTRAP_DRUPAL_SITE);

  // Install site according to install_method.
  drush_log(dt('Installing site with the "!method" method.', array(
    '!method' => d()->install_method,
  )), 'ok');

  // Run Drupal installation if option was specified
  if (d()->install_method == 'profile') {

    // call a backend task to do the actual installation.
    $result = provision_backend_invoke(d()->name, "provision-install-backend", array(), array('client_email' => drush_get_option('client_email')));

    // pass the login link back to the front end.
    drush_set_option('login_link', $result['context']['login_link']);

    if (drush_drupal_major_version() < 12) {
      drush_bootstrap(DRUSH_BOOTSTRAP_DRUPAL_FULL);
    }
    else {
      drush_bootstrap(DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION);
      drush_log("Skipping FULL bootstrap for drupal 12+ in drush_provision_drupal_provision_install");
    }

    drush_set_option('profile_installed', TRUE, 'site');
  }
  // If install_method is 'manual', do nothing.
  elseif (d()->install_method == 'manual') {
    drush_set_option('login_link', 'http://' . d()->uri . '/install.php');
  }

  drush_set_option('installed', TRUE, 'site');
  _provision_drupal_maintain_aliases();

}

/**
 * Finish the installation, regenerate the caches on the site so that
 * any changes to things such as available modules/ themes can take affect.
 */
function drush_provision_drupal_post_provision_install() {

  // If Drupal profile was installed, rebuild drupal caches and save enabled packages for aegir.
  if (d()->install_method == 'profile' && drush_get_option('profile_installed', FALSE, 'site')) {
    if (drush_drupal_major_version() < 12) {
      _provision_drupal_rebuild_caches();
    }
    drush_set_option('packages', _scrub_object(provision_drupal_system_map()), 'site');
    _provision_client_create_symlink();
    drush_include_engine('drupal', 'cron_key');
  }
  // For all other install methods, just create the symlink.
  else {
    _provision_client_create_symlink();
  }
}
