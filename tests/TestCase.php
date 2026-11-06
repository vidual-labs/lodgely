<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Refuse to boot against anything but in-memory SQLite. RefreshDatabase
     * drops every table, and phpunit.xml's `<env>` overrides lose to real
     * environment variables: a container that receives DB_* as real env vars
     * (env_file, Kubernetes, a shell export) would run the suite against the
     * live PostgreSQL database. vigilo lost a database that way; this makes
     * the same mistake fail loudly here instead.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $connection = (string) $app['config']->get('database.default');
        $database = (string) $app['config']->get("database.connections.{$connection}.database");

        if ($connection !== 'sqlite' || $database !== ':memory:') {
            throw new RuntimeException(sprintf(
                'Refusing to run tests against the [%s] database "%s": tests wipe the database they run on. '
                .'Unset the real DB_CONNECTION / DB_DATABASE environment variables (phpunit.xml sets in-memory SQLite).',
                $connection,
                $database,
            ));
        }

        return $app;
    }
}
