<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Color;
use App\Models\Occasion;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Models\User;
use App\Services\CatalogCodeService;
use App\Services\CatalogImportService;
use App\Services\CatalogXlsxReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use ZipArchive;

class CatalogImportTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->manager = User::factory()->create(['active' => true]);
        $this->manager->assignRole('gerente');
        $category = Category::create(['name' => 'Vestidos', 'active' => true]);
        DB::transaction(fn () => app(CatalogCodeService::class)->setPrefix($category, 'VES'));
        Size::create(['name' => 'M', 'active' => true]);
        Size::create(['name' => 'L', 'active' => true]);
        Color::create(['name' => 'Negro', 'active' => true]);
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        } parent::tearDown();
    }

    private function row(string $group = 'GRUPO-1', string $size = 'M'): array
    {
        return [$group, 'Vestidos', 'Vestido', 'Modelo', 'dama', $size, 'Negro', '250.50', '100', '2', ''];
    }

    private function workbook(array $rows, bool $formula = false, ?array $headers = null): string
    {
        $path = tempnam(sys_get_temp_dir(), 'samy-test-xlsx-');
        $this->files[] = $path;
        copy(resource_path('import-templates/catalog-create-costs.xlsx'), $path);
        $zip = new ZipArchive;
        $zip->open($path);
        $xml = '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach ([$headers ?? CatalogXlsxReader::HEADERS, ...$rows] as $r => $row) {
            $xml .= '<row r="'.($r + 1).'">';
            foreach ($row as $c => $value) {
                $xml .= '<c r="'.chr(65 + $c).($r + 1).'" t="inlineStr">'.($formula && $r === 1 && $c === 7 ? '<f>1+1</f>' : '').'<is><t>'.htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</t></is></c>';
            }
            $xml .= '</row>';
        }
        $zip->addFromString('xl/worksheets/sheet1.xml', $xml.'</sheetData></worksheet>');
        $zip->close();

        return $path;
    }

    private function preview(string $path): array
    {
        return $this->actingAs($this->manager)->post('/catalog-imports/preview', ['mode' => 'create', 'file' => new UploadedFile($path, 'productos.xlsx', null, null, true)], ['Accept' => 'application/json'])->assertOk()->json('data');
    }

    private function referenceCells(ZipArchive $zip): array
    {
        $document = new \DOMDocument;
        $document->loadXML($zip->getFromName('xl/worksheets/sheet4.xml'), LIBXML_NONET);
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $values = [];
        foreach ($xpath->query('/x:worksheet/x:sheetData/x:row/x:c') as $cell) {
            $values[$cell->getAttribute('r')] = $xpath->evaluate('string(x:is/x:t)', $cell);
        }

        return $values;
    }

    public function test_update_mode_uses_stable_ids_and_keeps_inventory_unchanged(): void
    {
        $category = Category::firstOrFail();
        $size = Size::where('name', 'M')->firstOrFail();
        $color = Color::firstOrFail();
        $product = Product::create(['sku' => 'VES-EXIST', 'name' => 'Antes', 'category_id' => $category->id, 'gender' => 'dama', 'size_id' => $size->id, 'color_id' => $color->id, 'purchase_price' => 20, 'sale_price' => 100, 'status' => 'disponible', 'created_by' => $this->manager->id]);
        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => 'VES-EXIST-M', 'size_id' => $size->id, 'color_id' => $color->id, 'stock' => 7, 'sale_price' => 100, 'purchase_price' => 20, 'active' => true]);
        $this->actingAs($this->manager)->get('/catalog-imports/export-update?ids[]='.$product->id)->assertOk();
        $version = $product->updated_at->format('Y-m-d H:i:s');
        $path = $this->workbook([[$product->id, $variant->id, $version, 'Vestidos', 'Después', '', 'dama', 'M', 'Negro', '175.50', '', '']], false, CatalogXlsxReader::UPDATE_HEADERS);
        $batch = $this->post('/catalog-imports/preview', ['mode' => 'update', 'file' => new UploadedFile($path, 'actualizar.xlsx', null, null, true)], ['Accept' => 'application/json'])->assertOk()->json('data');
        $this->assertSame('update', $batch['mode']);
        $this->assertSame('Antes', $product->fresh()->name);
        $id = $batch['groups'][0]['id'];
        $this->postJson('/catalog-imports/'.$batch['id'].'/confirm', ['groups' => [$id]])->assertAccepted();
        app(CatalogImportService::class)->process($id);
        app(CatalogImportService::class)->process($id);
        $this->assertSame('Después', $product->fresh()->name);
        $this->assertSame('175.50', $variant->fresh()->sale_price);
        $this->assertSame('20.00', $variant->fresh()->purchase_price);
        $this->assertSame(7, $variant->fresh()->stock);
        $this->assertDatabaseCount('product_variants', 1);
        $this->assertSame('175.50', $product->fresh()->sale_price);
    }

    public function test_update_mode_rejects_stale_product_version_before_writing(): void
    {
        $category = Category::firstOrFail();
        $size = Size::where('name', 'M')->firstOrFail();
        $color = Color::firstOrFail();
        $product = Product::create(['sku' => 'VES-STALE', 'name' => 'Vigente', 'category_id' => $category->id, 'gender' => 'dama', 'size_id' => $size->id, 'color_id' => $color->id, 'purchase_price' => 20, 'sale_price' => 100, 'status' => 'disponible', 'created_by' => $this->manager->id]);
        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => 'VES-STALE-M', 'size_id' => $size->id, 'color_id' => $color->id, 'stock' => 7, 'sale_price' => 100, 'active' => true]);
        $stale = $product->updated_at->copy()->subDay()->format('Y-m-d H:i:s');
        $path = $this->workbook([[$product->id, $variant->id, $stale, 'Vestidos', 'Nuevo nombre', '', 'dama', 'M', 'Negro', '175.50', '', '']], false, CatalogXlsxReader::UPDATE_HEADERS);
        $batch = $this->actingAs($this->manager)->post('/catalog-imports/preview', ['mode' => 'update', 'file' => new UploadedFile($path, 'stale.xlsx', null, null, true)], ['Accept' => 'application/json'])->assertOk()->json('data');
        $this->assertSame('invalid', $batch['groups'][0]['status']);
        $this->postJson('/catalog-imports/'.$batch['id'].'/confirm', ['groups' => [$batch['groups'][0]['id']]])->assertAccepted();
        $this->assertSame('Vigente', $product->fresh()->name);
        $this->assertSame(7, $variant->fresh()->stock);
    }

    public function test_preview_atomic_groups_and_repeated_confirmation_do_not_duplicate_products(): void
    {
        $bad = $this->row('BAD');
        $bad[1] = 'No existe';
        $path = $this->workbook([$this->row(), $this->row('GRUPO-1', 'L'), $this->row('SIMPLE'), $bad]);
        $batch = $this->preview($path);
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('product_variants', 0);
        $this->assertDatabaseCount('catalog_codes', 0);
        $this->assertSame(2, $batch['counts']['pending']);
        $this->assertSame(1, $batch['counts']['invalid']);
        $again = $this->preview($path);
        $this->assertSame($batch['id'], $again['id']);
        $ids = array_column($batch['groups'], 'id');
        $this->postJson('/catalog-imports/'.$batch['id'].'/confirm', ['groups' => $ids])->assertAccepted();
        foreach ($ids as $id) {
            app(CatalogImportService::class)->process($id);
            app(CatalogImportService::class)->process($id);
        }
        $this->postJson('/catalog-imports/'.$batch['id'].'/confirm', ['groups' => $ids])->assertAccepted();
        $this->assertDatabaseCount('products', 2);
        $this->assertDatabaseCount('product_variants', 3);
        $this->assertDatabaseCount('catalog_codes', 5);
        $this->assertSame(6, (int) ProductVariant::sum('stock'));
        $this->assertDatabaseHas('catalog_import_groups', ['batch_id' => $batch['id'], 'status' => 'invalid', 'product_id' => null]);
    }

    public function test_confirm_revalidates_catalog_and_job_revalidates_permissions(): void
    {
        $batch = $this->preview($this->workbook([$this->row()]));
        $id = $batch['groups'][0]['id'];
        Color::query()->update(['active' => false]);
        $this->postJson('/catalog-imports/'.$batch['id'].'/confirm', ['groups' => [$id]])->assertAccepted();
        $this->assertDatabaseHas('catalog_import_groups', ['id' => $id, 'status' => 'invalid']);
        Color::query()->update(['active' => true]);
        $this->postJson('/catalog-imports/'.$batch['id'].'/confirm', ['groups' => [$id]])->assertAccepted();
        $this->manager->update(['active' => false]);
        app(CatalogImportService::class)->process($id);
        $this->assertDatabaseHas('catalog_import_groups', ['id' => $id, 'status' => 'failed']);
        $this->assertDatabaseCount('products', 0);
    }

    public function test_owner_and_cost_permissions_are_enforced(): void
    {
        $batch = $this->preview($this->workbook([$this->row()]));
        $user = User::factory()->create(['active' => true]);
        $user->givePermissionTo('products.create');
        $this->actingAs($user)->getJson('/catalog-imports/'.$batch['id'])->assertNotFound();
        $row = $this->row('COST');
        $path = $this->workbook([$row]);
        $preview = $this->post('/catalog-imports/preview', ['mode' => 'create', 'file' => new UploadedFile($path, 'cost.xlsx', null, null, true)], ['Accept' => 'application/json'])->assertOk()->json('data');
        $this->assertSame('invalid', $preview['groups'][0]['status']);
        $this->assertArrayNotHasKey('costo', $preview['groups'][0]['rows'][0]);
        $this->assertStringContainsString('costos', implode(' ', $preview['groups'][0]['errors']));
    }

    public function test_formula_is_rejected_before_batch_or_inventory_is_created(): void
    {
        $path = $this->workbook([$this->row()], true);
        $this->actingAs($this->manager)->post('/catalog-imports/preview', ['mode' => 'create', 'file' => new UploadedFile($path, 'formula.xlsx', null, null, true)], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertDatabaseCount('catalog_import_batches', 0);
        $this->assertDatabaseCount('products', 0);
    }

    public function test_duplicate_combinations_and_inconsistent_groups_are_invalid(): void
    {
        $row = $this->row();
        $row[2] = 'Otro nombre';
        $batch = $this->preview($this->workbook([$this->row(), $row]));
        $this->assertSame('invalid', $batch['groups'][0]['status']);
        $errors = implode(' ', $batch['groups'][0]['errors']);
        $this->assertStringContainsString('coincidir', $errors);
        $this->assertStringContainsString('repetida', $errors);
    }

    public function test_template_contains_current_catalogs_and_api_is_protected(): void
    {
        Category::create(['name' => 'Blusas', 'active' => true]);
        Size::create(['name' => 'Única', 'active' => true]);
        Size::create(['name' => 'No usar', 'active' => false]);
        Color::create(['name' => 'Beige', 'active' => true]);
        Occasion::create(['name' => 'Casual', 'normalized_name' => 'casual', 'sort_order' => 0, 'active' => true]);
        $response = $this->actingAs($this->manager)->get('/catalog-imports/template')->assertOk();
        $zip = new ZipArchive;
        $zip->open($response->baseResponse->getFile()->getPathname());
        $references = $this->referenceCells($zip);
        $this->assertSame('Vestidos', $references['A2']);
        $this->assertSame('Blusas', $references['F2']);
        $this->assertSame(['L', 'M', 'Única'], [$references['B2'], $references['B3'], $references['B4']]);
        $this->assertNotContains('No usar', $references);
        $this->assertSame(['Beige', 'Negro'], [$references['C2'], $references['C3']]);
        $this->assertSame('Casual', $references['D2']);
        $this->assertStringContainsString('CatalogCategorias', $zip->getFromName('xl/workbook.xml'));
        $this->assertStringContainsString("'Referencias'!\$B\$2:\$B\$4", $zip->getFromName('xl/workbook.xml'));
        $this->assertStringContainsString('sqref="A2:A1001"', $zip->getFromName('xl/worksheets/sheet1.xml'));
        $this->assertStringContainsString('sqref="E2:E1001"', $zip->getFromName('xl/worksheets/sheet1.xml'));
        foreach (['J', 'K', 'L'] as $column) {
            $this->assertStringContainsString('sqref="'.$column.'2:'.$column.'1001"', $zip->getFromName('xl/worksheets/sheet1.xml'));
        }
        foreach (['ocasion_1', 'ocasion_2', 'ocasion_3'] as $header) {
            $this->assertStringContainsString($header, $zip->getFromName('xl/worksheets/sheet1.xml'));
        }
        $this->assertStringNotContainsString('grupo_producto', $zip->getFromName('xl/worksheets/sheet1.xml'));
        $this->assertStringContainsString('Vestido midi floral', $zip->getFromName('xl/worksheets/sheet3.xml'));
        $this->assertStringContainsString('Vestido liso de fiesta', $zip->getFromName('xl/worksheets/sheet3.xml'));
        $this->assertStringNotContainsString('Smoke', $zip->getFromName('xl/worksheets/sheet3.xml'));
        $examples = simplexml_load_string($zip->getFromName('xl/worksheets/sheet3.xml'));
        $numbers = array_map(fn ($row) => (string) $row['r'], $examples->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]'));
        $this->assertSame($numbers, array_values(array_unique($numbers)));
        $zip->close();
        $update = $this->actingAs($this->manager)->get('/catalog-imports/export-update')->assertOk();
        $zip->open($update->baseResponse->getFile()->getPathname());
        $updateReferences = $this->referenceCells($zip);
        $this->assertSame('Blusas', $updateReferences['A2']);
        $this->assertSame('Vestidos', $updateReferences['A3']);
        foreach (['L', 'M', 'N'] as $column) {
            $this->assertStringContainsString('sqref="'.$column.'2:'.$column.'1001"', $zip->getFromName('xl/worksheets/sheet1.xml'));
        }
        $zip->close();
        $user = User::factory()->create(['active' => true]);
        $this->actingAs($user)->getJson('/api/catalog-imports/template')->assertForbidden();
    }

    public function test_three_occasion_columns_assign_all_styles_and_old_column_remains_supported(): void
    {
        foreach (['XV Años', 'Vestido de noche', 'Bodas'] as $index => $name) {
            Occasion::create(['name' => $name, 'normalized_name' => Occasion::normalizeName($name), 'sort_order' => $index, 'active' => true]);
        }
        $row = $this->row();
        array_splice($row, 10, 1, ['XV Años', 'Vestido de noche', 'Bodas']);
        $batch = $this->preview($this->workbook([$row]));
        $this->assertSame('pending', $batch['groups'][0]['status']);
        $id = $batch['groups'][0]['id'];
        $this->postJson('/catalog-imports/'.$batch['id'].'/confirm', ['groups' => [$id]])->assertAccepted();
        app(CatalogImportService::class)->process($id);
        $this->assertEqualsCanonicalizing(['XV Años', 'Vestido de noche', 'Bodas'], Product::firstOrFail()->occasions->pluck('name')->all());

        $oldHeaders = array_values(array_diff(CatalogXlsxReader::HEADERS, ['ocasion_1', 'ocasion_2', 'ocasion_3']));
        $oldHeaders[] = 'ocasiones';
        $oldRow = $this->row('OTRO');
        $oldRow[10] = 'XV Años, Bodas';
        $legacy = $this->preview($this->workbook([$oldRow], false, $oldHeaders));
        $this->assertSame('pending', $legacy['groups'][0]['status']);
    }

    public function test_update_excel_keeps_occasions_beyond_its_three_dropdowns(): void
    {
        $names = ['XV Años', 'Vestido de noche', 'Bodas', 'Graduaciones', 'Fiestas'];
        $occasions = collect($names)->map(fn ($name, $index) => Occasion::create([
            'name' => $name, 'normalized_name' => Occasion::normalizeName($name), 'sort_order' => $index, 'active' => true,
        ]));
        $category = Category::firstOrFail();
        $size = Size::where('name', 'M')->firstOrFail();
        $color = Color::firstOrFail();
        $product = Product::create(['sku' => 'VES-MULTI', 'name' => 'Vestido multiocasión', 'category_id' => $category->id, 'gender' => 'dama', 'size_id' => $size->id, 'color_id' => $color->id, 'purchase_price' => 20, 'sale_price' => 100, 'status' => 'disponible', 'created_by' => $this->manager->id]);
        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => 'VES-MULTI-M', 'size_id' => $size->id, 'color_id' => $color->id, 'stock' => 2, 'sale_price' => 100, 'active' => true]);
        $product->occasions()->sync($occasions->take(4)->pluck('id')->all());
        $export = $this->actingAs($this->manager)->get('/catalog-imports/export-update?ids[]='.$product->id)->assertOk();
        $zip = new ZipArchive;
        $zip->open($export->baseResponse->getFile()->getPathname());
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('Bodas', $sheet);
        $this->assertStringNotContainsString('Graduaciones', $sheet);
        $this->assertStringContainsString('ocasion_3', $sheet);
        $zip->close();

        $ordered = $occasions->take(4)->sortBy('id')->values();
        $path = $this->workbook([[
            $product->id, $variant->id, $product->updated_at->format('Y-m-d H:i:s'),
            'Vestidos', $product->name, '', 'dama', 'M', 'Negro', '100', '',
            $ordered[0]->name, $ordered[1]->name, $occasions[4]->name,
        ]], false, CatalogXlsxReader::UPDATE_HEADERS);
        $batch = $this->post('/catalog-imports/preview', ['mode' => 'update', 'file' => new UploadedFile($path, 'actualizar.xlsx', null, null, true)], ['Accept' => 'application/json'])->assertOk()->json('data');
        $this->assertSame('pending', $batch['groups'][0]['status']);
        $id = $batch['groups'][0]['id'];
        $this->postJson('/catalog-imports/'.$batch['id'].'/confirm', ['groups' => [$id]])->assertAccepted();
        app(CatalogImportService::class)->process($id);
        $this->assertEqualsCanonicalizing([$ordered[0]->name, $ordered[1]->name, 'Fiestas', $ordered[3]->name], $product->fresh()->occasions->pluck('name')->all());
    }

    public function test_simple_template_infers_variants_without_group_codes(): void
    {
        $headers = array_values(array_diff(CatalogXlsxReader::HEADERS, ['grupo_producto']));
        $path = $this->workbook([
            ['Vestidos', 'Modelo azul', 'Descripción', 'dama', 'M', 'Negro', '250.50', '100', '2', ''],
            ['', '', '', '', 'L', 'Negro', '250.50', '100', '1', ''],
            ['Vestidos', 'Otro modelo', '', 'dama', 'M', 'Negro', '300', '120', '1', ''],
        ], false, $headers);
        $batch = $this->preview($path);
        $this->assertSame(2, $batch['counts']['pending']);
        $this->assertSame('Modelo azul · fila 2', $batch['groups'][0]['key']);
        $this->assertSame('Modelo azul', $batch['groups'][0]['rows'][1]['nombre']);
        $this->assertSame('Vestidos', $batch['groups'][0]['rows'][1]['categoria']);
        $this->assertCount(2, $batch['groups'][0]['rows']);
        $this->assertCount(1, $batch['groups'][1]['rows']);
        $ids = array_column($batch['groups'], 'id');
        $this->postJson('/catalog-imports/'.$batch['id'].'/confirm', ['groups' => $ids])->assertAccepted();
        foreach ($ids as $id) app(CatalogImportService::class)->process($id);
        $this->assertDatabaseCount('products', 2);
        $this->assertDatabaseCount('product_variants', 3);
    }

    public function test_macros_and_xml_entities_are_rejected(): void
    {
        foreach (['macro', 'entity'] as $kind) {
            $path = $this->workbook([$this->row()]);
            $zip = new ZipArchive;
            $zip->open($path);
            if ($kind === 'macro') {
                $zip->addFromString('xl/vbaProject.bin', 'not allowed');
            } else {
                $zip->addFromString('xl/worksheets/sheet1.xml', '<!DOCTYPE worksheet [<!ENTITY external SYSTEM "file:///etc/passwd">]><worksheet/>');
            }
            $zip->close();
            try {
                app(CatalogXlsxReader::class)->read($path);
                $this->fail('Se aceptó contenido no permitido.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('file', $exception->errors());
            }
        }
        $this->assertDatabaseCount('products', 0);
    }

    public function test_failure_after_variant_creation_rolls_back_the_entire_group_and_codes(): void
    {
        $batch = $this->preview($this->workbook([$this->row(), $this->row('GRUPO-1', 'L')]));
        $id = $batch['groups'][0]['id'];
        $this->postJson('/catalog-imports/'.$batch['id'].'/confirm', ['groups' => [$id]])->assertAccepted();
        $this->mock(\App\Services\InventoryService::class)->shouldReceive('updateSoldOutAt')->once()
            ->andThrow(ValidationException::withMessages(['stock' => 'Fallo controlado al finalizar el grupo.']));
        app(CatalogImportService::class)->process($id);
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('product_variants', 0);
        $this->assertDatabaseCount('catalog_codes', 0);
        $this->assertDatabaseHas('catalog_code_prefixes', ['prefix' => 'VES', 'last_number' => 0]);
        $this->assertDatabaseHas('catalog_import_groups', ['id' => $id, 'status' => 'failed']);
    }
}
