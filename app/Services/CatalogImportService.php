<?php

namespace App\Services;

use App\Jobs\ImportCatalogGroup;
use App\Models\Category;
use App\Models\Color;
use App\Models\Occasion;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CatalogImportService
{
    public function validateUpdate(array $rows, User $user): array
    {
        $errors = [];
        $first = $rows[0];
        $productId = (int) ($first['producto_id'] ?? 0);
        $product = Product::query()->with('variants')->find($productId);
        if (! $product || $productId < 1 || collect($rows)->contains(fn ($row) => (int) ($row['producto_id'] ?? 0) !== $productId)) {
            $errors[] = 'Todas las filas deben indicar el mismo producto_id existente.';
        }
        if ($product && (string) $first['version_producto'] !== $product->updated_at?->format('Y-m-d H:i:s')) {
            $errors[] = 'El producto cambió desde la exportación. Descarga una plantilla nueva.';
        }
        $changes = [];
        foreach (['nombre' => 'name', 'descripcion' => 'description', 'genero' => 'gender'] as $column => $field) {
            $values = collect($rows)->pluck($column)->unique()->values();
            if ($values->count() !== 1) $errors[] = "{$column} debe coincidir en todas las filas.";
            if ($first[$column] !== '') $changes[$field] = $first[$column];
        }
        if (isset($changes['name']) && mb_strlen($changes['name']) > 180) $errors[] = 'Nombre demasiado largo.';
        if (isset($changes['gender']) && ! in_array($changes['gender'], ['dama', 'caballero', 'unisex'], true)) $errors[] = 'Género inválido.';
        if (collect($rows)->pluck('categoria')->unique()->count() !== 1) $errors[] = 'Categoría distinta dentro del producto.';
        if ($first['categoria'] !== '') {
            $category = Category::query()->where('name', $first['categoria'])->where('active', true)->first();
            if (! $category) $errors[] = 'Categoría desconocida o inactiva.';
            else $changes['category_id'] = $category->id;
        }
        $targetCategory = Category::query()->find($changes['category_id'] ?? $product?->category_id);
        if (collect($rows)->contains(fn ($row) => $row['variante_id'] === '') && ! $targetCategory?->sku_prefix) {
            $errors[] = 'La categoría necesita prefijo SKU para agregar variantes.';
        }
        $occasionIds = null;
        if (collect($rows)->pluck('ocasiones')->unique()->count() !== 1) $errors[] = 'Ocasiones distintas dentro del producto.';
        if ($first['ocasiones'] !== '') {
            $occasionIds = [];
            foreach (preg_split('/[,;]+/u', $first['ocasiones']) as $name) {
                $name = trim($name);
                if ($name === '') continue;
                $occasion = Occasion::query()->where('normalized_name', Occasion::normalizeName($name))->where('active', true)->first();
                if (! $occasion) $errors[] = "Ocasión desconocida: {$name}.";
                else $occasionIds[] = $occasion->id;
            }
            if ($first['_occasion_columns'] ?? false) {
                // The spreadsheet exposes three slots. Keep associations managed in the product form beyond them.
                $extraIds = $product
                    ? $product->occasions()->orderBy('occasions.id')->pluck('occasions.id')->slice(3)->all()
                    : [];
                $occasionIds = [...$occasionIds, ...$extraIds];
            }
            $occasionIds = array_values(array_unique($occasionIds));
        }
        $lines = [];
        $seenIds = [];
        $combinations = [];
        foreach ($rows as $row) {
            $line = 'Fila '.$row['_row'].': ';
            if ((string) $row['version_producto'] !== (string) $first['version_producto']) $errors[] = $line.'Versión de producto diferente.';
            $variantId = $row['variante_id'] !== '' && ctype_digit((string) $row['variante_id']) ? (int) $row['variante_id'] : null;
            $existing = $variantId ? $product?->variants->firstWhere('id', $variantId) : null;
            if ($row['variante_id'] !== '' && ! $existing) $errors[] = $line.'variante_id no pertenece al producto.';
            if ($variantId && isset($seenIds[$variantId])) $errors[] = $line.'variante_id duplicado.';
            if ($variantId) $seenIds[$variantId] = true;
            $sizeName = $row['talla'] !== '' ? $row['talla'] : $existing?->size?->name;
            $colorName = $row['color'] !== '' ? $row['color'] : $existing?->color?->name;
            $size = Size::query()->where('name', $sizeName)->where('active', true)->first();
            $color = Color::query()->where('name', $colorName)->where('active', true)->first();
            if (! $size || ! $color) $errors[] = $line.'Talla o color desconocido/inactivo.';
            if ($size && $color) {
                $key = $size->id.'-'.$color->id;
                if (isset($combinations[$key])) $errors[] = $line.'Combinación talla/color duplicada.';
                $combinations[$key] = true;
            }
            $values = [];
            if ($size) $values['size_id'] = $size->id;
            if ($color) $values['color_id'] = $color->id;
            foreach (['precio_venta' => 'sale_price', 'costo' => 'purchase_price'] as $column => $field) {
                if ($row[$column] === '') continue;
                if (! preg_match('/^\d{1,7}(\.\d{1,2})?$/', $row[$column])) $errors[] = $line."{$column} inválido.";
                elseif ($column === 'costo' && ! $user->can('products.view_purchase_price')) $errors[] = $line.'No tienes permiso para modificar costos.';
                else $values[$field] = $row[$column];
            }
            if (! $existing && ! isset($values['sale_price'])) $errors[] = $line.'Una variante nueva requiere precio_venta.';
            $lines[] = ['id' => $variantId, 'values' => $values, 'action' => $variantId ? 'actualizar' : 'variante nueva'];
        }
        if ($product) {
            foreach ($product->variants as $variant) {
                if (! isset($seenIds[$variant->id])) $errors[] = "Falta la variante {$variant->sku}; exporta todas las variantes del producto.";
            }
        }

        return ['errors' => array_values(array_unique($errors)), 'product_id' => $productId, 'product_changes' => $changes, 'variants' => $lines, 'occasion_ids' => $occasionIds];
    }

    public function validate(array $rows, User $user): array
    {
        $errors = [];
        $variants = [];
        $first = $rows[0];
        $seen = [];
        foreach ($rows as $row) {
            $line = 'Fila '.$row['_row'].': ';
            foreach (['grupo_producto' => 100, 'nombre' => 180, 'categoria' => 80, 'genero' => 12, 'talla' => 80, 'color' => 80] as $key => $max) {
                if ($row[$key] === '' || mb_strlen($row[$key]) > $max) {
                    $errors[] = $line."{$key} es obligatorio (máximo {$max} caracteres).";
                }
            }
            foreach (['categoria', 'nombre', 'descripcion', 'genero', 'ocasiones'] as $key) {
                if ($row[$key] !== $first[$key]) {
                    $errors[] = $line."{$key} debe coincidir en todo el grupo.";
                }
            }
            if (! in_array($row['genero'], ['dama', 'caballero', 'unisex'], true)) {
                $errors[] = $line.'Género inválido.';
            }
            foreach (['precio_venta', 'costo'] as $key) {
                if ($key === 'costo' && $row[$key] === '') {
                    continue;
                }
                if (! preg_match('/^\d{1,7}(\.\d{1,2})?$/', $row[$key])) {
                    $errors[] = $line."{$key}: usa importe positivo o cero con máximo dos decimales y punto decimal.";
                }
            }
            if ($row['costo'] !== '' && ! $user->can('products.view_purchase_price')) {
                $errors[] = $line.'No tienes permiso para importar costos.';
            }
            if (! preg_match('/^\d{1,6}$/', $row['existencia_inicial'])) {
                $errors[] = $line.'La existencia inicial debe ser un entero entre 0 y 999999.';
            }
            $size = Size::where('name', $row['talla'])->where('active', true)->first();
            $color = Color::where('name', $row['color'])->where('active', true)->first();
            if (! $size) {
                $errors[] = $line.'Talla desconocida o inactiva; selecciónala del catálogo.';
            }
            if (! $color) {
                $errors[] = $line.'Color desconocido o inactivo; selecciónalo del catálogo.';
            }
            if ($size && $color) {
                $key = $size->id.'-'.$color->id;
                if (isset($seen[$key])) {
                    $errors[] = $line.'Combinación talla/color repetida en el grupo.';
                }
                $seen[$key] = true;
                $variants[] = ['size_id' => $size->id, 'color_id' => $color->id, 'stock' => (int) $row['existencia_inicial'], 'sale_price' => $row['precio_venta'], 'purchase_price' => $row['costo'] === '' ? null : $row['costo']];
            }
        }
        $category = Category::where('name', $first['categoria'])->where('active', true)->first();
        if (! $category) {
            $errors[] = 'Categoría desconocida o inactiva; utiliza una existente.';
        } elseif (! $category->sku_prefix) {
            $errors[] = 'La categoría necesita un prefijo SKU configurado.';
        }

        $occasionIds = [];
        foreach (preg_split('/[,;]+/u', $first['ocasiones'] ?? '') as $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }
            $occasion = Occasion::query()->where('normalized_name', Occasion::normalizeName($name))->where('active', true)->first();
            if (! $occasion) {
                $errors[] = "Ocasión desconocida o inactiva: {$name}.";
            } else {
                $occasionIds[] = $occasion->id;
            }
        }

        return ['errors' => array_values(array_unique($errors)), 'product' => ['name' => $first['nombre'], 'description' => $first['descripcion'] ?: null, 'category_id' => $category?->id, 'gender' => $first['genero']], 'variants' => $variants, 'occasion_ids' => array_values(array_unique($occasionIds))];
    }

    public function preview(string $path, string $filename, User $user, string $mode = 'create'): string
    {
        $hash = hash_file('sha256', $path);
        $existing = DB::table('catalog_import_batches')->where('user_id', $user->id)->where('file_hash', $hash)->where('mode', $mode)->first();
        if ($existing) {
            return $existing->id;
        }
        $rows = app(CatalogXlsxReader::class)->read($path, $mode);

        return DB::transaction(function () use ($rows, $hash, $filename, $user, $mode) {
            // Serializes repeat uploads by this user, including simultaneous requests.
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $existing = DB::table('catalog_import_batches')->where('user_id', $user->id)->where('file_hash', $hash)->where('mode', $mode)->first();
            if ($existing) {
                return $existing->id;
            }
            $id = (string) Str::uuid();
            DB::table('catalog_import_batches')->insert(['id' => $id, 'user_id' => $user->id, 'filename' => mb_substr(basename($filename), 0, 255), 'file_hash' => $hash, 'mode' => $mode, 'created_at' => now(), 'updated_at' => now()]);
            foreach (collect($rows)->groupBy(fn ($row) => $mode === 'update' ? 'product:'.$row['producto_id'] : ($row['grupo_producto'] === '' ? 'row:'.$row['_row'] : 'group:'.$row['grupo_producto'])) as $key => $group) {
                $values = $group->values()->all();
                $validation = $mode === 'update' ? $this->validateUpdate($values, $user) : $this->validate($values, $user);
                DB::table('catalog_import_groups')->insert(['batch_id' => $id, 'group_key' => hash('sha256', (string) $key), 'rows' => json_encode($values), 'errors' => json_encode($validation['errors']), 'status' => $validation['errors'] ? 'invalid' : 'pending', 'created_at' => now(), 'updated_at' => now()]);
            }

            return $id;
        });
    }

    public function view(string $id, User $user): array
    {
        $batch = DB::table('catalog_import_batches')->where('id', $id)->where('user_id', $user->id)->first();
        abort_unless($batch, 404);
        $groups = DB::table('catalog_import_groups')->where('batch_id', $id)->orderBy('id')->get()->map(function ($group) use ($user, $batch) {
            $rows = json_decode($group->rows, true);
            if (! $user->can('products.view_purchase_price')) {
                foreach ($rows as &$row) {
                    unset($row['costo']);
                }
            }

            $key = $batch->mode === 'update' ? 'Producto #'.$rows[0]['producto_id'] : ($rows[0]['grupo_producto'] ?: 'Fila '.$rows[0]['_row']);
            if (str_starts_with($key, 'Modelo fila ') && filled($rows[0]['nombre'] ?? null)) {
                $key = $rows[0]['nombre'].' · fila '.$rows[0]['_row'];
            }
            return ['id' => $group->id, 'key' => $key, 'status' => $group->status, 'product_id' => $group->product_id, 'errors' => json_decode($group->errors, true), 'rows' => $rows];
        });

        return ['id' => $id, 'filename' => $batch->filename, 'mode' => $batch->mode, 'groups' => $groups, 'counts' => $groups->countBy('status')];
    }

    public function confirm(string $id, array $selected, User $user): void
    {
        DB::transaction(function () use ($id, $selected, $user) {
            $batch = DB::table('catalog_import_batches')->where('id', $id)->where('user_id', $user->id)->lockForUpdate()->first();
            abort_unless($batch, 404);
            abort_if($batch->mode === 'update' && ! $user->can('products.update'), 403);
            $groups = DB::table('catalog_import_groups')->where('batch_id', $id)->whereIn('id', $selected)->lockForUpdate()->get();
            if ($groups->count() !== count(array_unique($selected))) {
                throw ValidationException::withMessages(['groups' => 'Selecciona grupos de este lote.']);
            }
            foreach ($groups as $group) {
                if (in_array($group->status, ['completed', 'queued'], true)) {
                    continue;
                }
                $validation = $batch->mode === 'update' ? $this->validateUpdate(json_decode($group->rows, true), $user) : $this->validate(json_decode($group->rows, true), $user);
                if ($validation['errors']) {
                    DB::table('catalog_import_groups')->where('id', $group->id)->update(['status' => 'invalid', 'errors' => json_encode($validation['errors']), 'updated_at' => now()]);

                    continue;
                }
                DB::table('catalog_import_groups')->where('id', $group->id)->update(['status' => 'queued', 'errors' => '[]', 'updated_at' => now()]);
                ImportCatalogGroup::dispatch($group->id)->onConnection('database')->onQueue('catalog-imports')->afterCommit();
            }
        });
    }

    public function process(int $groupId): void
    {
        try {
            DB::transaction(function () use ($groupId) {
                $group = DB::table('catalog_import_groups')->where('id', $groupId)->lockForUpdate()->firstOrFail();
                if ($group->status === 'completed') {
                    return;
                }
                if ($group->status !== 'queued') {
                    return;
                }
                $batch = DB::table('catalog_import_batches')->where('id', $group->batch_id)->firstOrFail();
                $user = User::findOrFail($batch->user_id);
                if (! $user->active || ! $user->can($batch->mode === 'update' ? 'products.update' : 'products.create')) {
                    throw ValidationException::withMessages(['permission' => 'El usuario ya no tiene permiso para crear productos.']);
                }
                if ($batch->mode === 'update') {
                    $rows = json_decode($group->rows, true);
                    Product::query()->whereKey((int) ($rows[0]['producto_id'] ?? 0))->lockForUpdate()->firstOrFail();
                    ProductVariant::query()->where('product_id', (int) ($rows[0]['producto_id'] ?? 0))->orderBy('id')->lockForUpdate()->get();
                }
                $data = $batch->mode === 'update' ? $this->validateUpdate(json_decode($group->rows, true), $user) : $this->validate(json_decode($group->rows, true), $user);
                if ($data['errors']) {
                    throw ValidationException::withMessages(['rows' => $data['errors']]);
                }
                if ($batch->mode === 'update') {
                    $this->processUpdate($groupId, $data);
                    return;
                }
                $codes = app(CatalogCodeService::class);
                $categoryId = (int) $data['product']['category_id'];
                $sku = $codes->next($categoryId, 'product');
                $first = $data['variants'][0];
                $product = Product::create($data['product'] + ['sku' => $sku, 'created_by' => $user->id, 'size_id' => $first['size_id'], 'color_id' => $first['color_id'], 'purchase_price' => $first['purchase_price'] ?? 0, 'sale_price' => $first['sale_price'], 'sale_price_base' => $first['sale_price'], 'status' => 'disponible', 'catalog_published' => false]);
                $codes->bind($sku, 'product', $product->id);
                $product->occasions()->sync($data['occasion_ids']);
                foreach ($data['variants'] as $variant) {
                    $sku = $codes->next($categoryId, 'variant');
                    $created = ProductVariant::create($variant + ['sku' => $sku, 'product_id' => $product->id, 'active' => true]);
                    $codes->bind($sku, 'variant', $created->id);
                }
                app(InventoryService::class)->updateSoldOutAt($product->id);
                DB::table('catalog_import_groups')->where('id', $groupId)->update(['status' => 'completed', 'product_id' => $product->id, 'errors' => '[]', 'updated_at' => now()]);
            }, 3);
        } catch (ValidationException $exception) {
            DB::table('catalog_import_groups')->where('id', $groupId)->where('status','queued')->update(['status' => 'failed', 'errors' => json_encode(collect($exception->errors())->flatten()->all()), 'updated_at' => now()]);
        }
    }

    private function processUpdate(int $groupId, array $data): void
    {
        $product = Product::query()->lockForUpdate()->findOrFail($data['product_id']);
        $product->update($data['product_changes']);
        if ($data['occasion_ids'] !== null) $product->occasions()->sync($data['occasion_ids']);
        foreach ($data['variants'] as $line) {
            if ($line['id']) {
                ProductVariant::query()->where('product_id', $product->id)->whereKey($line['id'])->firstOrFail()->update($line['values']);
            } else {
                $code = app(CatalogCodeService::class)->next((int) $product->category_id, 'variant');
                $variant = ProductVariant::create($line['values'] + ['product_id' => $product->id, 'sku' => $code, 'stock' => 0, 'active' => true]);
                app(CatalogCodeService::class)->bind($code, 'variant', $variant->id);
            }
        }
        $firstVariant = $product->variants()->orderBy('id')->first();
        if ($firstVariant) {
            $product->forceFill([
                'size_id' => $firstVariant->size_id,
                'color_id' => $firstVariant->color_id,
                'sale_price' => $firstVariant->sale_price,
                'sale_price_base' => $firstVariant->sale_price,
            ])->save();
        }
        $product->touch();
        DB::table('catalog_import_groups')->where('id', $groupId)->update(['status' => 'completed', 'product_id' => $product->id, 'errors' => '[]', 'updated_at' => now()]);
    }
}
