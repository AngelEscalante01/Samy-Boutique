<?php

namespace Tests\Feature;

use App\Models\CatalogSlide;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CatalogSlideTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_active_slides_are_public_and_unsafe_destinations_are_rejected(): void
    {
        Storage::fake('public');
        config(['media.carousel_disk' => 'public']);
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $manager = User::factory()->create(['active' => true]);
        $manager->assignRole('gerente');
        $this->actingAs($manager)->post('/catalog-slides', [
            'title' => 'Nueva temporada', 'sort_order' => 1, 'active' => true,
            'destination' => 'javascript:alert(1)',
            'desktop_image' => UploadedFile::fake()->image('banner.jpg'),
        ])->assertSessionHasErrors('destination');
        $this->assertDatabaseCount('catalog_slides', 0);

        $this->post('/catalog-slides', [
            'title' => 'Nueva temporada', 'sort_order' => 1, 'active' => true,
            'destination' => '/catalogo',
            'desktop_image' => UploadedFile::fake()->image('banner.jpg'),
            'mobile_image' => UploadedFile::fake()->image('banner-movil.jpg'),
        ])->assertRedirect();
        $slide = CatalogSlide::firstOrFail();
        Storage::disk('public')->assertExists($slide->desktop_path);
        Storage::disk('public')->assertExists($slide->mobile_path);
        $this->get('/catalogo')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Public/Catalog/Index')->has('slides', 1)->where('slides.0.title', 'Nueva temporada')->where('slides.0.mobile_url', $slide->mobile_url)->etc());
        $this->get('/catalog-slides')->assertOk()->assertInertia(fn (Assert $page) => $page->component('CatalogSlides/Index')->where('slides.0.has_mobile_image', true)->etc());

        $this->put('/catalog-slides/'.$slide->id, [
            'title' => $slide->title, 'sort_order' => 1, 'active' => false,
            'destination' => '/catalogo',
        ])->assertRedirect();
        $this->get('/catalogo')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Public/Catalog/Index')->has('slides', 0)->etc());
    }

    public function test_deleting_a_slide_keeps_an_object_used_by_another_slide(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('samy/carousel/shared.jpg', 'shared image');
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $manager = User::factory()->create(['active' => true]);
        $manager->assignRole('gerente');
        $first = CatalogSlide::create(['title' => 'Primera', 'sort_order' => 1, 'active' => true, 'generation' => (string) \Illuminate\Support\Str::uuid(),
            'desktop_disk' => 'public', 'desktop_path' => 'samy/carousel/shared.jpg']);
        $second = CatalogSlide::create(['title' => 'Segunda', 'sort_order' => 2, 'active' => true, 'generation' => (string) \Illuminate\Support\Str::uuid(),
            'desktop_disk' => 'public', 'desktop_path' => 'samy/carousel/shared.jpg']);

        $this->actingAs($manager)->delete('/catalog-slides/'.$first->id)->assertRedirect();
        Storage::disk('public')->assertExists('samy/carousel/shared.jpg');
        $this->delete('/catalog-slides/'.$second->id)->assertRedirect();
        Storage::disk('public')->assertMissing('samy/carousel/shared.jpg');
    }

    public function test_manager_can_edit_a_slide_and_replace_its_image_from_a_form(): void
    {
        Storage::fake('public');
        config(['media.carousel_disk' => 'public']);
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $manager = User::factory()->create(['active' => true]);
        $manager->assignRole('gerente');

        $this->actingAs($manager)->post('/catalog-slides', [
            'title' => 'Antes', 'sort_order' => 0, 'active' => true,
            'desktop_image' => UploadedFile::fake()->image('antes.png'),
        ])->assertRedirect();
        $slide = CatalogSlide::firstOrFail();
        $oldPath = $slide->desktop_path;

        $this->post('/catalog-slides/'.$slide->id, [
            '_method' => 'put', 'title' => 'Después', 'subtitle' => 'Nueva colección',
            'sort_order' => 2, 'active' => true, 'destination' => '/catalogo',
            'desktop_image' => UploadedFile::fake()->image('despues.png'),
            'mobile_image' => UploadedFile::fake()->image('despues-movil.png'),
        ])->assertRedirect();

        $slide->refresh();
        $this->assertSame('Después', $slide->title);
        $this->assertSame(2, $slide->sort_order);
        $this->assertNotSame($oldPath, $slide->desktop_path);
        Storage::disk('public')->assertExists($slide->desktop_path);
        Storage::disk('public')->assertExists($slide->mobile_path);
        Storage::disk('public')->assertMissing($oldPath);
        $this->get('/catalog-slides')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('CatalogSlides/Index')->where('slides.0.title', 'Después')->where('slides.0.has_mobile_image', true)->etc());
    }
}
