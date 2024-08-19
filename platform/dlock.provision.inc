<?php

/**
 * Drush hooks for the provision-dlock command
 */

function drush_provision_dlock() {
  # except the hostmaster platform
  if (d()->type === 'platform' &&
     provision_file()->exists(d()->root)->status() &&


    drush_log(dt('Drupal root reported in provision_lock_drush_local_vnd: !vendor', array('!vendor' => d()->root)), 'message');

    // Fix ownership first
    $success = drush_shell_exec("sudo --non-interactive /usr/local/bin/fix-drupal-platform-ownership.sh --root=%s --script-user=%s --web-group=%s", d()->root, d()->server->script_user, d()->server->web_group);

    $dru_vnd_drush_local = d()->root . '/vendor/drush';
    $dru_vnd_drush_above = d()->root . '/../vendor/drush';

    if (provision_file()->exists($dru_vnd_drush_local)->status()) {
      provision_file()->chmod($dru_vnd_drush_local, 0400)
      ->succeed('Changed permissions of <code>@path</code> to @perm')
      ->fail('Could not change permissions of <code>@path</code> to @perm');
    }
    if (provision_file()->exists($dru_vnd_drush_above)->status()) {
      provision_file()->chmod($dru_vnd_drush_above, 0400)
      ->succeed('Changed permissions of <code>@path</code> to @perm')
      ->fail('Could not change permissions of <code>@path</code> to @perm');
    }

    $drush_lock_ctrl = d()->root . '/local_drush_locked.pid';

    if (!provision_file()->exists($drush_lock_ctrl)->status()) {
      // Create drush-lock flag file.
      $drush_lock_ctrl_info = "Drush-Lock Flag File \n";
      $local_description = 'Drush-Lock Flag File';
      provision_file()->file_put_contents($drush_lock_ctrl, $drush_lock_ctrl_info)
        ->succeed('Generated ' . $local_description)
      	->fail('Could not generate ' . $local_description);
    }

    $drush_unlock_ctrl = d()->root . '/local_drush_unlocked.pid';

    if (provision_file()->exists($drush_unlock_ctrl)->status()) {
      // Delete drush-unlocked flag file.
      provision_file()->unlink($drush_unlock_ctrl)
        ->succeed('Removed Drush-UnLocked Flag File')
        ->fail('Could not remove Drush-UnLocked Flag File');
    }

  }
  else {
    return TRUE;
  }
}
