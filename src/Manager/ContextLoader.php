<?php

declare(strict_types=1);

namespace Aegir\Provision\Manager;

use Aegir\Provision\Core\Context;
use Aegir\Provision\Core\ContextRepository;

/**
 * Loads related contexts (platform, server, db_server).
 */
final class ContextLoader {
  private ContextRepository $contexts;

  public function __construct(ContextRepository $contexts) {
    $this->contexts = $contexts;
  }

  public function loadPlatform(Context $site): Context {
    $alias = (string) $site->get('platform');
    if ($alias === '') {
      throw new \RuntimeException('Site context missing platform alias.');
    }
    return $this->contexts->load($alias);
  }

  public function loadServer(Context $platform, ?Context $site = NULL): Context {
    $alias = (string) $platform->get('server');
    if ($alias === '' && $site !== NULL) {
      $alias = (string) $site->get('server');
    }
    if ($alias === '') {
      $alias = (string) $platform->get('web_server');
    }
    if ($alias === '') {
      throw new \RuntimeException('Platform context missing server alias.');
    }
    return $this->contexts->load($alias);
  }

  public function loadDbServer(Context $site, Context $server): Context {
    $alias = (string) $site->get('db_server');
    if ($alias !== '') {
      return $this->contexts->load($alias);
    }
    return $server;
  }
}
