<?php

/**
 * Provision disable command
 *
 * Disable an enabled and installed site
 */

/**
 * Make a backup of the site before we mess with it
 */
function drush_provision_drupal_pre_provision_disable($backup_file = null) {
  if (d()->type == 'site') {
    d()->site_enabled = false;
  }
}

/**
 * Set the enabled property of the site to false
 */
function drush_provision_drupal_post_provision_disable() {
  if (d()->type == 'site') {
    d()->write_alias();
  }
}
