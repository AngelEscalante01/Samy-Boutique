<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;

class NativeProductController extends Controller
{
    public function show(Product $product, Request $request)
    {
        $product->load(['category', 'size', 'color', 'images.derivatives', 'occasions', 'variants.size', 'variants.color']);
        return response()->json(['success' => true, 'data' => [
            'product' => (new ProductResource($product))->resolve(),
            'barcodes' => app(\App\Services\ProductLabelBarcode::class)->productPreviews($product),
            'can' => ['update' => $request->user()->can('products.update'), 'viewPurchasePrice' => $request->user()->can('products.view_purchase_price')],
        ]]);
    }
}
