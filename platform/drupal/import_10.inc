<?php

use Drupal\Core\Database\Database;

$db = NULL;
$connections = Database::getConnectionInfo();
if (isset($connections['default']['default'])) {
  $db = $connections['default']['default'];
}
elseif (isset($GLOBALS['databases']['default']['default'])) {
  $db = $GLOBALS['databases']['default']['default'];
}

if ($db) {
  drush_set_option('db_type', urldecode($db['driver']), 'site');
  drush_set_option('db_user', urldecode($db['username']), 'site');
  drush_set_option('db_host', urldecode($db['host']), 'site');
  drush_set_option('db_passwd', urldecode($db['password']), 'site');
  drush_set_option('db_name', urldecode($db['database']), 'site');

  drush_set_option('profile', \Drupal\Core\Site\Settings::get('install_profile', 'standard'), 'site');
  $language = \Drupal::languageManager()->getDefaultLanguage();
  drush_set_option('language', $language->getId(), 'site');
}
