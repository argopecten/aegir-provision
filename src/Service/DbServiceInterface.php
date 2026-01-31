<?php

declare(strict_types=1);

namespace Aegir\Provision\Service;

use Aegir\Provision\Core\Context;

/**
 * Interface for database service implementations (MySQL, PostgreSQL, etc.).
 */
interface DbServiceInterface
{
    /**
     * Ensure a database exists.
     */
    public function ensureDatabase(Context $server, string $dbName): void;

    /**
     * Ensure a database user exists with specified password.
     */
    public function ensureUser(Context $server, string $dbUser, string $dbPass, string $dbHost): void;

    /**
     * Grant all privileges on a database to a user.
     */
    public function grant(Context $server, string $dbName, string $dbUser, string $dbHost): void;

    /**
     * Drop a database if it exists.
     */
    public function dropDatabase(Context $server, string $dbName): void;

    /**
     * Drop a database user if it exists.
     */
    public function dropUser(Context $server, string $dbUser, string $dbHost): void;

    /**
     * Dump a database to a file.
     *
     * @return string Path to the dump file
     */
    public function dump(Context $server, string $dbName, string $targetFile, bool $gzip = false): string;

    /**
     * Import a database dump file.
     */
    public function import(Context $server, string $dbName, string $sourceFile): void;

    /**
     * Test database connection.
     *
     * @throws \RuntimeException If connection fails
     */
    public function testConnection(Context $server): void;
}
