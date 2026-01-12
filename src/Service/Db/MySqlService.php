<?php

declare(strict_types=1);

namespace Aegir\ProvisionD11\Service\Db;

use Aegir\ProvisionD11\Core\Context;
use Aegir\ProvisionD11\Core\ProcessRunner;

final class MySqlService {
  private ProcessRunner $runner;

  public function __construct(ProcessRunner $runner) {
    $this->runner = $runner;
  }

  public function ensureDatabase(Context $server, string $dbName): void {
    $this->runSql($server, sprintf('CREATE DATABASE IF NOT EXISTS `%s`', $dbName));
  }

  public function ensureUser(Context $server, string $dbUser, string $dbPass, string $dbHost): void {
    $this->runSql(
      $server,
      sprintf("CREATE USER IF NOT EXISTS '%s'@'%s' IDENTIFIED BY '%s'", $dbUser, $dbHost, $this->escapeSql($dbPass))
    );
    $this->runSql(
      $server,
      sprintf("ALTER USER '%s'@'%s' IDENTIFIED BY '%s'", $dbUser, $dbHost, $this->escapeSql($dbPass))
    );
  }

  public function grant(Context $server, string $dbName, string $dbUser, string $dbHost): void {
    $this->runSql(
      $server,
      sprintf("GRANT ALL PRIVILEGES ON `%s`.* TO '%s'@'%s'", $dbName, $dbUser, $dbHost)
    );
    $this->runSql($server, 'FLUSH PRIVILEGES');
  }

  public function dropDatabase(Context $server, string $dbName): void {
    $this->runSql($server, sprintf('DROP DATABASE IF EXISTS `%s`', $dbName));
  }

  public function dropUser(Context $server, string $dbUser, string $dbHost): void {
    $this->runSql($server, sprintf("DROP USER IF EXISTS '%s'@'%s'", $dbUser, $dbHost));
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
    $command = array_merge($this->mysqlBaseArgs($server, 'mysql'), ['-e', 'SELECT 1']);
    $env = $this->mysqlEnv($server);
    $result = $this->runner->run($command, NULL, $env);
    if ($result['exit_code'] !== 0) {
      throw new \RuntimeException('mysql connection failed: ' . $result['error']);
    }
  }

  private function runSql(Context $server, string $sql): void {
    $command = array_merge($this->mysqlBaseArgs($server, 'mysql'), ['-e', $sql]);
    $env = $this->mysqlEnv($server);
    $result = $this->runner->run($command, NULL, $env);
    if ($result['exit_code'] !== 0) {
      throw new \RuntimeException('mysql command failed: ' . $result['error']);
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

  private function escapeSql(string $value): string {
    return str_replace(["\\", "'"], ["\\\\", "\\'"], $value);
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
