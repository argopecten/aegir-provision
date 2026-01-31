<?php

declare(strict_types=1);

namespace Aegir\Provision\Manager;

use Aegir\Provision\Core\Context;
use Aegir\Provision\Core\ContextRepository;
use Aegir\Provision\Core\ContextType;
use Aegir\Provision\Core\Filesystem;
use Aegir\Provision\Core\ProcessRunner;
use Aegir\Provision\Event\BackupEvent;
use Aegir\Provision\Event\DeployEvent;
use Aegir\Provision\Event\ProvisionEvents;
use Aegir\Provision\Event\RestoreEvent;
use Aegir\Provision\Service\Db\MySqlService;
use Aegir\Provision\Service\DbServiceInterface;
use Aegir\Provision\Service\ServiceRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Manages backup and restore operations.
 *
 * Supports sites and platforms. Server backups are optional and not yet implemented.
 */
final class BackupRestoreManager {
  private ContextRepository $contexts;
  private Filesystem $filesystem;
  private ProcessRunner $runner;
  private LoggerInterface $logger;
  private EventDispatcherInterface $dispatcher;
  private ContextLoader $loader;
  private PathResolver $pathResolver;
  private DatabaseManager $dbManager;
  private ServiceRegistry $serviceRegistry;

  public function __construct(
    ContextRepository $contexts,
    Filesystem $filesystem,
    ProcessRunner $runner,
    LoggerInterface $logger,
    EventDispatcherInterface $dispatcher,
    ContextLoader $loader,
    PathResolver $pathResolver,
    DatabaseManager $dbManager,
    ServiceRegistry $serviceRegistry
  ) {
    $this->contexts = $contexts;
    $this->filesystem = $filesystem;
    $this->runner = $runner;
    $this->logger = $logger;
    $this->dispatcher = $dispatcher;
    $this->loader = $loader;
    $this->pathResolver = $pathResolver;
    $this->dbManager = $dbManager;
    $this->serviceRegistry = $serviceRegistry;
  }

  public function backup(string $contextName, ?string $backupFile): string {
    $context = $this->contexts->load($contextName);

    if ($context->type() === ContextType::SITE) {
      return $this->backupSite($context, $backupFile);
    }
    if ($context->type() === ContextType::PLATFORM) {
      return $this->backupPlatform($context, $backupFile);
    }
    throw new \RuntimeException('Backup only supports site and platform contexts.');
  }

  private function backupSite(Context $site, ?string $backupFile): string {
    $platform = $this->loader->loadPlatform($site);
    $server = $this->loader->loadServer($platform, $site);
    $dbServer = $this->loader->loadDbServer($site, $server);
    $paths = $this->pathResolver->resolveServerPaths($server);
    $backupDir = $paths->backupPath . '/' . $site->name();
    $this->filesystem->ensureDir($backupDir, 0750);

    /** @var DbServiceInterface $mysql */
    $mysql = $this->serviceRegistry->get('db', 'mysql');
    $dbName = (string) $site->get('db_name');
    if ($dbName === '') {
      throw new \RuntimeException('No db_name set for site context.');
    }

    if ($backupFile !== NULL && !str_contains($backupFile, '/')) {
      $backupFile = $backupDir . '/' . $backupFile;
    }
    $backupFile = $backupFile ?: ($backupDir . '/' . $this->backupBaseName($site) . '.tar.gz');
    if (file_exists($backupFile)) {
      throw new \RuntimeException('Backup file already exists: ' . $backupFile);
    }

    try {
      // Dispatch VALIDATE event
      $event = new BackupEvent('validate', $site, $backupFile);
      $this->dispatcher->dispatch($event, ProvisionEvents::VALIDATE_BACKUP);

      // Dispatch BEFORE event
      $event = new BackupEvent('before', $site, $backupFile);
      $this->dispatcher->dispatch($event, ProvisionEvents::BEFORE_BACKUP);

      if ($this->isDbBackup($backupFile)) {
        $target = $backupFile;
        $gzip = str_ends_with($target, '.gz');
        $target = $gzip ? substr($target, 0, -3) : $target;
        $final = $mysql->dump($dbServer, $dbName, $target, $gzip);
        $this->logger->info('Database backup written to {file}.', ['file' => $final]);
        
        // Dispatch AFTER event
        $event = new BackupEvent('after', $site, $final);
        $this->dispatcher->dispatch($event, ProvisionEvents::AFTER_BACKUP);
        
        return $final;
      }

      $docroot = $this->pathResolver->resolveDocroot($platform);
      $sitePath = $this->pathResolver->resolveSitePath($site, $docroot);
      $tmpDir = $this->makeTempDir('aegir-backup-');
      $dumpFile = $tmpDir . '/database.sql';
      $mysql->dump($dbServer, $dbName, $dumpFile, FALSE);

      $this->createTarArchive($backupFile, $docroot, $sitePath, $dumpFile);
      $this->filesystem->removeDir($tmpDir);

      $this->logger->info('Site backup written to {file}.', ['file' => $backupFile]);

      // Dispatch AFTER event
      $event = new BackupEvent('after', $site, $backupFile);
      $this->dispatcher->dispatch($event, ProvisionEvents::AFTER_BACKUP);

    } catch (\Exception $e) {
      // Dispatch ROLLBACK event
      $event = new BackupEvent('rollback', $site, $backupFile ?? '', ['exception' => $e]);
      $this->dispatcher->dispatch($event, ProvisionEvents::ROLLBACK_BACKUP);
      throw $e;
    }

    return $backupFile;
  }

  public function restore(string $contextName, string $backupFile): void {
    $context = $this->contexts->load($contextName);

    if ($context->type() === ContextType::SITE) {
      $this->restoreSite($context, $backupFile);
      return;
    }
    if ($context->type() === ContextType::PLATFORM) {
      $this->restorePlatform($context, $backupFile);
      return;
    }
    throw new \RuntimeException('Restore only supports site and platform contexts.');
  }

  private function restoreSite(Context $site, string $backupFile): void {
    try {
      // Dispatch VALIDATE event
      $event = new RestoreEvent('validate', $site, $backupFile);
      $this->dispatcher->dispatch($event, ProvisionEvents::VALIDATE_RESTORE);

      // Dispatch BEFORE event
      $event = new RestoreEvent('before', $site, $backupFile);
      $this->dispatcher->dispatch($event, ProvisionEvents::BEFORE_RESTORE);

      $platform = $this->loader->loadPlatform($site);
    $server = $this->loader->loadServer($platform, $site);
    $dbServer = $this->loader->loadDbServer($site, $server);
    $db = $this->dbManager->ensureSiteDatabase($site, $dbServer);
    $paths = $this->pathResolver->resolveServerPaths($server);
    if (!str_contains($backupFile, '/')) {
      $backupFile = $paths->backupPath . '/' . $backupFile;
    }
    if (!is_readable($backupFile)) {
      throw new \RuntimeException('Backup file not readable: ' . $backupFile);
    }

    /** @var DbServiceInterface $mysql */
    $mysql = $this->serviceRegistry->get('db', 'mysql');
    if ($this->isDbBackup($backupFile)) {
      $mysql->import($dbServer, $db->name, $backupFile);
      $this->logger->info('Restored database from {file}.', ['file' => $backupFile]);
      return;
    }

    $docroot = $this->pathResolver->resolveDocroot($platform);
    $sitePath = $this->pathResolver->resolveSitePath($site, $docroot);
    $tmpDir = $this->makeTempDir('aegir-restore-');
    $this->extractTarArchive($backupFile, $tmpDir);

    $extractedSite = $tmpDir . '/sites/' . $site->get('uri');
    if (!is_dir($extractedSite)) {
      $this->filesystem->removeDir($tmpDir);
      throw new \RuntimeException('Backup does not contain site directory: ' . $extractedSite);
    }
    $this->filesystem->remove($sitePath);
    $this->copyFiles($extractedSite, $sitePath);

    $dumpFile = $tmpDir . '/database.sql';
    if (is_file($dumpFile)) {
      $mysql->import($dbServer, $db->name, $dumpFile);
    }
    else {
      $this->filesystem->removeDir($tmpDir);
      throw new \RuntimeException('Backup does not contain database.sql.');
    }

    $this->filesystem->removeDir($tmpDir);
    $this->logger->info('Restored site from {file}.', ['file' => $backupFile]);

      // Dispatch AFTER event
      $event = new RestoreEvent('after', $site, $backupFile);
      $this->dispatcher->dispatch($event, ProvisionEvents::AFTER_RESTORE);

    } catch (\Exception $e) {
      // Dispatch ROLLBACK event
      $event = new RestoreEvent('rollback', $site, $backupFile, ['exception' => $e]);
      $this->dispatcher->dispatch($event, ProvisionEvents::ROLLBACK_RESTORE);
      throw $e;
    }
  }

  public function deploy(string $contextName, string $backupFile): void {
    $context = $this->contexts->load($contextName);

    try {
      // Dispatch VALIDATE event
      $event = new DeployEvent('validate', $context, $backupFile);
      $this->dispatcher->dispatch($event, ProvisionEvents::VALIDATE_DEPLOY);

      // Dispatch BEFORE event
      $event = new DeployEvent('before', $context, $backupFile);
      $this->dispatcher->dispatch($event, ProvisionEvents::BEFORE_DEPLOY);

      $this->restore($contextName, $backupFile);

      // Dispatch AFTER event
      $event = new DeployEvent('after', $context, $backupFile);
      $this->dispatcher->dispatch($event, ProvisionEvents::AFTER_DEPLOY);

    } catch (\Exception $e) {
      // Dispatch ROLLBACK event
      $event = new DeployEvent('rollback', $context, $backupFile, ['exception' => $e]);
      $this->dispatcher->dispatch($event, ProvisionEvents::ROLLBACK_DEPLOY);
      throw $e;
    }
  }

  private function backupBaseName(Context $site): string {
    $name = (string) ($site->get('uri') ?: $site->name());
    return $name . '-' . gmdate('Ymd.His');
  }

  private function isDbBackup(string $file): bool {
    return str_ends_with($file, '.sql') || str_ends_with($file, '.sql.gz');
  }

  private function makeTempDir(string $prefix): string {
    $path = sys_get_temp_dir() . '/' . $prefix . uniqid('', TRUE);
    $this->filesystem->ensureDir($path, 0700);
    return $path;
  }

  private function createTarArchive(string $target, string $docroot, string $sitePath, string $dumpFile): void {
    $gzip = str_ends_with($target, '.gz') || str_ends_with($target, '.tgz');
    $flag = $gzip ? '-czf' : '-cf';
    $docroot = rtrim($docroot, '/');
    $relativeSite = str_starts_with($sitePath, $docroot . '/') ? substr($sitePath, strlen($docroot) + 1) : basename($sitePath);
    $command = [
      'tar',
      $flag,
      $target,
      '-C',
      $docroot,
      $relativeSite,
      '-C',
      dirname($dumpFile),
      basename($dumpFile),
    ];
    $result = $this->runner->run($command);
    if ($result['exit_code'] !== 0) {
      throw new \RuntimeException('tar failed: ' . $result['error']);
    }
  }

  private function extractTarArchive(string $archive, string $targetDir): void {
    $gzip = str_ends_with($archive, '.gz') || str_ends_with($archive, '.tgz');
    $flag = $gzip ? '-xzf' : '-xf';
    $command = ['tar', $flag, $archive, '-C', $targetDir];
    $result = $this->runner->run($command);
    if ($result['exit_code'] !== 0) {
      throw new \RuntimeException('tar extract failed: ' . $result['error']);
    }
  }

  private function copyFiles(string $source, string $destination): void {
    if (!is_dir($source)) {
      return;
    }
    $this->filesystem->ensureDir($destination, 0775);
    $items = scandir($source);
    if (!is_array($items)) {
      return;
    }
    foreach ($items as $item) {
      if ($item === '.' || $item === '..') {
        continue;
      }
      $src = $source . '/' . $item;
      $dst = $destination . '/' . $item;
      if (is_dir($src) && !is_link($src)) {
        $this->copyFiles($src, $dst);
      }
      else {
        copy($src, $dst);
      }
    }
  }

  private function backupPlatform(Context $platform, ?string $backupFile): string {
    $server = $this->loader->loadServer($platform);
    $paths = $this->pathResolver->resolveServerPaths($server);
    $backupDir = $paths->backupPath . '/platforms';
    $this->filesystem->ensureDir($backupDir, 0750);

    $docroot = $this->pathResolver->resolveDocroot($platform);
    if (!is_dir($docroot)) {
      throw new \RuntimeException('Platform docroot not found: ' . $docroot);
    }

    if ($backupFile !== NULL && !str_contains($backupFile, '/')) {
      $backupFile = $backupDir . '/' . $backupFile;
    }
    $backupFile = $backupFile ?: ($backupDir . '/' . $platform->name() . '-' . gmdate('Ymd.His') . '.tar.gz');
    
    if (file_exists($backupFile)) {
      throw new \RuntimeException('Backup file already exists: ' . $backupFile);
    }

    try {
      // Dispatch VALIDATE event
      $event = new BackupEvent('validate', $platform, $backupFile);
      $this->dispatcher->dispatch($event, ProvisionEvents::VALIDATE_BACKUP);

      // Dispatch BEFORE event
      $event = new BackupEvent('before', $platform, $backupFile);
      $this->dispatcher->dispatch($event, ProvisionEvents::BEFORE_BACKUP);

      // Create tar archive of the platform
    $gzip = str_ends_with($backupFile, '.gz') || str_ends_with($backupFile, '.tgz');
    $flag = $gzip ? '-czf' : '-cf';
    $command = [
      'tar',
      $flag,
      $backupFile,
      '-C',
      dirname($docroot),
      basename($docroot),
    ];
    
    $result = $this->runner->run($command);
    if ($result['exit_code'] !== 0) {
      throw new \RuntimeException('tar failed: ' . $result['error']);
    }

    $this->logger->info('Platform backup written to {file}.', ['file' => $backupFile]);

      // Dispatch AFTER event
      $event = new BackupEvent('after', $platform, $backupFile);
      $this->dispatcher->dispatch($event, ProvisionEvents::AFTER_BACKUP);

    } catch (\Exception $e) {
      // Dispatch ROLLBACK event
      $event = new BackupEvent('rollback', $platform, $backupFile ?? '', ['exception' => $e]);
      $this->dispatcher->dispatch($event, ProvisionEvents::ROLLBACK_BACKUP);
      throw $e;
    }

    return $backupFile;
  }

  private function restorePlatform(Context $platform, string $backupFile): void {
    try {
      // Dispatch VALIDATE event
      $event = new RestoreEvent('validate', $platform, $backupFile);
      $this->dispatcher->dispatch($event, ProvisionEvents::VALIDATE_RESTORE);

      // Dispatch BEFORE event
      $event = new RestoreEvent('before', $platform, $backupFile);
      $this->dispatcher->dispatch($event, ProvisionEvents::BEFORE_RESTORE);

      $server = $this->loader->loadServer($platform);
    $paths = $this->pathResolver->resolveServerPaths($server);
    
    if (!str_contains($backupFile, '/')) {
      $backupFile = $paths->backupPath . '/platforms/' . $backupFile;
    }
    if (!is_readable($backupFile)) {
      throw new \RuntimeException('Backup file not readable: ' . $backupFile);
    }

    $docroot = $this->pathResolver->resolveDocroot($platform);
    $tmpDir = $this->makeTempDir('aegir-platform-restore-');
    $this->extractTarArchive($backupFile, $tmpDir);

    // Find the extracted platform directory
    $items = scandir($tmpDir);
    $extractedDir = NULL;
    if (is_array($items)) {
      foreach ($items as $item) {
        if ($item !== '.' && $item !== '..' && is_dir($tmpDir . '/' . $item)) {
          $extractedDir = $tmpDir . '/' . $item;
          break;
        }
      }
    }

    if ($extractedDir === NULL || !is_dir($extractedDir)) {
      $this->filesystem->removeDir($tmpDir);
      throw new \RuntimeException('Backup does not contain platform directory.');
    }

    // Remove old platform and restore from backup
    if (is_dir($docroot)) {
      $this->filesystem->remove($docroot);
    }
    $this->filesystem->ensureDir(dirname($docroot), 0755);
    rename($extractedDir, $docroot);

    $this->filesystem->removeDir($tmpDir);
    $this->logger->info('Restored platform from {file}.', ['file' => $backupFile]);

      // Dispatch AFTER event
      $event = new RestoreEvent('after', $platform, $backupFile);
      $this->dispatcher->dispatch($event, ProvisionEvents::AFTER_RESTORE);

    } catch (\Exception $e) {
      // Dispatch ROLLBACK event
      $event = new RestoreEvent('rollback', $platform, $backupFile, ['exception' => $e]);
      $this->dispatcher->dispatch($event, ProvisionEvents::ROLLBACK_RESTORE);
      throw $e;
    }
  }
}
