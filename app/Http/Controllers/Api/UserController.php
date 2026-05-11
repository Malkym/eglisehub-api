<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class UserController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->user()->isSuperAdmin()) {
            return $this->respondWithError('Accès refusé. Super admin requis.', 403);
        }

        $users = User::with('ministere:id,nom,sous_domaine')
            ->when($request->role, fn($q) => $q->where('role', $request->role))
            ->when($request->ministere_id, fn($q) => $q->where('ministere_id', $request->ministere_id))
            ->when($request->actif, fn($q) => $q->where('actif', filter_var($request->actif, FILTER_VALIDATE_BOOLEAN)))
            ->when($request->search, fn($q) => $q->where(function ($query) use ($request) {
                $query->where('name', 'like', "%{$request->search}%")
                    ->orWhere('email', 'like', "%{$request->search}%");
            }))
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return $this->respondPaginated($users);
    }

    public function store(StoreUserRequest $request)
    {
        if (!$request->user()->isSuperAdmin()) {
            return $this->respondWithError('Accès refusé. Super admin requis.', 403);
        }

        $validated = $request->validated();

        $user = User::create([
            'name'          => $validated['name'],
            'prenom'        => $validated['prenom'] ?? null,
            'email'         => $validated['email'],
            'password'      => Hash::make($validated['password']),
            'role'          => $validated['role'],
            'ministere_id'  => in_array($validated['role'], ['admin_ministere', 'createur_contenu', 'moderateur'])
                ? $validated['ministere_id']
                : null,
            'actif'         => $validated['actif'] ?? true,
        ]);

        $this->log($request, 'create_user', 'utilisateurs', "Création: {$user->email}");

        return $this->respondSuccess(
            new UserResource($user->load('ministere:id,nom')),
            'Utilisateur créé.',
            201
        );
    }

    public function show(Request $request, string $id)
    {
        if (!$request->user()->isSuperAdmin()) {
            return $this->respondWithError('Accès refusé. Super admin requis.', 403);
        }

        $user = User::with('ministere:id,nom,sous_domaine')->findOrFail($id);

        return $this->respondSuccess(new UserResource($user));
    }

    public function update(UpdateUserRequest $request, string $id)
    {
        if (!$request->user()->isSuperAdmin()) {
            return $this->respondWithError('Accès refusé. Super admin requis.', 403);
        }

        $user = User::findOrFail($id);

        $data = $request->only(['name', 'prenom', 'email', 'role', 'ministere_id', 'actif']);

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update(array_filter($data));

        $this->log($request, 'update_user', 'utilisateurs', "Modification: {$user->email}");

        return $this->respondSuccess(
            new UserResource($user->fresh()->load('ministere:id,nom')),
            'Utilisateur mis à jour.'
        );
    }

    public function destroy(Request $request, string $id)
    {
        if (!$request->user()->isSuperAdmin()) {
            return $this->respondWithError('Accès refusé. Super admin requis.', 403);
        }

        $user = User::findOrFail($id);

        if ($user->id === $request->user()->id) {
            return $this->respondWithError('Vous ne pouvez pas supprimer votre propre compte.', 403);
        }

        if ($user->role === 'super_admin') {
            return $this->respondWithError('Impossible de supprimer un super admin.', 403);
        }

        $user->update(['actif' => false]);

        $this->log($request, 'disable_user', 'utilisateurs', "Désactivation: {$user->email}");

        return $this->respondSuccess(null, 'Utilisateur désactivé.');
    }

    public function toggle(Request $request, string $id)
    {
        if (!$request->user()->isSuperAdmin()) {
            return $this->respondWithError('Accès refusé. Super admin requis.', 403);
        }

        $user = User::findOrFail($id);

        if ($user->id === $request->user()->id) {
            return $this->respondWithError('Action impossible sur votre propre compte.', 403);
        }

        $user->update(['actif' => !$user->actif]);
        $etat = $user->fresh()->actif ? 'activé' : 'désactivé';

        $this->log($request, 'toggle_user', 'utilisateurs', "Toggle: {$user->email} → {$etat}");

        return $this->respondSuccess(new UserResource($user->fresh()), "Utilisateur {$etat}.");
    }

    public function impersonate(Request $request, string $id)
    {
        $rateLimitKey = 'impersonate:' . $request->user()->id;

        if (RateLimiter::tooManyAttempts($rateLimitKey, 10)) {
            return $this->respondWithError('Trop de tentatives. Veuillez réessayer plus tard.', 429);
        }

        if (!$request->user()->isSuperAdmin()) {
            RateLimiter::hit($rateLimitKey, 300);
            return $this->respondWithError('Accès réservé aux super admins.', 403);
        }

        $targetUser = User::findOrFail($id);

        if ($targetUser->id === $request->user()->id) {
            return $this->respondWithError('Action impossible sur votre propre compte.', 400);
        }

        if ($targetUser->role === 'super_admin') {
            return $this->respondWithError('Impossible de imiter un super admin.', 403);
        }

        RateLimiter::clear($rateLimitKey);

        $token = $targetUser->createToken(
            'impersonate_' . $request->user()->id,
            ['*'],
            now()->addMinutes(30)
        )->plainTextToken;

        $this->log($request, 'impersonate_user', 'utilisateurs', "Impersonation: {$request->user()->email} → {$targetUser->email}");

        return $this->respondSuccess([
            'token'      => $token,
            'user'       => new UserResource($targetUser->load('ministere:id,nom')),
            'expires_in' => 1800,
        ], "Session temporaire (30 min) en tant que {$targetUser->name}.");
    }

    public function ministryIndex(Request $request)
    {
        $ministereId = $request->user()->ministere_id;

        $users = User::where('ministere_id', $ministereId)
            ->when($request->actif, fn($q) => $q->where('actif', filter_var($request->actif, FILTER_VALIDATE_BOOLEAN)))
            ->when($request->search, fn($q) => $q->where(function ($query) use ($request) {
                $query->where('name', 'like', "%{$request->search}%")
                    ->orWhere('email', 'like', "%{$request->search}%");
            }))
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return $this->respondPaginated($users);
    }

    public function ministryStore(Request $request)
    {
        $user = $request->user();

        if (!in_array($user->role, ['admin_ministere', 'createur_contenu'])) {
            return $this->respondWithError('Accès refusé. Rôle admin_ministere ou createur_contenu requis.', 403);
        }

        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'prenom'   => 'nullable|string|max:255',
            'email'    => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'role'     => 'nullable|in:admin_ministere,createur_contenu,moderateur',
        ]);

        $role = $validated['role'] ?? 'createur_contenu';

        $newUser = User::create([
            'name'          => $validated['name'],
            'prenom'        => $validated['prenom'] ?? null,
            'email'         => $validated['email'],
            'password'      => Hash::make($validated['password']),
            'role'          => $role,
            'ministere_id'  => $user->ministere_id,
            'actif'         => true,
        ]);

        $this->log($request, 'create_ministry_user', 'utilisateurs', "Création: {$newUser->email}");

        return $this->respondSuccess(new UserResource($newUser), 'Utilisateur créé pour le ministère.', 201);
    }

    public function ministryUpdate(Request $request, string $id)
    {
        $user = User::where('ministere_id', $request->user()->ministere_id)
            ->findOrFail($id);

        $request->validate([
            'name'   => 'sometimes|string|max:255',
            'prenom' => 'nullable|string|max:255',
            'email'  => "sometimes|email|max:255|unique:users,email,{$id}",
            'actif'  => 'nullable|boolean',
        ]);

        $user->update($request->only(['name', 'prenom', 'email', 'actif']));

        return $this->respondSuccess(new UserResource($user->fresh()), 'Utilisateur mis à jour.');
    }

    public function ministryDestroy(Request $request, string $id)
    {
        $user = User::where('ministere_id', $request->user()->ministere_id)
            ->findOrFail($id);

        if ($user->id === $request->user()->id) {
            return $this->respondWithError('Impossible de vous supprimer vous-même.', 403);
        }

        $user->update(['actif' => false]);

        return $this->respondSuccess(null, 'Utilisateur désactivé.');
    }
}