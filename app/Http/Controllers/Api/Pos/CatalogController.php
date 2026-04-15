<?php

namespace App\Http\Controllers\Api\Pos;

use App\Http\Controllers\Api\ApiController;
use App\Models\Category;
use App\Models\Color;
use App\Models\Size;
use Illuminate\Http\JsonResponse;

class CatalogController extends ApiController
{
    /**
     * GET /api/catalogs
     * Returns all active categories, sizes and colors in a single request.
     */
    public function index(): JsonResponse
    {
        $categories = Category::query()
            ->orderBy('name')
            ->get(['id', 'name', 'active']);

        $sizes = Size::query()
            ->orderBy('name')
            ->get(['id', 'name', 'active']);

        $colors = Color::query()
            ->orderBy('name')
            ->get(['id', 'name', 'hex', 'active']);

        return $this->successResponse([
            'categories' => $categories,
            'sizes'      => $sizes,
            'colors'     => $colors,
        ]);
    }

    /**
     * GET /api/catalogs/categories
     */
    public function categories(): JsonResponse
    {
        $categories = Category::query()
            ->orderBy('name')
            ->get(['id', 'name', 'active']);

        return $this->successResponse($categories);
    }

    /**
     * GET /api/catalogs/sizes
     */
    public function sizes(): JsonResponse
    {
        $sizes = Size::query()
            ->orderBy('name')
            ->get(['id', 'name', 'active']);

        return $this->successResponse($sizes);
    }

    /**
     * GET /api/catalogs/colors
     */
    public function colors(): JsonResponse
    {
        $colors = Color::query()
            ->orderBy('name')
            ->get(['id', 'name', 'hex', 'active']);

        return $this->successResponse($colors);
    }
}
