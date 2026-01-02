<?php

require_once dirname(__DIR__, 3) . '/provision.inc';

use Drush\Commands\DrushCommands;

/**
 * Drush commands for Provision.
 */
class Provision_Drush_Commands_ProvisionCommands extends DrushCommands {
  /**
   * Track whether Provision initialization has run.
   *
   * @var bool
   */
  protected $provision_initialized = FALSE;

  /**
   * Ensure Provision commandfiles and context are initialized.
   *
   * @param string|null $alias_override
   *   Optional alias to set as the active context.
   */
  protected function initializeProvision($alias_override = NULL) {
    if ($this->provision_initialized) {
      return;
    }

    provision_load_commandfiles();

    $alias = $alias_override;
    if ($alias === NULL) {
      $hash_name = drush_get_option('#name') ? '#name' : 'name';
      $alias = drush_get_option($hash_name, '@self', 'alias');
    }
    d($alias, TRUE);

    _provision_drush_check_user();
    _provision_drush_check_load();

    $this->provision_initialized = TRUE;
  }

  /**
   * Invoke a Drush hook for the current command.
   *
   * @param string $hook
   *   Hook name to invoke.
   * @param array $args
   *   Arguments passed to the hook.
   */
  protected function invokeHook($hook, array $args = array()) {
    if (!function_exists('provision_command_invoke_all')) {
      return;
    }
    $invoke_args = array_merge(array($hook), $args);
    call_user_func_array('provision_command_invoke_all', $invoke_args);
  }

  /**
   * Run the Provision hook pipeline for a command.
   *
   * @param string $command
   *   Command name, e.g. "provision-install".
   * @param array $args
   *   Arguments to pass to hooks.
   */
  protected function runProvisionCommand($command, array $args = array()) {
    $this->initializeProvision();

    $hook_base = str_replace('-', '_', $command);
    try {
      $this->invokeHook($hook_base . '_validate', $args);
      if (function_exists('drush_get_error') && drush_get_error()) {
        return;
      }

      $this->invokeHook('pre_' . $hook_base, $args);
      if (function_exists('drush_get_error') && drush_get_error()) {
        $this->invokeHook('pre_' . $hook_base . '_rollback', $args);
        return;
      }

      $this->invokeHook($hook_base, $args);
      if (function_exists('drush_get_error') && drush_get_error()) {
        $this->invokeHook($hook_base . '_rollback', $args);
        return;
      }

      $this->invokeHook('post_' . $hook_base, $args);
    }
    finally {
      $this->finalizeProvision();
    }
  }

  /**
   * Run post-command Provision cleanup.
   */
  protected function finalizeProvision() {
    if (function_exists('provision_drupal_drush_exit')) {
      provision_drupal_drush_exit();
    }
    if (function_exists('provision_command_invoke_all')) {
      provision_command_invoke_all('drush_exit');
    }
  }

  /**
   * Save Drush alias.
   *
   * @command provision-save
   * @argument context_name Context to save.
   * @option context_type Context type: server, platform, or site; default server.
   * @option delete Remove the alias.
   * @allow-additional-options
   * @bootstrap DRUSH_BOOTSTRAP_DRUSH
   */
  public function provisionSave($context_name = NULL) {
    $this->initializeProvision($context_name);
    $alias = $context_name ?: d()->name;

    try {
      if (drush_get_option('delete', FALSE)) {
        $config = new Provision_Config_Drushrc_Alias($alias);
        $config->unlink();
        return;
      }

      d($alias)->type_invoke('save');
      d($alias)->write_alias();
    }
    finally {
      $this->finalizeProvision();
    }
  }

  /**
   * Verify that the provisioning framework is correctly installed.
   *
   * @command provision-verify
   * @aliases v,pv,verify
   * @argument context_name Optional context to verify.
   * @option override_slave_authority Push the site specific files directory to a slave.
   * @allow-additional-options
   * @bootstrap DRUSH_BOOTSTRAP_DRUSH
   */
  public function provisionVerify($context_name = NULL) {
    $this->initializeProvision($context_name);

    try {
      $this->invokeHook('provision_verify_validate');
      if (function_exists('drush_get_error') && drush_get_error()) {
        return;
      }

      $this->invokeHook('pre_provision_verify');
      if (function_exists('drush_get_error') && drush_get_error()) {
        return;
      }

      provision_backend_invoke(d()->name, 'provision-save');
      d()->command_invoke('verify');

      if (function_exists('drush_get_error') && drush_get_error()) {
        return;
      }

      $this->invokeHook('post_provision_verify');
    }
    finally {
      $this->finalizeProvision();
    }
  }

  /**
   * Provision a new site using the provided data.
   *
   * @command provision-install
   * @option client_email The email address of the client to use.
   * @option profile The profile to use when installing the site.
   * @option force-reinstall Delete the sites database and files before install.
   * @allow-additional-options
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_ROOT
   */
  public function provisionInstall() {
    $this->runProvisionCommand('provision-install');
  }

  /**
   * Provision a new site using the provided data (backend).
   *
   * @command provision-install-backend
   * @hidden
   * @option client_email The email address of the client to use.
   * @allow-additional-options
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_SITE
   */
  public function provisionInstallBackend() {
    $this->runProvisionCommand('provision-install-backend');
  }

  /**
   * Turn an already running site into a provisioned site.
   *
   * @command provision-import
   * @option client_email The email address of the client to use.
   * @allow-additional-options
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_ROOT
   */
  public function provisionImport() {
    $this->runProvisionCommand('provision-import');
  }

  /**
   * Generate a back up for the site.
   *
   * @command provision-backup
   * @argument backup_file Optional backup file path.
   * @option provision_backup_suffix Set the backup extension (default: .tar.gz).
   * @allow-additional-options
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_ROOT
   */
  public function provisionBackup($backup_file = NULL) {
    $this->runProvisionCommand('provision-backup', array($backup_file));
  }

  /**
   * Enable a disabled site.
   *
   * @command provision-enable
   * @allow-additional-options
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_ROOT
   */
  public function provisionEnable() {
    $this->runProvisionCommand('provision-enable');
  }

  /**
   * Disable a site.
   *
   * @command provision-disable
   * @argument domain Optional domain to disable.
   * @allow-additional-options
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_ROOT
   */
  public function provisionDisable($domain = NULL) {
    $this->runProvisionCommand('provision-disable', array($domain));
  }

  /**
   * Lock a platform from having any other sites provisioned on it.
   *
   * @command provision-lock
   * @allow-additional-options
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_ROOT
   */
  public function provisionLock() {
    $this->runProvisionCommand('provision-lock');
  }

  /**
   * Unlock a platform so that sites can be provisioned on it.
   *
   * @command provision-unlock
   * @allow-additional-options
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_ROOT
   */
  public function provisionUnlock() {
    $this->runProvisionCommand('provision-unlock');
  }

  /**
   * Restore the site to a previous backup.
   *
   * @command provision-restore
   * @argument backup_file Backup to restore.
   * @allow-additional-options
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_ROOT
   */
  public function provisionRestore($backup_file = NULL) {
    $this->runProvisionCommand('provision-restore', array($backup_file));
  }

  /**
   * Deploy an existing backup to a new url.
   *
   * @command provision-deploy
   * @argument backup_file Backup to deploy.
   * @option old_uri Old site uri to replace in the database.
   * @allow-additional-options
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_ROOT
   */
  public function provisionDeploy($backup_file = NULL) {
    $this->runProvisionCommand('provision-deploy', array($backup_file));
  }

  /**
   * Migrate a site between platforms.
   *
   * @command provision-migrate
   * @argument platform_name Target platform alias.
   * @argument new_name Optional new site name.
   * @option profile Drupal profile to use.
   * @allow-additional-options
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_ROOT
   */
  public function provisionMigrate($platform_name = NULL, $new_name = NULL) {
    $this->runProvisionCommand('provision-migrate', array($platform_name, $new_name));
  }

  /**
   * Clone a site to another name or platform.
   *
   * @command provision-clone
   * @argument new_name New site name.
   * @argument platform Optional platform alias.
   * @option profile Drupal profile to use.
   * @option new_db_server New database server alias.
   * @allow-additional-options
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_ROOT
   */
  public function provisionClone($new_name = NULL, $platform = NULL) {
    $this->runProvisionCommand('provision-clone', array($new_name, $platform));
  }

  /**
   * Delete a site or platform.
   *
   * @command provision-delete
   * @option force Force deletion.
   * @allow-additional-options
   * @bootstrap DRUSH_BOOTSTRAP_DRUSH
   */
  public function provisionDelete() {
    $this->runProvisionCommand('provision-delete');
  }

  /**
   * Generate a one-time login reset URL.
   *
   * @command provision-login-reset
   * @allow-additional-options
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_ROOT
   */
  public function provisionLoginReset() {
    $this->runProvisionCommand('provision-login-reset');
  }

  /**
   * Delete a backup file.
   *
   * @command provision-backup-delete
   * @argument backup_file Backup file to delete.
   * @allow-additional-options
   * @bootstrap DRUSH_BOOTSTRAP_DRUSH
   */
  public function provisionBackupDelete($backup_file = NULL) {
    $this->runProvisionCommand('provision-backup-delete', array($backup_file));
  }

  /**
   * Parse the output of --backend commands to a human readable form.
   *
   * @command backend-parse
   * @bootstrap DRUSH_BOOTSTRAP_DRUSH
   */
  public function backendParse() {
    $this->initializeProvision();
    drush_provision_backend_parse();
  }
}
