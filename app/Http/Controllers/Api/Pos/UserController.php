<?php

namespace App\Http\Controllers\Api\Pos;

use App\Http\Controllers\Api\ApiController;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends ApiController
{
    /**
     * GET /api/users
     * Returns a paginated list of users. Only accessible to managers (gerente).
     *
     * Query params:
     *  - q: search in name or email
     *  - per_page: default 20
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::query()->with('roles');

        if ($q = $request->query('q')) {
            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%");
            });
        }

        $paginator = $query->orderBy('name')
                           ->paginate((int) ($request->query('per_page', 20)));

        $items = $paginator->getCollection()->map(fn (User $user) => [
            'id'     => $user->id,
            'name'   => $user->name,
            'email'  => $user->email,
            'active' => (bool) $user->active,
            'roles'  => $user->getRoleNames()->values(),
            'created_at' => $user->created_at?->toISOString(),
        ])->values()->all();

        return $this->paginatedResponse($paginator, $items);
    }
}
