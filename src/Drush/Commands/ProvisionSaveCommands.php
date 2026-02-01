<?php

declare(strict_types=1);

namespace Drush\Commands\provision;

use Aegir\Provision\ProvisionManager;
use Drush\Attributes\Argument;
use Drush\Attributes\Command;
use Drush\Attributes\Option;
use Drush\Commands\DrushCommands;
use Symfony\Component\Yaml\Yaml;

/**
 * Provision save commands.
 */
final class ProvisionSaveCommands extends DrushCommands {
  use ProvisionAutowireTrait;

  public function __construct(
    private readonly ProvisionManager $manager
  ) {
    parent::__construct();
  }

  /**
   * Save or update context data.
   */
  #[Command(name: 'provision:save', aliases: ['psave'])]
  #[Argument(name: 'context', description: 'Context name, e.g. @example.com')]
  #[Option(name: 'data', description: 'Context data as JSON or YAML string')]
  #[Option(name: 'data-file', description: 'Context data file (JSON or YAML)')]
  #[Option(name: 'type', description: 'Context type: server, platform, site')]
  #[Option(name: 'delete', description: 'Delete context', type: 'boolean')]
  public function save(
    string $context,
    array $options = ['data' => null, 'data-file' => null, 'type' => null, 'delete' => false]
  ): int {
    try {
      $data = $this->readContextData(
        $options['data'],
        $options['data-file']
      );

      $this->manager->saveContext(
        $context,
        $data,
        $options['type'],
        (bool) $options['delete']
      );

      $this->logger()->success("Context saved: $context");
      return self::EXIT_SUCCESS;
    }
    catch (\Exception $e) {
      $this->logger()->error($e->getMessage());
      return self::EXIT_FAILURE;
    }
  }

  /**
   * @return array<string,mixed>
   */
  private function readContextData(?string $data, ?string $file): array {
    if ($file !== null) {
      if (!is_readable($file)) {
        throw new \RuntimeException('Context data file not readable: ' . $file);
      }
      $data = file_get_contents($file);
      if ($data === false) {
        throw new \RuntimeException('Unable to read context data file: ' . $file);
      }
    }

    if ($data === null || trim($data) === '') {
      return [];
    }

    $data = trim($data);
    if (str_starts_with($data, '{') || str_starts_with($data, '[')) {
      $decoded = json_decode($data, true);
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
