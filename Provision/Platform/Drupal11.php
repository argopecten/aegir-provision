<?php
/**
 * @file
 * Drupal 11 platform operations.
 */

class Provision_Platform_Drupal11 extends Provision_Platform_DrupalBase {
  public function __construct() {
    parent::__construct(11, NULL, array('>=8.3'), array('^13'));
  }
}
