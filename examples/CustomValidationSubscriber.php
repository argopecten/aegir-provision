<?php

declare(strict_types=1);

namespace Example\Provision\EventSubscriber;

use Aegir\Provision\Event\{ProvisionEvents, InstallEvent};
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Example event subscriber for custom validation.
 *
 * This example demonstrates how to:
 * - Validate domain names before installation
 * - Send notifications after successful installation
 * - Integrate with the Aegir Provision event system
 *
 * To use this in your custom extension:
 * 1. Create a Composer package with this subscriber
 * 2. Register it using the EventDispatcher
 * 3. The subscriber will automatically receive events
 *
 * Example registration in your extension's bootstrap code:
 *
 *   $dispatcher = $container->get(EventDispatcherInterface::class);
 *   $dispatcher->addSubscriber(new CustomValidationSubscriber());
 */
class CustomValidationSubscriber implements EventSubscriberInterface
{
    /**
     * Returns an array of event names this subscriber listens to.
     *
     * The array keys are event names and the value is either:
     * - A method name to call (priority defaults to 0)
     * - An array with the method name and priority
     *
     * Priority determines execution order (higher = earlier).
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ProvisionEvents::VALIDATE_INSTALL => ['validateDomain', 10],
            ProvisionEvents::AFTER_INSTALL => ['notifyAdmin', 0],
            ProvisionEvents::BEFORE_DELETE => ['preventProductionDelete', 20],
        ];
    }

    /**
     * Validate that the domain is in an allowed list.
     *
     * This runs during the VALIDATE phase, before any changes are made.
     * Throw an exception to prevent the operation from proceeding.
     */
    public function validateDomain(InstallEvent $event): void
    {
        $site = $event->getSite();
        $uri = $site->get('uri');

        // Custom validation logic
        $allowedDomains = ['example.com', 'test.com', 'dev.local'];
        $domain = parse_url($uri, PHP_URL_HOST) ?? $uri;

        $isAllowed = false;
        foreach ($allowedDomains as $allowedDomain) {
            if (str_ends_with($domain, $allowedDomain)) {
                $isAllowed = true;
                break;
            }
        }

        if (!$isAllowed) {
            throw new \RuntimeException(
                sprintf(
                    'Domain "%s" is not allowed. Must end with: %s',
                    $domain,
                    implode(', ', $allowedDomains)
                )
            );
        }
    }

    /**
     * Send notification after successful installation.
     *
     * This runs during the AFTER phase, after all changes are complete.
     */
    public function notifyAdmin(InstallEvent $event): void
    {
        $site = $event->getSite();
        $platform = $event->getPlatform();

        // Example: Log to syslog
        error_log(sprintf(
            'New site installed: %s on platform %s',
            $site->get('uri'),
            $platform->name()
        ));

        // Example: Send webhook notification
        // $this->sendWebhook([
        //     'event' => 'site.installed',
        //     'site' => $site->get('uri'),
        //     'platform' => $platform->name(),
        //     'timestamp' => time(),
        // ]);

        // Example: Send email
        // mail(
        //     'admin@example.com',
        //     'New Site Installed',
        //     "Site {$site->get('uri')} has been installed."
        // );
    }

    /**
     * Prevent deletion of production sites.
     *
     * This demonstrates stopping an operation before it starts.
     */
    public function preventProductionDelete($event): void
    {
        $context = $event->getContext();

        // Check if this is a production site
        $uri = $context->get('uri');
        if ($uri && !str_contains($uri, 'dev') && !str_contains($uri, 'test')) {
            throw new \RuntimeException(
                sprintf(
                    'Cannot delete production site: %s. Use --force flag if this is intentional.',
                    $uri
                )
            );
        }
    }

    /**
     * Example private method for webhook notifications.
     */
    private function sendWebhook(array $data): void
    {
        // Implementation would use curl or Guzzle to POST to webhook endpoint
        // file_get_contents('https://webhook.example.com/notify', false, stream_context_create([
        //     'http' => [
        //         'method' => 'POST',
        //         'header' => 'Content-Type: application/json',
        //         'content' => json_encode($data),
        //     ],
        // ]));
    }
}
