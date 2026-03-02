<?php

declare(strict_types=1);

namespace Aegir\Provision\Core\ValueObject;

/**
 * Immutable value object representing a crontab entry configuration.
 *
 * Each cron job is identified by a unique identifier (typically the Drupal
 * root path) so multiple Aegir instances on the same server can coexist.
 */
final readonly class CronJobConfig {

  /**
   * Minimum supported frequency in seconds.
   */
  private const MIN_FREQUENCY = 60;

  /**
   * @param string $identifier Unique key for this cron entry (e.g. Drupal root path)
   * @param string $drupalRoot Absolute path to the Drupal installation
   * @param string $drushPath Absolute path to the drush binary
   * @param string $command Drush command to execute (e.g. 'hosting:dispatch')
   * @param int $frequency Interval in seconds between runs (minimum 60)
   */
  public function __construct(
    public string $identifier,
    public string $drupalRoot,
    public string $drushPath,
    public string $command = 'hosting:dispatch',
    public int $frequency = 300,
  ) {
    if (empty($this->identifier)) {
      throw new \InvalidArgumentException('Identifier cannot be empty');
    }
    if (empty($this->drupalRoot)) {
      throw new \InvalidArgumentException('Drupal root cannot be empty');
    }
    if (empty($this->drushPath)) {
      throw new \InvalidArgumentException('Drush path cannot be empty');
    }
    if (empty($this->command)) {
      throw new \InvalidArgumentException('Command cannot be empty');
    }
    if ($this->frequency < self::MIN_FREQUENCY) {
      throw new \InvalidArgumentException(
        sprintf('Frequency must be at least %d seconds, got %d', self::MIN_FREQUENCY, $this->frequency)
      );
    }
  }

  /**
   * Convert frequency in seconds to a cron schedule expression.
   *
   * Examples:
   *   60   => '* * * * *'        (every minute)
   *   300  => '*​/5 * * * *'      (every 5 minutes)
   *   900  => '*​/15 * * * *'     (every 15 minutes)
   *   3600 => '0 * * * *'        (every hour)
   *
   * @return string Cron schedule expression
   */
  public function toCronExpression(): string {
    $minutes = (int) floor($this->frequency / 60);

    if ($minutes <= 1) {
      return '* * * * *';
    }

    if ($minutes < 60 && 60 % $minutes === 0) {
      return "*/{$minutes} * * * *";
    }

    if ($minutes < 60) {
      // Non-divisor of 60 — use closest divisor.
      $divisors = [1, 2, 3, 4, 5, 6, 10, 12, 15, 20, 30];
      $closest = 1;
      foreach ($divisors as $d) {
        if ($d <= $minutes) {
          $closest = $d;
        }
      }
      return "*/{$closest} * * * *";
    }

    if ($minutes === 60) {
      return '0 * * * *';
    }

    // Longer intervals: run every N hours.
    $hours = (int) floor($minutes / 60);
    if ($hours < 24 && 24 % $hours === 0) {
      return "0 */{$hours} * * *";
    }

    return "0 */{$hours} * * *";
  }

  /**
   * Generate the crontab marker comment for this entry.
   *
   * @return string Marker comment line
   */
  public function toMarkerComment(): string {
    return '# AEGIR ' . $this->identifier;
  }

  /**
   * Generate the full crontab entry (marker + command line).
   *
   * @return string Two-line crontab entry
   */
  public function toCrontabEntry(): string {
    $expression = $this->toCronExpression();
    $marker = $this->toMarkerComment();
    $line = sprintf(
      '%s cd %s && %s %s >> /dev/null 2>&1',
      $expression,
      $this->drupalRoot,
      $this->drushPath,
      $this->command
    );

    return $marker . "\n" . $line;
  }

  /**
   * Create a new instance with a different frequency.
   */
  public function withFrequency(int $frequency): self {
    return new self(
      identifier: $this->identifier,
      drupalRoot: $this->drupalRoot,
      drushPath: $this->drushPath,
      command: $this->command,
      frequency: $frequency,
    );
  }

  /**
   * Create a new instance with a different command.
   */
  public function withCommand(string $command): self {
    return new self(
      identifier: $this->identifier,
      drupalRoot: $this->drupalRoot,
      drushPath: $this->drushPath,
      command: $command,
      frequency: $this->frequency,
    );
  }
}
