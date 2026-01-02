<?php
/**
 * @file
 * Drupal 8 platform operations.
 */

class Provision_Platform_Drupal8 extends Provision_Platform_DrupalBase {
  public function __construct() {
    parent::__construct(8, NULL, array('>=7.3 <8.2'), array('^10'));
  }
}
