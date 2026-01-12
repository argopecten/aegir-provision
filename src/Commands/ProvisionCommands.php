<?php

declare(strict_types=1);

namespace Aegir\ProvisionD11\Commands;

use Aegir\ProvisionD11\Config\TemplateRenderer;
use Aegir\ProvisionD11\Core\ContextRepository;
use Aegir\ProvisionD11\Core\Filesystem;
use Aegir\ProvisionD11\Core\ProcessRunner;
use Aegir\ProvisionD11\Provision\ProvisionManager;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\Yaml\Yaml;

final class ProvisionCommands extends DrushCommands {
  #[CLI\Command(name: 'provision-save')]
  #[CLI\Argument(name: 'context', description: 'Context name, e.g. @example.com')]
  #[CLI\Option(name: 'data', description: 'Context data as JSON or YAML string')]
  #[CLI\Option(name: 'data-file', description: 'Context data file (JSON or YAML)')]
  #[CLI\Option(name: 'type', description: 'Context type: server, platform, site')]
  #[CLI\Option(name: 'delete', description: 'Delete context')]
  public function provisionSave(string $context, array $options = [
    'data' => NULL,
    'data-file' => NULL,
    'type' => NULL,
    'delete' => FALSE,
  ]): void {
    $data = $this->readContextData($options['data'], $options['data-file']);
    $this->manager()->saveContext($context, $data, $options['type'], (bool) $options['delete']);
  }

  #[CLI\Command(name: 'provision-verify', aliases: ['pv', 'verify'])]
  #[CLI\Argument(name: 'context', description: 'Context name, e.g. @example.com')]
  public function provisionVerify(string $context): void {
    $this->manager()->verify($context);
  }

  #[CLI\Command(name: 'provision-install')]
  #[CLI\Argument(name: 'context', description: 'Site context name')]
  public function provisionInstall(string $context): void {
    $this->manager()->install($context);
  }

  #[CLI\Command(name: 'provision-import')]
  #[CLI\Argument(name: 'context', description: 'Context name')]
  public function provisionImport(string $context): void {
    $this->manager()->importContext($context);
  }

  #[CLI\Command(name: 'provision-backup')]
  #[CLI\Argument(name: 'context', description: 'Site context name')]
  #[CLI\Argument(name: 'backup-file', description: 'Optional backup file path', required: FALSE)]
  public function provisionBackup(string $context, ?string $backupFile = NULL): void {
    $file = $this->manager()->backup($context, $backupFile);
    $this->output()->writeln($file);
  }

  #[CLI\Command(name: 'provision-restore')]
  #[CLI\Argument(name: 'context', description: 'Site context name')]
  #[CLI\Argument(name: 'backup-file', description: 'Backup file to restore')]
  public function provisionRestore(string $context, string $backupFile): void {
    $this->manager()->restore($context, $backupFile);
  }

  #[CLI\Command(name: 'provision-deploy')]
  #[CLI\Argument(name: 'context', description: 'Site context name')]
  #[CLI\Argument(name: 'backup-file', description: 'Backup file to deploy')]
  public function provisionDeploy(string $context, string $backupFile): void {
    $this->manager()->deploy($context, $backupFile);
  }

  #[CLI\Command(name: 'provision-migrate')]
  #[CLI\Argument(name: 'context', description: 'Site context name')]
  #[CLI\Argument(name: 'platform', description: 'Target platform alias')]
  public function provisionMigrate(string $context, string $platform): void {
    $this->manager()->migrate($context, $platform);
  }

  #[CLI\Command(name: 'provision-clone')]
  #[CLI\Argument(name: 'context', description: 'Source site context name')]
  #[CLI\Argument(name: 'new-site', description: 'New site alias')]
  #[CLI\Argument(name: 'platform', description: 'Target platform alias', required: FALSE)]
  public function provisionClone(string $context, string $newSite, ?string $platform = NULL): void {
    $this->manager()->cloneSite($context, $newSite, $platform ?? '');
  }

  #[CLI\Command(name: 'provision-enable')]
  #[CLI\Argument(name: 'context', description: 'Site context name')]
  public function provisionEnable(string $context): void {
    $this->manager()->enable($context);
  }

  #[CLI\Command(name: 'provision-disable')]
  #[CLI\Argument(name: 'context', description: 'Site context name')]
  public function provisionDisable(string $context): void {
    $this->manager()->disable($context);
  }

  #[CLI\Command(name: 'provision-lock')]
  #[CLI\Argument(name: 'context', description: 'Context name')]
  public function provisionLock(string $context): void {
    $this->manager()->lock($context);
  }

  #[CLI\Command(name: 'provision-unlock')]
  #[CLI\Argument(name: 'context', description: 'Context name')]
  public function provisionUnlock(string $context): void {
    $this->manager()->unlock($context);
  }

  #[CLI\Command(name: 'provision-delete')]
  #[CLI\Argument(name: 'context', description: 'Context name')]
  #[CLI\Option(name: 'delete-files', description: 'Remove site files on disk')]
  #[CLI\Option(name: 'delete-db', description: 'Drop site database and db user')]
  public function provisionDelete(string $context, array $options = ['delete-files' => FALSE, 'delete-db' => FALSE]): void {
    $this->manager()->delete($context, (bool) $options['delete-files'], (bool) $options['delete-db']);
  }

  #[CLI\Command(name: 'provision-login-reset')]
  #[CLI\Argument(name: 'context', description: 'Site context name')]
  public function provisionLoginReset(string $context): void {
    $this->manager()->loginReset($context);
  }

  #[CLI\Command(name: 'backend-parse')]
  public function backendParse(): void {
    $input = stream_get_contents(STDIN);
    if ($input !== FALSE && trim($input) !== '') {
      $this->output()->writeln($input);
    }
  }

  private function manager(): ProvisionManager {
    return new ProvisionManager(
      new ContextRepository(),
      new Filesystem(),
      new ProcessRunner(),
      new TemplateRenderer(),
      $this->logger()
    );
  }

  /**
   * @return array<string,mixed>
   */
  private function readContextData(?string $data, ?string $file): array {
    if ($file !== NULL) {
      if (!is_readable($file)) {
        throw new \RuntimeException('Context data file not readable: ' . $file);
      }
      $data = file_get_contents($file);
      if ($data === FALSE) {
        throw new \RuntimeException('Unable to read context data file: ' . $file);
      }
    }

    if ($data === NULL || trim($data) === '') {
      return [];
    }

    $data = trim($data);
    if (str_starts_with($data, '{') || str_starts_with($data, '[')) {
      $decoded = json_decode($data, TRUE);
      if (!is_array($decoded)) {
        throw new \RuntimeException('Invalid JSON context data.');
      }
      return $this->normalizeContextData($decoded);
    }

    $decoded = Yaml::parse($data);
    if (!is_array($decoded)) {
      throw new \RuntimeException('Invalid YAML context data.');
    }

    return $this->normalizeContextData($decoded);
  }

  /**
   * @param array<string,mixed> $data
   * @return array<string,mixed>
   */
  private function normalizeContextData(array $data): array {
    if (count($data) === 1) {
      $first = reset($data);
      if (is_array($first)) {
        return $first;
      }
    }
    return $data;
  }
}
