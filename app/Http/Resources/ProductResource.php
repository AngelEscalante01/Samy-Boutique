<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Recomendación: ocultar purchase_price aquí (transformer), NO en el modelo.
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $canSeePurchasePrice = $user?->can('products.view_purchase_price') ?? false;

        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'description' => $this->description,

            'gender' => $this->gender,
            'status' => $this->status,
            'sold_at' => $this->sold_at,

            'sale_price' => $this->sale_price,
            // Solo gerente (o quien tenga el permiso) verá el costo.
            'purchase_price' => $this->when($canSeePurchasePrice, $this->purchase_price),

            'category' => [
                'id' => $this->whenLoaded('category', fn () => $this->category->id),
                'name' => $this->whenLoaded('category', fn () => $this->category->name),
            ],
            'size' => [
                'id' => $this->whenLoaded('size', fn () => $this->size->id),
                'name' => $this->whenLoaded('size', fn () => $this->size->name),
            ],
            'color' => [
                'id' => $this->whenLoaded('color', fn () => $this->color->id),
                'name' => $this->whenLoaded('color', fn () => $this->color->name),
                'hex' => $this->whenLoaded('color', fn () => $this->color->hex),
            ],
            'images' => $this->whenLoaded('images', fn () => $this->images->map(fn ($img) => [
                'id' => $img->id,
                'path' => $img->path,
                'url' => $img->url,
                'sort' => $img->sort,
            ])),
        ];
    }
}
