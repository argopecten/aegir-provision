<?php
/**
 * @file
 * Drupal 10 platform operations.
 */

class Provision_Platform_Drupal10 extends Provision_Platform_DrupalBase {
  public function __construct() {
    parent::__construct(10, NULL, array('>=8.1 <8.4'), array('^11', '^12', '^13'));
  }
}
