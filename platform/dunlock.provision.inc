<?php

/**
 * Drush hooks for the provision-dunlock command
 */

function drush_provision_dunlock() {

  drush_log(dt('Drush hooks for the provision-dunlock command'), 'message');

  $drush_lock_ctrl = d()->root . '/local_drush_locked.pid';

  if (d()->type === 'platform' && provision_file()->exists($drush_lock_ctrl)->status()) {
    provision_unlock_some_vnd();
  }
  return TRUE;
}
