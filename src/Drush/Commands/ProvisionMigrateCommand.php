<?php

declare(strict_types=1);

namespace Aegir\Provision\Drush\Commands;

use Aegir\Provision\ProvisionManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'provision-migrate',
    description: 'Migrate a site to a different platform'
)]
final class ProvisionMigrateCommand extends Command {
  use ProvisionAutowireTrait;

  public function __construct(
    private readonly ProvisionManager $manager
  ) {
    parent::__construct();
  }

  protected function configure(): void {
    $this->addArgument('context', InputArgument::REQUIRED, 'Site context name');
    $this->addArgument('platform', InputArgument::REQUIRED, 'Target platform alias');
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $this->manager->migrate(
      (string) $input->getArgument('context'),
      (string) $input->getArgument('platform')
    );
    return Command::SUCCESS;
  }
}
