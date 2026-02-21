<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserPasswordRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    private const AVAILABLE_ROLES = ['gerente', 'cajero'];

    public function index(Request $request): Response
    {
        $q    = $request->string('q')->toString();
        $role = $request->query('role', '');

        $users = User::query()
            ->with('roles:name')
            ->when($q !== '', fn ($query) => $query->where(function ($q2) use ($q) {
                $q2->where('name', 'like', "%{$q}%")
                   ->orWhere('email', 'like', "%{$q}%");
            }))
            ->when($role !== '', fn ($query) => $query->whereHas(
                'roles', fn ($r) => $r->where('name', $role)
            ))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $u) => [
                'id'            => $u->id,
                'name'          => $u->name,
                'email'         => $u->email,
                'active'        => (bool) $u->active,
                'role'          => $u->roles->first()?->name ?? '—',
                'created_at'    => $u->created_at?->toDateString(),
            ]);

        return Inertia::render('Users/Index', [
            'users'   => $users,
            'roles'   => self::AVAILABLE_ROLES,
            'filters' => ['q' => $q, 'role' => $role],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Users/Create', [
            'roles' => self::AVAILABLE_ROLES,
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
            'active'   => $data['active'] ?? true,
        ]);

        $user->syncRoles([$data['role']]);

        return redirect()->route('users.index')
            ->with('success', "Usuario {$user->name} creado.");
    }

    public function edit(User $user): Response
    {
        $user->loadMissing('roles:name');

        return Inertia::render('Users/Edit', [
            'user'  => [
                'id'     => $user->id,
                'name'   => $user->name,
                'email'  => $user->email,
                'active' => (bool) $user->active,
                'role'   => $user->roles->first()?->name ?? '',
            ],
            'roles' => self::AVAILABLE_ROLES,
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        $user->update([
            'name'   => $data['name'],
            'email'  => $data['email'],
            'active' => $data['active'] ?? true,
        ]);

        $user->syncRoles([$data['role']]);

        return back()->with('success', 'Usuario actualizado.');
    }

    public function toggleActive(User $user): RedirectResponse
    {
        // No desactivar al último gerente
        if ($user->active && $user->hasRole('gerente')) {
            $managersCount = User::whereHas(
                'roles', fn ($q) => $q->where('name', 'gerente')
            )->where('active', true)->count();

            if ($managersCount <= 1) {
                return back()->with('error', 'No puedes desactivar al único gerente activo.');
            }
        }

        $user->update(['active' => ! $user->active]);
        $label = $user->active ? 'activado' : 'desactivado';

        return back()->with('success', "Usuario {$label}.");
    }

    public function updatePassword(UpdateUserPasswordRequest $request, User $user): RedirectResponse
    {
        $user->update([
            'password' => Hash::make($request->validated()['new_password']),
        ]);

        return back()->with('success', 'Contraseña actualizada.');
    }
}
