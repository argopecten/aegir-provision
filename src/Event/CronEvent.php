<?php

declare(strict_types=1);

namespace Aegir\Provision\Event;

use Aegir\Provision\Core\Context;
use Aegir\Provision\Core\ValueObject\CronJobConfig;

/**
 * Event for cron operations (add, delete).
 */
class CronEvent extends ProvisionEvent {

  public function __construct(
    string $operation,
    Context $context,
    private readonly ?CronJobConfig $cronConfig = null,
    array $data = [],
  ) {
    parent::__construct($operation, $context, $data);
  }

  /**
   * Get the cron job configuration (available for add operations).
   */
  public function getCronConfig(): ?CronJobConfig {
    return $this->cronConfig;
  }
}
