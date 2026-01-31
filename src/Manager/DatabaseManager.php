<?php

declare(strict_types=1);

namespace Aegir\Provision\Manager;

use Aegir\Provision\Core\Context;
use Aegir\Provision\Core\ProcessRunner;
use Aegir\Provision\Core\ValueObject\DatabaseCredentials;
use Aegir\Provision\Service\Db\MySqlService;
use Aegir\Provision\Service\DbServiceInterface;
use Aegir\Provision\Service\ServiceRegistry;

/**
 * Manages database operations for sites.
 */
final class DatabaseManager {
  private ProcessRunner $runner;
  private ServiceRegistry $serviceRegistry;

  public function __construct(ProcessRunner $runner, ServiceRegistry $serviceRegistry) {
    $this->runner = $runner;
    $this->serviceRegistry = $serviceRegistry;
  }

  /**
   * Ensure database and user exist for a site, return credentials.
   */
  public function ensureSiteDatabase(Context $site, Context $server): DatabaseCredentials {
    $dbType = (string) $site->get('db_type', 'mysql');
    if ($dbType !== 'mysql') {
      throw new \RuntimeException('Unsupported db type: ' . $dbType);
    }
    $dbName = (string) $site->get('db_name');
    $dbUser = (string) $site->get('db_user');
    $dbPass = (string) $site->get('db_passwd');

    if ($dbName === '' || $dbUser === '' || $dbPass === '') {
      $generated = $this->generateDbCredentials($site->name());
      $dbName = $dbName ?: $generated['name'];
      $dbUser = $dbUser ?: $generated['user'];
      $dbPass = $dbPass ?: $generated['pass'];
      $site->set('db_name', $dbName);
      $site->set('db_user', $dbUser);
      $site->set('db_passwd', $dbPass);
      $site->set('db_host', $site->get('db_host', $server->get('db_host', '127.0.0.1')));
      $site->set('db_port', $site->get('db_port', $server->get('db_port', 3306)));
      $site->set('db_type', $site->get('db_type', 'mysql'));
    }

    /** @var DbServiceInterface $mysql */
    $mysql = $this->serviceRegistry->get('db', $dbType);
    $dbHost = $this->resolveDbGrantHost($server);
    $mysql->ensureDatabase($server, $dbName);
    $mysql->ensureUser($server, $dbUser, $dbPass, $dbHost);
    $mysql->grant($server, $dbName, $dbUser, $dbHost);

    $host = (string) $site->get('db_host', $server->get('db_host', '127.0.0.1'));
    $port = (int) $site->get('db_port', $server->get('db_port', 3306));
    $socket = $site->get('db_socket') ?: $server->get('db_socket');

    return new DatabaseCredentials(
      name: $dbName,
      user: $dbUser,
      password: $dbPass,
      host: $host,
      port: $port,
      socket: $socket ? (string) $socket : null,
    );
  }

  /**
   * @return array{name:string,user:string,pass:string}
   */
  public function generateDbCredentials(string $seed): array {
    $slug = preg_replace('/[^a-z0-9]+/i', '_', strtolower($seed));
    $slug = trim((string) $slug, '_');
    $base = $slug !== '' ? $slug : 'site';

    return [
      'name' => substr('aegir_' . $base, 0, 63),
      'user' => substr('aegir_' . $base, 0, 32),
      'pass' => bin2hex(random_bytes(12)),
    ];
  }

  public function resolveDbGrantHost(Context $server): string {
    return $server->get('db_grant_all_hosts') ? '%' : (string) $server->get('db_host', 'localhost');
  }
}
