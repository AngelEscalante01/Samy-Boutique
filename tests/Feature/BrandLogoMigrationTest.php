<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandLogoMigrationTest extends TestCase
{
    public function test_logo_upload_is_verified_and_never_runs_against_an_unapproved_bucket(): void
    {
        Storage::fake('r2_public');
        config()->set('filesystems.disks.r2_public.bucket', 'samy-public-test');
        $original = public_path('logo-source.png');
        $sha = hash_file('sha256', $original);
        $key = "samy/brand/logo/{$sha}.png";

        $this->artisan('media:migrate-brand-logo')->assertExitCode(0);
        Storage::disk('r2_public')->assertMissing($key);

        $this->artisan('media:migrate-brand-logo', ['--execute' => true])->assertExitCode(0);
        Storage::disk('r2_public')->assertExists($key);
        $this->assertSame($sha, hash('sha256', Storage::disk('r2_public')->get($key)));

        Storage::disk('r2_public')->delete($key);
        config()->set('filesystems.disks.r2_public.bucket', 'another-bucket');
        $this->artisan('media:migrate-brand-logo', ['--execute' => true])->assertExitCode(1);
        Storage::disk('r2_public')->assertMissing($key);
    }
}
