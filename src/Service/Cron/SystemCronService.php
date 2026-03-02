<?php

declare(strict_types=1);

namespace Aegir\Provision\Service\Cron;

use Aegir\Provision\Core\ProcessRunner;
use Aegir\Provision\Core\ValueObject\CronJobConfig;
use Aegir\Provision\Service\CronServiceInterface;

/**
 * System crontab implementation of the cron service.
 *
 * Manages crontab entries via `crontab -l` and `crontab -` commands.
 * Each managed entry is identified by a marker comment (# AEGIR {identifier})
 * to support idempotent add/update/delete operations and multiple Aegir
 * instances on the same server.
 */
final class SystemCronService implements CronServiceInterface {

  private const MARKER_PREFIX = '# AEGIR ';

  public function __construct(
    private readonly ProcessRunner $runner,
  ) {}

  public function addCron(CronJobConfig $config): void {
    $existing = $this->readCrontab();
    $newEntry = $config->toCrontabEntry();
    $marker = $config->toMarkerComment();

    // Remove existing entry with same identifier (if any), then append new.
    $cleaned = $this->removeEntryByMarker($existing, $marker);
    $updated = $this->appendEntry($cleaned, $newEntry);

    $this->writeCrontab($updated);
  }

  public function deleteCron(string $identifier): void {
    $existing = $this->readCrontab();
    $marker = self::MARKER_PREFIX . $identifier;
    $updated = $this->removeEntryByMarker($existing, $marker);

    // Only write if something actually changed.
    if ($updated !== $existing) {
      $this->writeCrontab($updated);
    }
  }

  public function hasCron(string $identifier): bool {
    $crontab = $this->readCrontab();
    $marker = self::MARKER_PREFIX . $identifier;

    return str_contains($crontab, $marker);
  }

  public function listCron(): array {
    $crontab = $this->readCrontab();
    $entries = [];
    $lines = explode("\n", $crontab);

    for ($i = 0, $count = count($lines); $i < $count; $i++) {
      $line = $lines[$i];
      if (!str_starts_with($line, self::MARKER_PREFIX)) {
        continue;
      }

      $identifier = substr($line, strlen(self::MARKER_PREFIX));
      // The command line follows the marker.
      $commandLine = ($i + 1 < $count) ? $lines[$i + 1] : '';
      $entries[$identifier] = $commandLine;
      $i++; // Skip the command line.
    }

    return $entries;
  }

  public function reload(): void {
    // For system crontab, `crontab -` inherently reloads. Re-read and
    // re-write to ensure consistency (e.g. after manual edits).
    $crontab = $this->readCrontab();
    if ($crontab !== '') {
      $this->writeCrontab($crontab);
    }
  }

  /**
   * Read the current crontab for this user.
   */
  private function readCrontab(): string {
    $result = $this->runner->run(['crontab', '-l']);

    // `crontab -l` returns exit code 1 when no crontab exists.
    if ($result['exit_code'] !== 0) {
      return '';
    }

    return rtrim((string) $result['output']);
  }

  /**
   * Write a new crontab for this user.
   */
  private function writeCrontab(string $contents): void {
    // Ensure crontab ends with a newline (required by some cron implementations).
    $contents = rtrim($contents) . "\n";

    $result = $this->runner->run(['crontab', '-'], input: $contents);

    if ($result['exit_code'] !== 0) {
      throw new \RuntimeException(
        'Failed to write crontab: ' . ($result['error'] ?: 'Unknown error')
      );
    }
  }

  /**
   * Remove a crontab entry identified by its marker comment.
   *
   * Removes the marker line and the immediately following command line.
   */
  private function removeEntryByMarker(string $crontab, string $marker): string {
    if ($crontab === '') {
      return '';
    }

    $lines = explode("\n", $crontab);
    $filtered = [];
    $skipNext = false;

    foreach ($lines as $line) {
      if ($skipNext) {
        $skipNext = false;
        continue;
      }

      if ($line === $marker) {
        $skipNext = true;
        continue;
      }

      $filtered[] = $line;
    }

    return implode("\n", $filtered);
  }

  /**
   * Append a new entry to the crontab contents.
   */
  private function appendEntry(string $crontab, string $entry): string {
    if ($crontab === '') {
      return $entry;
    }

    return rtrim($crontab) . "\n" . $entry;
  }
}
