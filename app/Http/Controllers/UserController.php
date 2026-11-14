<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Property;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();
        $properties = Property::query()->orderBy('code');

        if (! $actor->hasGlobalPropertyAccess()) {
            $properties->whereIn('id', $actor->property_ids ?? []);
        }

        return response()->json([
            'users' => User::query()->orderBy('name')->get()
                ->filter(fn (User $user): bool => $this->mayManage($actor, $user))->values(),
            'permissions' => $actor->capabilities(),
            'properties' => $properties->get(['id', 'code']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $this->authorizeAccessAssignment($request->user(), $data);
        $user = User::create($data);
        AuditLog::record('create', $user, $user->only(['role', 'active', 'permissions', 'property_ids']));

        return response()->json(['user' => $user], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $this->validated($request, $user);

        $user = DB::transaction(function () use ($request, $user, $data): User {
            $superusers = User::query()->where('role', 'superuser')->lockForUpdate()->get();
            $target = User::query()->lockForUpdate()->findOrFail($user->id);
            $actor = $request->user();

            abort_unless($this->mayManage($actor, $target), 403, 'No puedes modificar esta cuenta.');

            $this->authorizeAccessAssignment($actor, $data, $target);

            if ($target->role === 'superuser'
                && ($data['role'] !== 'superuser' || ! $data['active'])
                && $superusers->where('active', true)->count() <= 1) {
                throw ValidationException::withMessages(['role' => 'Debe existir al menos un superusuario habilitado.']);
            }

            $target->update($data);
            AuditLog::record('update', $target, $target->only(['role', 'active', 'permissions', 'property_ids']));

            return $target;
        });

        return response()->json(['user' => $user]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?User $user = null): array
    {
        if ($request->has('email')) {
            $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user?->id)],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:10', 'max:255'],
            'role' => ['sometimes', Rule::in(['superuser', 'admin', 'user'])],
            'active' => ['sometimes', 'boolean'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::in(User::PERMISSIONS)],
            'property_ids' => ['sometimes', 'array'],
            'property_ids.*' => ['integer', 'distinct', Rule::exists('properties', 'id')],
        ], [
            'password.min' => 'La contraseña debe tener al menos 10 caracteres.',
            'email.unique' => 'Ya existe una cuenta con este correo.',
            'property_ids.*.exists' => 'Uno de los inmuebles seleccionados no existe.',
        ]);

        $data['role'] ??= $user?->role ?? 'user';
        $data['active'] ??= $user?->active ?? true;
        $data['permissions'] ??= $user?->permissions ?? [];
        $data['property_ids'] = array_map('intval', $data['property_ids'] ?? $user?->property_ids ?? []);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    private function authorizeAccessAssignment(User $actor, array $data, ?User $target = null): void
    {
        if ($target?->is($actor)) {
            foreach (['role', 'active', 'permissions', 'property_ids'] as $field) {
                $current = $actor->{$field};
                $requested = $data[$field];
                if (in_array($field, ['permissions', 'property_ids'], true)) {
                    $current ??= [];
                    sort($current);
                    sort($requested);
                }
                if ($requested !== $current) {
                    throw ValidationException::withMessages([$field => 'Tu propio acceso debe modificarlo otra cuenta autorizada.']);
                }
            }

            return;
        }

        if ($actor->role !== 'superuser' && $data['role'] !== 'user') {
            abort(403, 'La asignación de administradores corresponde al superusuario.');
        }

        if (array_diff($data['permissions'], $actor->capabilities())) {
            abort(403, 'Solo puedes asignar permisos que posee tu cuenta.');
        }

        if (! $actor->hasGlobalPropertyAccess()
            && array_diff($data['property_ids'], array_map('intval', $actor->property_ids ?? []))) {
            abort(403, 'Solo puedes asignar inmuebles incluidos en tu alcance.');
        }
    }

    private function mayManage(User $actor, User $target): bool
    {
        if ($actor->role === 'superuser' || $actor->is($target)) {
            return true;
        }

        if ($target->role !== 'user' || array_diff($target->capabilities(), $actor->capabilities())) {
            return false;
        }

        return $actor->hasGlobalPropertyAccess()
            || (! $target->hasGlobalPropertyAccess()
                && ! array_diff($target->property_ids ?? [], $actor->property_ids ?? []));
    }
}
