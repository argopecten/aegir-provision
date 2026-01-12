<?php

$databases['default']['default'] = [
  'database' => $db['name'],
  'username' => $db['user'],
  'password' => $db['pass'],
  'host' => $db['host'],
  'port' => $db['port'],
  'driver' => $db['driver'],
  'prefix' => '',
];

$settings['hash_salt'] = $hash_salt;
$settings['config_sync_directory'] = $config_sync_directory;
$settings['file_private_path'] = $private_path;

if (!empty($trusted_host_patterns)) {
  $settings['trusted_host_patterns'] = $trusted_host_patterns;
}
