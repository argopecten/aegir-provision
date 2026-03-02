# Create Value Object

Create a new `final readonly` value object in `src/Core/ValueObject/`.

Value objects represent immutable, validated data. They replace passing raw arrays for structured data like credentials, configurations, or paths.

## User Input Needed

Ask the user:
1. What data does this represent? (e.g., "SSL certificate paths", "cron job schedule")
2. What fields are required? What are optional?
3. Does it need a `toArray()` for template rendering or Context storage?
4. Does it need `with*()` mutation helpers?

## Template

Path: `src/Core/ValueObject/{Name}.php`

```php
<?php

declare(strict_types=1);

namespace Aegir\Provision\Core\ValueObject;

/**
 * Immutable value object representing {description}.
 */
final readonly class {Name}
{
    /**
     * @param string $requiredField Description
     * @param int    $port          Description (default: 80)
     * @param string|null $optional Optional field
     */
    public function __construct(
        public string $requiredField,
        public int $port = 80,
        public ?string $optional = null,
    ) {
        // Validate on construction — fail fast
        if (empty($this->requiredField)) {
            throw new \InvalidArgumentException('{Name}: requiredField cannot be empty.');
        }
        if ($this->port < 1 || $this->port > 65535) {
            throw new \InvalidArgumentException("{Name}: invalid port {$this->port}.");
        }
    }

    /**
     * Convert to array for Context storage or template rendering.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'required_field' => $this->requiredField,
            'port' => $this->port,
            'optional' => $this->optional,
        ];
    }

    /**
     * Return a new instance with a different port.
     */
    public function withPort(int $port): self
    {
        return new self(
            requiredField: $this->requiredField,
            port: $port,
            optional: $this->optional,
        );
    }
}
```

## Existing Value Objects (read before creating)

| File | Represents |
|------|-----------|
| `DatabaseCredentials.php` | DB name, user, pass, host, port, socket |
| `ApacheVhostConfig.php` | serverName, documentRoot, port, SSL config |
| `CronJobConfig.php` | job ID, schedule, command, user |
| `ServerPaths.php` | aegir root, config dir, backup dir |

## Rules

- `final readonly` — never subclassed, never mutated
- Validate all inputs in constructor — throw `\InvalidArgumentException` with clear message
- Properties `public` — no getters needed on readonly classes (PHP 8.1+)
- `with*()` methods return **new instances** — never mutate `$this`
- `toArray()` uses snake_case keys matching Context YAML and template variables
- No `\Drupal::` — standalone package
- No service dependencies in value objects — pure data + validation only
