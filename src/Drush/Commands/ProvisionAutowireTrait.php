<?php

declare(strict_types=1);

namespace Drush\Commands\provision;

use Aegir\Provision\Drush\ProvisionServiceRegistry;
use Drush\Commands\AutowireTrait;
use Psr\Container\ContainerInterface;

trait ProvisionAutowireTrait {
  use AutowireTrait { AutowireTrait::create as private autowireCreate; }

  public static function create(ContainerInterface $container): self {
    ProvisionServiceRegistry::register($container);
    return self::autowireCreate($container);
  }
}
