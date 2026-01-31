<?php

declare(strict_types=1);

namespace Aegir\Provision\Drush\Commands;

use Aegir\Provision\ProvisionManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(
    name: 'provision-save',
    description: 'Save or update context data'
)]
final class ProvisionSaveCommand extends Command {
  use ProvisionAutowireTrait;

  public function __construct(
    private readonly ProvisionManager $manager
  ) {
    parent::__construct();
  }

  protected function configure(): void {
    $this->addArgument('context', InputArgument::REQUIRED, 'Context name, e.g. @example.com');
    $this->addOption('data', null, InputOption::VALUE_REQUIRED, 'Context data as JSON or YAML string');
    $this->addOption('data-file', null, InputOption::VALUE_REQUIRED, 'Context data file (JSON or YAML)');
    $this->addOption('type', null, InputOption::VALUE_REQUIRED, 'Context type: server, platform, site');
    $this->addOption('delete', null, InputOption::VALUE_NONE, 'Delete context');
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $data = $this->readContextData(
      $input->getOption('data'),
      $input->getOption('data-file')
    );

    $this->manager->saveContext(
      (string) $input->getArgument('context'),
      $data,
      $input->getOption('type'),
      (bool) $input->getOption('delete')
    );

    return Command::SUCCESS;
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
