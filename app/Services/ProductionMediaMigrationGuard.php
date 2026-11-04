<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Explicit CLI-only copy into production storage, never a live web/worker mode. */
class ProductionMediaMigrationGuard
{
    public function validate(): void
    {
        if (! app()->runningInConsole() || ! app()->environment('migration')
            || ! config('media.migration_enabled') || config('firebase.push_enabled')
            || config('mail.default') !== 'log'
            || config('database.connections.mysql.host') !== 'mysql') {
            throw new RuntimeException('Production media copy requires isolated migration CLI with delivery disabled.');
        }
        $schema = DB::getDatabaseName();
        if (! preg_match('/\A(?:samy_migration_[0-9]{8}[a-z0-9_]*|samy_production)\z/', $schema)) {
            throw new RuntimeException('Unexpected migration database; backup schema is forbidden.');
        }
        $approvalFile = (string) config('media.migration_approval_file');
        $source = (string) config('media.migration_source_backup');
        if (! is_readable($approvalFile) || filesize($approvalFile) > 16384 || ! is_readable($source)) {
            throw new RuntimeException('Private migration manifest and source backup required.');
        }
        $approval = json_decode(file_get_contents($approvalFile), true, 16, JSON_THROW_ON_ERROR);
        $expected = [
            'purpose' => 'production-media-copy',
            'database' => $schema,
            'public_bucket' => 'samy-public-prod',
            'private_bucket' => 'samy-private-prod',
            'source_sha256' => hash_file('sha256', $source),
        ];
        foreach ($expected as $key => $value) {
            if (($approval[$key] ?? null) !== $value) {
                throw new RuntimeException('Migration manifest does not match source and destination.');
            }
        }
        if (config('filesystems.disks.r2_public.bucket') !== $expected['public_bucket']
            || config('filesystems.disks.r2_private.bucket') !== $expected['private_bucket']) {
            throw new RuntimeException('Production migration bucket mismatch.');
        }
    }
}
