<?php

declare(strict_types=1);

namespace Aegir\Provision\Manager;

use Aegir\Provision\Core\Context;
use Aegir\Provision\Core\ContextRepository;
use Aegir\Provision\Core\Filesystem;
use Aegir\Provision\Event\ProvisionEvent;
use Aegir\Provision\Event\ProvisionEvents;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Manages lock/unlock operations.
 *
 * Supports all context types: sites, platforms, and servers.
 */
final class LockManager {
  private ContextRepository $contexts;
  private Filesystem $filesystem;
  private LoggerInterface $logger;
  private EventDispatcherInterface $dispatcher;
  private ContextLoader $loader;
  private PathResolver $pathResolver;

  public function __construct(
    ContextRepository $contexts,
    Filesystem $filesystem,
    LoggerInterface $logger,
    EventDispatcherInterface $dispatcher,
    ContextLoader $loader,
    PathResolver $pathResolver
  ) {
    $this->contexts = $contexts;
    $this->filesystem = $filesystem;
    $this->logger = $logger;
    $this->dispatcher = $dispatcher;
    $this->loader = $loader;
    $this->pathResolver = $pathResolver;
  }

  public function lock(string $contextName): void {
    $context = $this->contexts->load($contextName);

    // Dispatch VALIDATE event
    $event = new ProvisionEvent('validate', $context);
    $this->dispatcher->dispatch($event, ProvisionEvents::VALIDATE_LOCK);

    // Dispatch BEFORE event
    $event = new ProvisionEvent('before', $context);
    $this->dispatcher->dispatch($event, ProvisionEvents::BEFORE_LOCK);

    $path = $this->pathResolver->lockPath($context, $this->loader);
    $this->filesystem->writeFile($path, "locked\n", 0644);
    $this->logger->info('Locked {context}.', ['context' => $contextName]);

    // Dispatch AFTER event
    $event = new ProvisionEvent('after', $context);
    $this->dispatcher->dispatch($event, ProvisionEvents::AFTER_LOCK);
  }

  public function unlock(string $contextName): void {
    $context = $this->contexts->load($contextName);

    // Dispatch VALIDATE event
    $event = new ProvisionEvent('validate', $context);
    $this->dispatcher->dispatch($event, ProvisionEvents::VALIDATE_UNLOCK);

    // Dispatch BEFORE event
    $event = new ProvisionEvent('before', $context);
    $this->dispatcher->dispatch($event, ProvisionEvents::BEFORE_UNLOCK);

    $path = $this->pathResolver->lockPath($context, $this->loader);
    $this->filesystem->remove($path);
    $this->logger->info('Unlocked {context}.', ['context' => $contextName]);

    // Dispatch AFTER event
    $event = new ProvisionEvent('after', $context);
    $this->dispatcher->dispatch($event, ProvisionEvents::AFTER_UNLOCK);
  }
}
