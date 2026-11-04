<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CatalogCodeService
{
    private function lock(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('La emisión de códigos requiere una transacción externa.');
        }
        DB::table('catalog_code_state')->where('id', 1)->lockForUpdate()->firstOrFail();
    }

    public function setPrefix(Category $category, ?string $prefix): void
    {
        $this->lock();
        $prefix = strtoupper(trim((string) $prefix));
        if ($prefix === '') {
            $category->update(['sku_prefix' => null]);

            return;
        }
        if (! preg_match('/^[A-Z][A-Z0-9]{1,11}$/', $prefix)) {
            throw ValidationException::withMessages(['sku_prefix' => 'Usa de 2 a 12 letras o números, comenzando con letra.']);
        }
        $reserved = DB::table('catalog_code_prefixes')->where('prefix', $prefix)->first();
        if ($reserved && (int) $reserved->category_id !== (int) $category->id) {
            throw ValidationException::withMessages(['sku_prefix' => 'Este prefijo está reservado por otra categoría, incluso si fue retirada.']);
        }
        if (! $reserved) {
            DB::table('catalog_code_prefixes')->insert(['prefix' => $prefix, 'category_id' => $category->id, 'last_number' => 0]);
        }
        $category->update(['sku_prefix' => $prefix]);
    }

    public function next(int $categoryId, string $ownerType): string
    {
        $this->lock();
        if (DB::table('catalog_code_conflicts')->whereNull('resolved_at')->exists()) {
            throw ValidationException::withMessages(['sku' => 'Hay códigos históricos ambiguos. Revisa catalog_code_conflicts antes de emitir nuevos códigos.']);
        }
        $category = Category::query()->findOrFail($categoryId);
        $counter = DB::table('catalog_code_prefixes')->where('prefix', $category->sku_prefix)->lockForUpdate()->first();
        if (! $counter || (int) $counter->category_id !== $categoryId) {
            throw ValidationException::withMessages(['category_id' => 'Configura un prefijo SKU en Catálogos > Categorías antes de crear productos o variantes.']);
        }
        $number = (int) $counter->last_number;
        do {
            $code = $counter->prefix.'-'.str_pad((string) ++$number, 6, '0', STR_PAD_LEFT);
        } while ($this->occupied($code));
        DB::table('catalog_code_prefixes')->where('prefix', $counter->prefix)->update(['last_number' => $number]);
        DB::table('catalog_codes')->insert(['code' => $code, 'code_key' => $this->key($code), 'owner_type' => $ownerType, 'created_at' => now(), 'updated_at' => now()]);

        return $code;
    }

    public function bind(string $code, string $ownerType, int $ownerId): void
    {
        $this->lock();
        $changed = DB::table('catalog_codes')->where('code_key', $this->key($code))->where('owner_type', $ownerType)->whereNull('owner_id')->update(['owner_id' => $ownerId, 'updated_at' => now()]);
        if ($changed !== 1) {
            throw new \LogicException('El código no pertenece a esta reserva.');
        }
    }

    public function addAlias(string $code, string $ownerType, int $ownerId): void
    {
        $this->lock();
        $code = trim($code);
        if ($code === '' || mb_strlen($code) > 80 || ! in_array($ownerType, ['product', 'variant'], true)) {
            throw ValidationException::withMessages(['sku' => 'Alias inválido.']);
        }
        $table = $ownerType === 'product' ? 'products' : 'product_variants';
        if (! DB::table($table)->where('id', $ownerId)->exists() || $this->occupied($code)) {
            throw ValidationException::withMessages(['sku' => 'El alias ya está reservado o la entidad no existe.']);
        }
        DB::table('catalog_codes')->insert(['code' => $code, 'code_key' => $this->key($code), 'owner_type' => $ownerType, 'owner_id' => $ownerId, 'is_alias' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function key(string $code): string
    {
        return hash('sha256', mb_strtoupper(trim($code), 'UTF-8'));
    }

    private function occupied(string $code): bool
    {
        return DB::table('catalog_codes')->where('code_key', $this->key($code))->exists()
            || DB::table('products')->whereRaw('UPPER(TRIM(sku)) = ?', [mb_strtoupper(trim($code), 'UTF-8')])->exists()
            || DB::table('product_variants')->whereRaw('UPPER(TRIM(sku)) = ?', [mb_strtoupper(trim($code), 'UTF-8')])->exists();
    }
}
