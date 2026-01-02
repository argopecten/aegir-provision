<?php
/**
 * @file
 * Base class for Drupal platform operations.
 */

abstract class Provision_Platform_DrupalBase {
  protected $majorVersion;
  protected $scriptVersion;
  protected $scriptRoot;
  protected $supportedPhpVersions = array();
  protected $supportedDrushVersions = array();

  public function __construct($majorVersion, $scriptVersion = NULL, array $supportedPhpVersions = array(), array $supportedDrushVersions = array()) {
    $this->majorVersion = (int) $majorVersion;
    $this->scriptVersion = (int) ($scriptVersion ? $scriptVersion : $majorVersion);
    $this->scriptRoot = dirname(__DIR__, 2) . '/platform/drupal';
    $this->supportedPhpVersions = $supportedPhpVersions;
    $this->supportedDrushVersions = $supportedDrushVersions;
  }

  public function getMajorVersion() {
    return $this->majorVersion;
  }

  public function getSupportedPhpVersions() {
    return $this->supportedPhpVersions;
  }

  public function getSupportedDrushVersions() {
    return $this->supportedDrushVersions;
  }

  public function install() {
    return $this->runScript('install', TRUE);
  }

  public function import() {
    return $this->runScript('import');
  }

  public function deploy() {
    return $this->runScript('deploy', TRUE);
  }

  public function clearCaches() {
    return $this->runScript('clear');
  }

  public function cronKey() {
    return $this->runScript('cron_key');
  }

  public function loadPackagesHelpers() {
    return $this->runScript('packages', TRUE);
  }

  public function packages() {
    $this->loadPackagesHelpers();

    $packages['base'] = _provision_find_packages('base');
    $packages['sites-all'] = _provision_find_packages('sites', 'all');

    // Create a package for the Drupal release.
    $packages['base']['platforms'] = _provision_find_platforms();

    // Find install profiles.
    $profiles = _provision_find_profiles();
    drush_set_option('profiles', array_keys((array) $profiles), 'drupal');

    // Iterate through the install profiles, finding the profile specific packages.
    foreach ($profiles as $profile => $info) {
      if (empty($info->version)) {
        $info->version = drush_drupal_version();
      }
      $packages['base']['profiles'][$profile] = $info;
      $packages['profiles'][$profile] = _provision_find_packages('profiles', $profile);
    }

    return $packages;
  }

  public function systemMap() {
    $this->loadPackagesHelpers();
    return _provision_drupal_system_map();
  }

  protected function scriptPath($name) {
    return $this->scriptRoot . '/' . $name . '_' . $this->scriptVersion . '.inc';
  }

  protected function runScript($name, $once = FALSE) {
    $path = $this->scriptPath($name);
    if (!is_readable($path)) {
      drush_set_error('PROVISION_PLATFORM_SCRIPT_MISSING',
        dt('Missing Drupal @version platform script: @path', array(
          '@version' => $this->scriptVersion,
          '@path' => $path,
        )));
      return FALSE;
    }

    if ($once) {
      require_once $path;
    }
    else {
      require $path;
    }
    return TRUE;
  }
}
