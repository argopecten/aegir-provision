<?php

declare(strict_types=1);

namespace Aegir\Provision\Core;

final class Context {
  private string $name;
  private string $type;
  private array $data;

  public function __construct(string $name, string $type, array $data = []) {
    $this->name = ltrim($name, '@');
    $this->type = $type;
    $this->data = $data;
  }

  public function name(): string {
    return $this->name;
  }

  public function alias(): string {
    return '@' . $this->name;
  }

  public function type(): string {
    return $this->type;
  }

  public function all(): array {
    return $this->data;
  }

  public function get(string $key, mixed $default = NULL): mixed {
    return $this->data[$key] ?? $default;
  }

  public function set(string $key, mixed $value): void {
    $this->data[$key] = $value;
  }

  public function toArray(): array {
    return $this->data;
  }
}
