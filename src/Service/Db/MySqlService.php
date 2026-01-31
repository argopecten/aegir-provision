<?php

declare(strict_types=1);

namespace Aegir\Provision\Service\Db;

use Aegir\Provision\Core\Context;
use Aegir\Provision\Core\ProcessRunner;
use Aegir\Provision\Service\DbServiceInterface;

final class MySqlService implements DbServiceInterface {
  private ProcessRunner $runner;
  /** @var array<string,\PDO> Cached PDO connections keyed by server context name */
  private array $pdoConnections = [];

  public function __construct(ProcessRunner $runner) {
    $this->runner = $runner;
  }

  /**
   * Get or create a PDO connection for the given server context.
   *
   * @param Context $server Server context with database connection details
   * @return \PDO PDO connection instance
   * @throws \RuntimeException If connection fails
   */
  private function getPdoConnection(Context $server): \PDO {
    $contextName = $server->get('name', 'default');
    
    if (isset($this->pdoConnections[$contextName])) {
      return $this->pdoConnections[$contextName];
    }

    $host = (string) $server->get('db_host', '127.0.0.1');
    $port = (string) $server->get('db_port', '3306');
    $user = (string) $server->get('db_admin_user', 'root');
    $password = (string) $server->get('db_admin_passwd', '');
    $socket = $server->get('db_socket');

    // Build DSN
    if ($socket) {
      $dsn = "mysql:unix_socket={$socket}";
    } else {
      $dsn = "mysql:host={$host};port={$port}";
    }

    try {
      $pdo = new \PDO($dsn, $user, $password, [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
      ]);
      
      $this->pdoConnections[$contextName] = $pdo;
      return $pdo;
    } catch (\PDOException $e) {
      throw new \RuntimeException("Failed to connect to MySQL: {$e->getMessage()}", 0, $e);
    }
  }

  public function ensureDatabase(Context $server, string $dbName): void {
    $pdo = $this->getPdoConnection($server);
    
    try {
      // Database names cannot be parameterized, but we validate it
      if (!preg_match('/^[a-zA-Z0-9_]+$/', $dbName)) {
        throw new \InvalidArgumentException("Invalid database name: {$dbName}");
      }
      
      $sql = "CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
      $pdo->exec($sql);
    } catch (\PDOException $e) {
      throw new \RuntimeException("Failed to create database '{$dbName}': {$e->getMessage()}", 0, $e);
    }
  }

  public function ensureUser(Context $server, string $dbUser, string $dbPass, string $dbHost): void {
    $pdo = $this->getPdoConnection($server);
    
    try {
      // Note: In MySQL 8.0+, CREATE USER IF NOT EXISTS and ALTER USER handle authentication
      // We use prepared statements where possible, but user/host must be quoted identifiers
      $stmt = $pdo->prepare("CREATE USER IF NOT EXISTS ?@? IDENTIFIED BY ?");
      $stmt->execute([$dbUser, $dbHost, $dbPass]);
      
      $stmt = $pdo->prepare("ALTER USER ?@? IDENTIFIED BY ?");
      $stmt->execute([$dbUser, $dbHost, $dbPass]);
    } catch (\PDOException $e) {
      throw new \RuntimeException("Failed to create/update user '{$dbUser}'@'{$dbHost}': {$e->getMessage()}", 0, $e);
    }
  }

  public function grant(Context $server, string $dbName, string $dbUser, string $dbHost): void {
    $pdo = $this->getPdoConnection($server);
    
    try {
      // Validate database name for identifier safety
      if (!preg_match('/^[a-zA-Z0-9_]+$/', $dbName)) {
        throw new \InvalidArgumentException("Invalid database name: {$dbName}");
      }
      
      // GRANT statements require quoted identifiers, cannot use placeholders for db/user/host
      $stmt = $pdo->prepare("GRANT ALL PRIVILEGES ON `{$dbName}`.* TO ?@?");
      $stmt->execute([$dbUser, $dbHost]);
      
      $pdo->exec('FLUSH PRIVILEGES');
    } catch (\PDOException $e) {
      throw new \RuntimeException("Failed to grant privileges on '{$dbName}' to '{$dbUser}'@'{$dbHost}': {$e->getMessage()}", 0, $e);
    }
  }

  public function dropDatabase(Context $server, string $dbName): void {
    $pdo = $this->getPdoConnection($server);
    
    try {
      // Validate database name for identifier safety
      if (!preg_match('/^[a-zA-Z0-9_]+$/', $dbName)) {
        throw new \InvalidArgumentException("Invalid database name: {$dbName}");
      }
      
      $sql = "DROP DATABASE IF EXISTS `{$dbName}`";
      $pdo->exec($sql);
    } catch (\PDOException $e) {
      throw new \RuntimeException("Failed to drop database '{$dbName}': {$e->getMessage()}", 0, $e);
    }
  }

  public function dropUser(Context $server, string $dbUser, string $dbHost): void {
    $pdo = $this->getPdoConnection($server);
    
    try {
      $stmt = $pdo->prepare("DROP USER IF EXISTS ?@?");
      $stmt->execute([$dbUser, $dbHost]);
    } catch (\PDOException $e) {
      throw new \RuntimeException("Failed to drop user '{$dbUser}'@'{$dbHost}': {$e->getMessage()}", 0, $e);
    }
  }

  public function dump(Context $server, string $dbName, string $targetFile, bool $gzip = FALSE): string {
    $command = array_merge(
      $this->mysqlBaseArgs($server, 'mysqldump'),
      ['--single-transaction', '--quick', '--skip-lock-tables', $dbName]
    );

    $env = $this->mysqlEnv($server);
    $result = $this->runner->run($command, NULL, $env);
    if ($result['exit_code'] !== 0) {
      throw new \RuntimeException('mysqldump failed: ' . $result['error']);
    }

    if (file_put_contents($targetFile, $result['output']) === FALSE) {
      throw new \RuntimeException('Unable to write database dump: ' . $targetFile);
    }

    if ($gzip) {
      $gzFile = $targetFile . '.gz';
      $gz = gzopen($gzFile, 'wb9');
      if ($gz === FALSE) {
        throw new \RuntimeException('Unable to open gzip file: ' . $gzFile);
      }
      gzwrite($gz, $result['output']);
      gzclose($gz);
      @unlink($targetFile);
      return $gzFile;
    }

    return $targetFile;
  }

  public function import(Context $server, string $dbName, string $sourceFile): void {
    $command = array_merge($this->mysqlBaseArgs($server, 'mysql'), [$dbName]);
    $env = $this->mysqlEnv($server);

    if (str_ends_with($sourceFile, '.gz')) {
      $tmp = $this->uncompress($sourceFile);
      $handle = fopen($tmp, 'rb');
      if ($handle === FALSE) {
        throw new \RuntimeException('Unable to open backup: ' . $tmp);
      }
      $result = $this->runner->run($command, NULL, $env, $handle);
      fclose($handle);
      @unlink($tmp);
    }
    else {
      $handle = fopen($sourceFile, 'rb');
      if ($handle === FALSE) {
        throw new \RuntimeException('Unable to open backup: ' . $sourceFile);
      }
      $result = $this->runner->run($command, NULL, $env, $handle);
      fclose($handle);
    }

    if ($result['exit_code'] !== 0) {
      throw new \RuntimeException('mysql import failed: ' . $result['error']);
    }
  }

  public function testConnection(Context $server): void {
    try {
      $pdo = $this->getPdoConnection($server);
      // Simple query to verify connection works
      $pdo->query('SELECT 1');
    } catch (\PDOException | \RuntimeException $e) {
      throw new \RuntimeException("MySQL connection test failed: {$e->getMessage()}", 0, $e);
    }
  }

  /**
   * @return string[]
   */
  private function mysqlBaseArgs(Context $server, string $binary): array {
    $host = (string) $server->get('db_host', '127.0.0.1');
    $port = (string) $server->get('db_port', '3306');
    $user = (string) $server->get('db_admin_user', 'root');

    $args = [$binary, '--protocol=TCP', '--host=' . $host, '--port=' . $port, '--user=' . $user];

    if ($server->get('db_socket')) {
      $args[] = '--socket=' . $server->get('db_socket');
    }

    return $args;
  }

  /**
   * @return array<string,string>
   */
  private function mysqlEnv(Context $server): array {
    $password = (string) $server->get('db_admin_passwd', '');
    return $password !== '' ? ['MYSQL_PWD' => $password] : [];
  }

  private function uncompress(string $sourceFile): string {
    $tmp = sys_get_temp_dir() . '/aegir-db-' . uniqid('', TRUE) . '.sql';
    $command = ['gunzip', '-c', $sourceFile];
    $result = $this->runner->run($command);
    if ($result['exit_code'] !== 0) {
      throw new \RuntimeException('gunzip failed: ' . $result['error']);
    }
    if (file_put_contents($tmp, $result['output']) === FALSE) {
      throw new \RuntimeException('Unable to write temp SQL file: ' . $tmp);
    }
    return $tmp;
  }
}
