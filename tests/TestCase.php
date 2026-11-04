<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        // Windows' bundled SQLite may not include the CONCAT function used by
        // one historical data migration. Keep the test schema portable without
        // changing the production migration or database behavior.
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::connection()->getPdo()->sqliteCreateFunction(
                'CONCAT',
                static fn (...$values): string => implode('', array_map(
                    static fn ($value): string => (string) $value,
                    $values,
                )),
            );
        }

        return $app;
    }
}
