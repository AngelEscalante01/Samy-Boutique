<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Color;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Size;
use App\Models\User;
use App\Services\ReportDataService;
use App\Services\ReportExportService;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;
use ZipArchive;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->manager = User::factory()->create(['active' => true]);
        $this->manager->assignRole('gerente');
    }

    private function sales(): void
    {
        $category = Category::create(['name' => 'Vestidos', 'active' => true]);
        $size = Size::create(['name' => 'M', 'active' => true]);
        $color = Color::create(['name' => 'Marfil', 'active' => true]);
        $customer = Customer::create(['name' => '=HYPERLINK("https://example.invalid")', 'phone' => '0012345678', 'purchases_count' => 4]);
        $product = Product::create(['sku' => 'REPORTE-001', 'name' => 'Vestido de noche & edición especial', 'category_id' => $category->id, 'gender' => 'dama', 'size_id' => $size->id, 'color_id' => $color->id, 'purchase_price' => 200, 'sale_price' => 500, 'status' => 'disponible', 'created_by' => $this->manager->id]);
        foreach (['2026-10-10 12:00:00', '2026-11-01 12:00:00'] as $date) {
            $sale = Sale::forceCreate(['customer_id' => $customer->id, 'created_by' => $this->manager->id, 'subtotal' => 1000, 'total' => 900, 'discount_total' => 100, 'status' => 'completed', 'created_at' => $date]);
            $sale->items()->create(['product_id' => $product->id, 'quantity' => 2, 'qty' => 2, 'sku' => $product->sku, 'name' => $product->name, 'unit_price' => 500, 'line_total' => 1000, 'final_price' => 1000]);
        }
        Sale::forceCreate(['created_by' => $this->manager->id, 'subtotal' => 2000, 'total' => 2000, 'status' => 'cancelled', 'created_at' => '2026-10-11 12:00:00']);
    }

    public function test_exports_require_the_same_report_permission_as_the_page(): void
    {
        foreach (['pdf', 'xlsx'] as $format) {
            $this->get('/reports/export/'.$format)->assertRedirect('/login');
        }
        $user = User::factory()->create(['active' => true]);
        foreach (['pdf', 'xlsx'] as $format) {
            $this->actingAs($user)->get('/reports/export/'.$format)->assertForbidden();
        }
        $this->actingAs($this->manager)->get('/reports/export/csv')->assertNotFound();
    }

    public function test_excel_matches_the_filtered_screen_and_keeps_amounts_numeric_and_names_as_text(): void
    {
        $this->sales();
        $this->actingAs($this->manager)->get('/reports?from=2026-10-10&to=2026-10-11')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Index')
            ->where('salesSummary.total_sold', fn ($value) => (float) $value === 900.0)
            ->where('salesSummary.profit_total', fn ($value) => (float) $value === 500.0)
            ->where('salesSummary.sales_count', 1)->where('salesSummary.canceled_count', 1)
            ->has('dailySummary', 2)->has('topProducts', 1)->has('topCustomers', 1)->etc());

        $response = $this->get('/reports/export/xlsx?from=2026-10-10&to=2026-10-11')->assertOk()
            ->assertDownload('Samy-reporte-2026-10-10-al-2026-10-11.xlsx');
        $path = $response->baseResponse->getFile()->getPathname();
        try {
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($path));
            foreach (range(1, 3) as $id) {
                $doc = new DOMDocument;
                $this->assertTrue($doc->loadXML($zip->getFromName('xl/worksheets/sheet'.$id.'.xml'), LIBXML_NONET));
                $x = new DOMXPath($doc);
                $x->registerNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                $this->assertSame(0, $x->query('//s:f')->length);
                if ($id === 1) {
                    $this->assertSame('900.00', $x->evaluate('string(//s:c[@r="D6"]/s:v)'));
                    $this->assertSame('500.00', $x->evaluate('string(//s:c[@r="D7"]/s:v)'));
                    $this->assertSame('1', $x->evaluate('string(//s:c[@r="D8"]/s:v)'));
                    $this->assertSame('1', $x->evaluate('string(//s:c[@r="D13"]/s:v)'));
                    $this->assertStringNotContainsString('2026-11-01', $doc->textContent);
                }
                if ($id === 2) $this->assertStringContainsString('Vestido de noche & edición especial', $doc->textContent);
                if ($id === 3) {
                    $this->assertStringContainsString('=HYPERLINK("https://example.invalid")', $doc->textContent);
                    $this->assertStringContainsString('0012345678', $doc->textContent);
                    $this->assertSame('inlineStr', $x->evaluate('string(//s:c[s:is/s:t="0012345678"]/@t)'));
                }
            }
            $this->assertStringContainsString('name="Ventas"', $zip->getFromName('xl/workbook.xml'));
            $this->assertStringContainsString('name="Productos"', $zip->getFromName('xl/workbook.xml'));
            $this->assertStringContainsString('name="Clientes"', $zip->getFromName('xl/workbook.xml'));
            $zip->close();
        } finally {
            @unlink($path);
        }
    }

    public function test_pdf_is_a_downloadable_document_and_empty_ranges_work_in_both_formats(): void
    {
        $this->sales();
        $response = $this->actingAs($this->manager)->get('/reports/export/pdf?from=2026-10-10&to=2026-10-11')
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('attachment;', $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringContainsString('%%EOF', $response->getContent());

        $data = app(ReportDataService::class)->build('2020-01-01', '2020-01-01');
        $this->assertSame(0, $data['salesSummary']['sales_count']);
        $this->assertSame([], $data['topProducts']);
        $this->assertStringStartsWith('%PDF-', app(ReportExportService::class)->pdf($data));
        $path = app(ReportExportService::class)->xlsx($data);
        $this->assertGreaterThan(0, filesize($path));
        unlink($path);
    }

    public function test_invalid_dates_and_reversed_ranges_are_rejected_for_page_and_downloads(): void
    {
        $this->actingAs($this->manager);
        foreach (['/reports', '/reports/export/pdf', '/reports/export/xlsx'] as $url) {
            $this->getJson($url.'?from=2026-10-12&to=2026-10-10')->assertUnprocessable()->assertJsonValidationErrors('to');
            $this->getJson($url.'?from=2026-02-31&to=2026-03-10')->assertUnprocessable()->assertJsonValidationErrors('from');
        }
        $this->assertDatabaseCount('sales', 0);
    }
}
