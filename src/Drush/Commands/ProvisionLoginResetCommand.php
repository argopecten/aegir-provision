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
    name: 'provision-login-reset',
    description: 'Reset admin login for a site'
)]
final class ProvisionLoginResetCommand extends Command {
  use ProvisionAutowireTrait;

  public function __construct(
    private readonly ProvisionManager $manager
  ) {
    parent::__construct();
  }

  protected function configure(): void {
    $this->addArgument('context', InputArgument::REQUIRED, 'Site context name');
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $this->manager->loginReset((string) $input->getArgument('context'));
    return Command::SUCCESS;
  }
}
