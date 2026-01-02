<?php
/**
 * @file
 * Drupal platform factory.
 */

class Provision_Platform_Factory {
  public static function current() {
    $major = (int) drush_drupal_major_version();
    return self::forMajor($major);
  }

  public static function forMajor($major) {
    switch ((int) $major) {
      case 8:
        return new Provision_Platform_Drupal8();
      case 9:
        return new Provision_Platform_Drupal9();
      case 10:
        return new Provision_Platform_Drupal10();
      case 11:
        return new Provision_Platform_Drupal11();
      default:
        drush_set_error('PROVISION_UNSUPPORTED_DRUPAL_VERSION',
          dt('Drupal @major is not supported by provision.', array('@major' => $major)));
        return NULL;
    }
  }
}
