<?php

declare(strict_types=1);

namespace Aegir\Provision\Drush;

use Aegir\Provision\Config\TemplateRenderer;
use Aegir\Provision\Core\ConfigPaths;
use Aegir\Provision\Core\ContextRepository;
use Aegir\Provision\Core\Filesystem;
use Aegir\Provision\Core\ProcessRunner;
use Aegir\Provision\ProvisionManager;
use Aegir\Provision\Service\Cron\SystemCronService;
use Aegir\Provision\Service\Db\MySqlService;
use Aegir\Provision\Service\Http\ApacheService;
use Aegir\Provision\Service\ServiceRegistry;
use Aegir\Provision\Service\Ssl\SslManager;
use League\Container\Container;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class ProvisionServiceRegistry {
  public static function register(ContainerInterface $container): void {
    if (!$container instanceof Container) {
      return;
    }

    if (!$container->has(ContextRepository::class)) {
      $container->addShared(ContextRepository::class);
    }

    if (!$container->has(Filesystem::class)) {
      $container->addShared(Filesystem::class);
    }

    if (!$container->has(ProcessRunner::class)) {
      $container->addShared(ProcessRunner::class);
    }

    if (!$container->has(TemplateRenderer::class)) {
      $container->addShared(TemplateRenderer::class);
    }

    if (!$container->has(EventDispatcherInterface::class)) {
      $container->addShared(EventDispatcherInterface::class, function () {
        return new EventDispatcher();
      });
    }

    if (!$container->has(ServiceRegistry::class)) {
      $container->addShared(ServiceRegistry::class, function () use ($container) {
        $registry = new ServiceRegistry();
        
        // Get dependencies from container
        $filesystem = $container->get(Filesystem::class);
        $runner = $container->get(ProcessRunner::class);
        $templates = $container->get(TemplateRenderer::class);
        
        $paths = new ConfigPaths();
        
        // Register service factories for lazy instantiation
        // Apache HTTP service factory
        $registry->registerFactory('http', 'apache', function () use ($paths, $filesystem, $templates, $runner, $registry) {
          $sslManager = $registry->get('ssl', 'default');
          return new ApacheService($paths, $filesystem, $templates, $runner, $sslManager);
        });
        
        // MySQL database service factory
        $registry->registerFactory('db', 'mysql', function () use ($runner) {
          return new MySqlService($runner);
        });
        
        // SSL manager factory
        $registry->registerFactory('ssl', 'default', function () use ($paths, $filesystem, $runner) {
          return new SslManager($paths, $filesystem, $runner);
        });

        // System cron service factory
        $registry->registerFactory('cron', 'system', function () use ($runner) {
          return new SystemCronService($runner);
        });
        
        return $registry;
      });
    }

    if (!$container->has(ProvisionManager::class)) {
      $container->addShared(ProvisionManager::class)
        ->addArgument(ContextRepository::class)
        ->addArgument(Filesystem::class)
        ->addArgument(ProcessRunner::class)
        ->addArgument(TemplateRenderer::class)
        ->addArgument(LoggerInterface::class)
        ->addArgument(EventDispatcherInterface::class)
        ->addArgument(ServiceRegistry::class);
    }
  }
}
