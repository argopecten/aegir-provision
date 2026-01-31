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
    name: 'provision-clone',
    description: 'Clone a site to a new context'
)]
final class ProvisionCloneCommand extends Command {
  use ProvisionAutowireTrait;

  public function __construct(
    private readonly ProvisionManager $manager
  ) {
    parent::__construct();
  }

  protected function configure(): void {
    $this->addArgument('context', InputArgument::REQUIRED, 'Source site context name');
    $this->addArgument('new-site', InputArgument::REQUIRED, 'New site alias');
    $this->addArgument('platform', InputArgument::OPTIONAL, 'Target platform alias');
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $platform = $input->getArgument('platform');
    $this->manager->cloneSite(
      (string) $input->getArgument('context'),
      (string) $input->getArgument('new-site'),
      $platform === null ? '' : (string) $platform
    );
    return Command::SUCCESS;
  }
}
