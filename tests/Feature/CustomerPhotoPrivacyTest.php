<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerPhotoPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_photo_requires_permission_and_can_be_replaced_and_removed(): void
    {
        Storage::fake('local');
        config(['media.customer_photo_disk' => 'local']);
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $manager = User::factory()->create(['active' => true]);
        $manager->assignRole('gerente');
        $customer = Customer::create(['name' => 'Ana', 'active' => true]);

        $this->get("/customers/{$customer->id}/photo")->assertRedirect('/login');
        $this->actingAs($manager)->post("/customers/{$customer->id}/photo", ['photo' => UploadedFile::fake()->image('ana.jpg')])->assertRedirect();
        $first = $customer->fresh()->photo_path;
        $this->assertNotNull($first);
        Storage::disk('local')->assertExists($first);
        $response = $this->get("/customers/{$customer->id}/photo")->assertOk();
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));

        $this->post("/customers/{$customer->id}/photo", ['photo' => UploadedFile::fake()->image('new.png')])->assertRedirect();
        Storage::disk('local')->assertMissing($first);
        $second = $customer->fresh()->photo_path;
        $this->delete("/customers/{$customer->id}/photo")->assertRedirect();
        Storage::disk('local')->assertMissing($second);
        $this->get("/customers/{$customer->id}/photo")->assertNotFound();
    }

    public function test_removing_a_customer_photo_keeps_an_object_referenced_by_another_customer(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('samy/customers/legacy.jpg', 'shared image');
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $manager = User::factory()->create(['active' => true]);
        $manager->assignRole('gerente');
        $first = Customer::create(['name' => 'Ana', 'active' => true,
            'photo_disk' => 'local', 'photo_path' => 'samy/customers/legacy.jpg']);
        $second = Customer::create(['name' => 'Bea', 'active' => true,
            'photo_disk' => 'local', 'photo_path' => 'samy/customers/legacy.jpg']);

        $this->actingAs($manager)->delete('/customers/'.$first->id.'/photo')->assertRedirect();
        Storage::disk('local')->assertExists('samy/customers/legacy.jpg');
        $this->delete('/customers/'.$second->id.'/photo')->assertRedirect();
        Storage::disk('local')->assertMissing('samy/customers/legacy.jpg');
    }
}
