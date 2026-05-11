<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Traits\HasActivityLogging;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class UserController extends Controller
{
    use HasActivityLogging;

    public function index(Request $request)
    {
        if (!$request->user()->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé. Super admin requis.',
            ], 403);
        }

        $users = User::with('ministere:id,nom,sous_domaine')
            ->when($request->role, fn($q) => $q->where('role', $request->role))
            ->when($request->ministere_id, fn($q) => $q->where('ministere_id', $request->ministere_id))
            ->when($request->actif, fn($q) => $q->where('actif', filter_var($request->actif, FILTER_VALIDATE_BOOLEAN)))
            ->when(
                $request->search,
                fn($q) => $q->where(function ($query) use ($request) {
                    $query->where('name', 'like', "%{$request->search}%")
                        ->orWhere('email', 'like', "%{$request->search}%");
                })
            )
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $users]);
    }

    public function store(StoreUserRequest $request)
    {
        if (!$request->user()->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé. Super admin requis.',
            ], 403);
        }

        $validated = $request->validated();

        $user = User::create([
            'name'          => $validated['name'],
            'prenom'        => $validated['prenom'] ?? null,
            'email'        => $validated['email'],
            'password'     => Hash::make($validated['password']),
            'role'         => $validated['role'],
            'ministere_id' => in_array($validated['role'], ['admin_ministere', 'createur_contenu', 'moderateur']) 
                ? $validated['ministere_id'] 
                : null,
            'actif'        => $validated['actif'] ?? true,
        ]);

        $this->logAction($request, 'create_user', 'utilisateurs', "Création: {$user->email}");

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur créé.',
            'data'    => new UserResource($user->load('ministere:id,nom')),
        ], 201);
    }

    public function show(Request $request, string $id)
    {
        if (!$request->user()->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé. Super admin requis.',
            ], 403);
        }

        $user = User::with('ministere:id,nom,sous_domaine')->findOrFail($id);

        return response()->json(['success' => true, 'data' => new UserResource($user)]);
    }

    public function update(UpdateUserRequest $request, string $id)
    {
        if (!$request->user()->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé. Super admin requis.',
            ], 403);
        }

        $user = User::findOrFail($id);
        $validated = $request->validated();

        $data = $request->only(['name', 'prenom', 'email', 'role', 'ministere_id', 'actif']);

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update(array_filter($data));

        $this->logAction($request, 'update_user', 'utilisateurs', "Modification: {$user->email}");

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur mis à jour.',
            'data'    => new UserResource($user->fresh()->load('ministere:id,nom')),
        ]);
    }

    public function destroy(Request $request, string $id)
    {
        if (!$request->user()->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé. Super admin requis.',
            ], 403);
        }

        $user = User::findOrFail($id);

        if ($user->id === $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez pas supprimer votre propre compte.',
            ], 403);
        }

        if ($user->role === 'super_admin' && !$request->user()->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de supprimer un super admin.',
            ], 403);
        }

        $user->update(['actif' => false]);

        $this->logAction($request, 'disable_user', 'utilisateurs', "Désactivation: {$user->email}");

        return response()->json(['success' => true, 'message' => 'Utilisateur désactivé.']);
    }

    public function toggle(Request $request, string $id)
    {
        if (!$request->user()->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé. Super admin requis.',
            ], 403);
        }

        $user = User::findOrFail($id);

        if ($user->id === $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Action impossible sur votre propre compte.',
            ], 403);
        }

        $user->update(['actif' => !$user->actif]);
        $etat = $user->fresh()->actif ? 'activé' : 'désactivé';

        $this->logAction($request, 'toggle_user', 'utilisateurs', "Toggle: {$user->email} → {$etat}");

        return response()->json([
            'success' => true,
            'message' => "Utilisateur {$etat}.",
            'data'    => new UserResource($user->fresh()),
        ]);
    }

    public function impersonate(Request $request, string $id)
    {
        $rateLimitKey = 'impersonate:' . $request->user()->id;
        
        if (RateLimiter::tooManyAttempts($rateLimitKey, 10)) {
            return response()->json([
                'success' => false,
                'message' => 'Trop de tentatives. Veuillez réessayer plus tard.',
            ], 429);
        }

        if (!$request->user()->isSuperAdmin()) {
            RateLimiter::hit($rateLimitKey, 300);
            return response()->json([
                'success' => false,
                'message' => 'Accès réservé aux super admins.',
            ], 403);
        }

        $targetUser = User::findOrFail($id);

        if ($targetUser->id === $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Action impossible sur votre propre compte.',
            ], 400);
        }

        if ($targetUser->role === 'super_admin') {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de imiter un super admin.',
            ], 403);
        }

        RateLimiter::clear($rateLimitKey);

        $token = $targetUser->createToken(
            'impersonate_' . $request->user()->id,
            ['*'],
            now()->addMinutes(30)
        )->plainTextToken;

        $this->logAction(
            $request,
            'impersonate_user',
            'utilisateurs',
            "Impersonation: {$request->user()->email} → {$targetUser->email}"
        );

        return response()->json([
            'success' => true,
            'message' => "Session temporaire (30 min) en tant que {$targetUser->name}.",
            'token'  => $token,
            'user'   => new UserResource($targetUser->load('ministere:id,nom')),
            'expires_in' => 1800,
        ]);
    }

    public function ministryIndex(Request $request)
    {
        $ministereId = $request->user()->ministere_id;

        $users = User::where('ministere_id', $ministereId)
            ->when($request->actif, fn($q) => $q->where('actif', filter_var($request->actif, FILTER_VALIDATE_BOOLEAN)))
            ->when(
                $request->search,
                fn($q) => $q->where(function ($query) use ($request) {
                    $query->where('name', 'like', "%{$request->search}%")
                        ->orWhere('email', 'like', "%{$request->search}%");
                })
            )
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $users]);
    }

    public function ministryStore(Request $request)
    {
        $user = $request->user();
        
        if (!in_array($user->role, ['admin_ministere', 'createur_contenu'])) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé. Rôle admin_ministere ou createur_contenu requis.',
            ], 403);
        }

        $validated = $request->validate([
            'name'      => 'required|string|max:255',
            'prenom'    => 'nullable|string|max:255',
            'email'     => 'required|email|max:255|unique:users,email',
            'password'  => 'required|string|min:8',
            'role'      => 'nullable|in:admin_ministere,createur_contenu,moderateur',
        ]);

        $role = $validated['role'] ?? 'createur_contenu';

        $newUser = User::create([
            'name'          => $validated['name'],
            'prenom'        => $validated['prenom'] ?? null,
            'email'        => $validated['email'],
            'password'     => Hash::make($validated['password']),
            'role'         => $role,
            'ministere_id' => $user->ministere_id,
            'actif'        => true,
        ]);

        $this->logAction($request, 'create_ministry_user', 'utilisateurs', "Création: {$newUser->email}");

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur créé pour le ministère.',
            'data'    => new UserResource($newUser),
        ], 201);
    }

    public function ministryUpdate(Request $request, string $id)
    {
        $user = User::where('ministere_id', $request->user()->ministere_id)
            ->findOrFail($id);

        $validated = $request->validate([
            'name'   => 'sometimes|string|max:255',
            'prenom' => 'nullable|string|max:255',
            'email'  => "sometimes|email|max:255|unique:users,email,{$id}",
            'actif'  => 'nullable|boolean',
        ]);

        $user->update($request->only(['name', 'prenom', 'email', 'actif']));

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur mis à jour.',
            'data'    => new UserResource($user->fresh()),
        ]);
    }

    public function ministryDestroy(Request $request, string $id)
    {
        $user = User::where('ministere_id', $request->user()->ministere_id)
            ->findOrFail($id);

        if ($user->id === $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de vous supprimer vous-même.',
            ], 403);
        }

        $user->update(['actif' => false]);

        return response()->json(['success' => true, 'message' => 'Utilisateur désactivé.']);
    }
}