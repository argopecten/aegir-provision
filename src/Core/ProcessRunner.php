<?php

declare(strict_types=1);

namespace Aegir\ProvisionD11\Core;

use Symfony\Component\Process\Process;

final class ProcessRunner {
  /**
   * @param string|resource|null $input
   */
  public function run(array $command, ?string $cwd = NULL, array $env = [], mixed $input = NULL): array {
    $process = new Process($command, $cwd, $env ?: NULL);
    $process->setTimeout(NULL);
    if ($input !== NULL) {
      $process->setInput($input);
    }
    $process->run();

    return [
      'exit_code' => $process->getExitCode(),
      'output' => $process->getOutput(),
      'error' => $process->getErrorOutput(),
    ];
  }
}
