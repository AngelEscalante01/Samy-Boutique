<?php

namespace Tests\Feature;

use App\Services\ProductionMediaMigrationGuard;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class ProductionMediaMigrationGuardTest extends TestCase
{
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    private function configureMigration(string $schema = 'samy_migration_20261101'): array
    {
        $source = tempnam(sys_get_temp_dir(), 'samy-source-');
        $manifest = tempnam(sys_get_temp_dir(), 'samy-manifest-');
        $this->temporaryFiles = [$source, $manifest];
        file_put_contents($source, 'Synthetic backup fixture');
        file_put_contents($manifest, json_encode([
            'purpose' => 'production-media-copy', 'database' => $schema,
            'public_bucket' => 'samy-public-prod', 'private_bucket' => 'samy-private-prod',
            'source_sha256' => hash_file('sha256', $source),
        ]));
        $this->app['env'] = 'migration';
        config(['media.migration_enabled' => true, 'firebase.push_enabled' => false,
            'mail.default' => 'log', 'database.connections.mysql.host' => 'mysql',
            'filesystems.disks.r2_public.bucket' => 'samy-public-prod',
            'filesystems.disks.r2_private.bucket' => 'samy-private-prod',
            'media.migration_approval_file' => $manifest, 'media.migration_source_backup' => $source]);
        DB::shouldReceive('getDatabaseName')->andReturn($schema);
        return [$source, $manifest];
    }

    public function test_manifest_allows_copy_then_rejects_changed_backup(): void
    {
        [$source] = $this->configureMigration();
        $guard = new ProductionMediaMigrationGuard;
        $guard->validate();
        $this->addToAssertionCount(1);
        file_put_contents($source, 'Changed synthetic backup');
        $this->expectException(RuntimeException::class);
        $guard->validate();
    }

    public function test_running_production_application_cannot_enable_migration(): void
    {
        $this->configureMigration();
        $this->app['env'] = 'production';
        $this->expectException(RuntimeException::class);
        (new ProductionMediaMigrationGuard)->validate();
    }

    public function test_backup_schema_is_forbidden_even_with_matching_manifest(): void
    {
        $this->configureMigration('samy_restore');
        $this->expectException(RuntimeException::class);
        (new ProductionMediaMigrationGuard)->validate();
    }

    public function test_wrong_bucket_is_rejected_even_with_valid_source(): void
    {
        $this->configureMigration();
        config(['filesystems.disks.r2_private.bucket' => 'another-project']);
        $this->expectException(RuntimeException::class);
        (new ProductionMediaMigrationGuard)->validate();
    }
}
