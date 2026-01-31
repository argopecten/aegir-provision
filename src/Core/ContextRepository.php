<?php

declare(strict_types=1);

namespace Aegir\Provision\Core;

final class ContextRepository {
  private AliasStore $aliasStore;

  public function __construct(?AliasStore $aliasStore = NULL) {
    $this->aliasStore = $aliasStore ?? new AliasStore();
  }

  public function load(string $contextName): Context {
    $contextName = ltrim($contextName, '@');
    $alias = $this->aliasStore->readAlias($contextName);
    if (!is_array($alias)) {
      throw new \RuntimeException('Context not found: ' . $contextName);
    }

    $provision = $alias['provision'] ?? [];
    if (!is_array($provision)) {
      $provision = [];
    }

    $type = (string) ($provision['context_type'] ?? '');
    unset($provision['context_type']);
    if ($type === '') {
      $type = $this->inferType($alias, $provision);
    }

    $context = new Context($contextName, $type, $provision);

    return $context;
  }

  public function save(Context $context): string {
    $data = $context->toArray();
    $data['context_type'] = $context->type();

    $alias = [
      'provision' => $data,
    ];

    if ($context->type() === ContextType::SITE) {
      $alias['root'] = $context->get('root');
      $alias['uri'] = $context->get('uri');
    }
    elseif ($context->type() === ContextType::PLATFORM) {
      $alias['root'] = $context->get('root');
    }
    elseif ($context->type() === ContextType::SERVER) {
      $alias['host'] = $context->get('remote_host', 'localhost');
      $alias['user'] = $context->get('script_user');
    }

    return $this->aliasStore->writeAlias($context->name(), $alias);
  }

  public function delete(string $contextName): void {
    $this->aliasStore->deleteAlias($contextName);
  }

  private function inferType(array $alias, array $provision): string {
    if (!empty($provision['uri']) || !empty($alias['uri'])) {
      return ContextType::SITE;
    }
    if (!empty($provision['root']) || !empty($alias['root'])) {
      return ContextType::PLATFORM;
    }
    return ContextType::SERVER;
  }
}
