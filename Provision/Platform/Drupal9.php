<?php
/**
 * @file
 * Drupal 9 platform operations.
 */

class Provision_Platform_Drupal9 extends Provision_Platform_DrupalBase {
  public function __construct() {
    parent::__construct(9, NULL, array('>=8.0 <8.3'), array('^10', '^11', '^12'));
  }
}
