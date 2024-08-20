<?php

/**
 * Drush hooks for the provision-dlock command
 */

function drush_provision_dlock() {

  drush_log(dt('Drush hooks for the provision-dlock command'), 'message');

  if (d()->type === 'platform') {
    provision_lock_some_vnd();
  }
  return TRUE;
}
