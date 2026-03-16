<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\LogAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    // GET /api/admin/users — Super admin : tous les utilisateurs
    public function index(Request $request)
    {
        $users = User::with('ministere:id,nom,sous_domaine')
            ->when($request->role,         fn($q) => $q->where('role', $request->role))
            ->when($request->ministere_id, fn($q) => $q->where('ministere_id', $request->ministere_id))
            ->when($request->actif,        fn($q) => $q->where('actif', $request->actif === 'true'))
            ->when(
                $request->search,
                fn($q) =>
                $q->where('name', 'like', "%{$request->search}%")
                    ->orWhere('email', 'like', "%{$request->search}%")
            )
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $users]);
    }

    // POST /api/admin/users — Super admin : créer un utilisateur
    public function store(Request $request)
    {
        $request->validate([
            'name'         => 'required|string|max:255',
            'prenom'       => 'nullable|string|max:255',
            'email'        => 'required|email|unique:users,email',
            'password'     => 'required|string|min:8',
            'role'         => 'required|in:super_admin,admin_ministere',
            'ministere_id' => 'required_if:role,admin_ministere|nullable|exists:ministeres,id',
            'actif'        => 'boolean',
        ]);

        $user = User::create([
            'name'         => $request->name,
            'prenom'       => $request->prenom,
            'email'        => $request->email,
            'password'     => Hash::make($request->password),
            'role'         => $request->role,
            'ministere_id' => $request->role === 'admin_ministere' ? $request->ministere_id : null,
            'actif'        => $request->actif ?? true,
        ]);

        $this->log($request, 'create_user', 'utilisateurs', "Création: {$user->email}");

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur créé.',
            'data'    => $user->load('ministere:id,nom'),
        ], 201);
    }

    // GET /api/admin/users/{id}
    public function show(Request $request, string $id)
    {
        $user = User::with('ministere:id,nom,sous_domaine')->findOrFail($id);

        return response()->json(['success' => true, 'data' => $user]);
    }

    // PUT /api/admin/users/{id}
    public function update(Request $request, string $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'name'         => 'sometimes|string|max:255',
            'prenom'       => 'nullable|string|max:255',
            'email'        => "sometimes|email|unique:users,email,{$id}",
            'password'     => 'nullable|string|min:8',
            'role'         => 'sometimes|in:super_admin,admin_ministere',
            'ministere_id' => 'nullable|exists:ministeres,id',
            'actif'        => 'boolean',
        ]);

        $data = $request->only(['name', 'prenom', 'email', 'role', 'ministere_id', 'actif']);

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        $this->log($request, 'update_user', 'utilisateurs', "Modification: {$user->email}");

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur mis à jour.',
            'data'    => $user->fresh()->load('ministere:id,nom'),
        ]);
    }

    // DELETE /api/admin/users/{id}
    public function destroy(Request $request, string $id)
    {
        $user = User::findOrFail($id);

        // Empêcher de se supprimer soi-même
        if ($user->id === $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez pas supprimer votre propre compte.',
            ], 403);
        }

        // Désactiver plutôt que supprimer
        $user->update(['actif' => false]);

        $this->log($request, 'disable_user', 'utilisateurs', "Désactivation: {$user->email}");

        return response()->json(['success' => true, 'message' => 'Utilisateur désactivé.']);
    }

    // POST /api/admin/users/{id}/toggle
    public function toggle(Request $request, string $id)
    {
        $user = User::findOrFail($id);

        if ($user->id === $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Action impossible sur votre propre compte.',
            ], 403);
        }

        $user->update(['actif' => ! $user->actif]);
        $etat = $user->fresh()->actif ? 'activé' : 'désactivé';

        $this->log($request, 'toggle_user', 'utilisateurs', "Toggle: {$user->email} → {$etat}");

        return response()->json([
            'success' => true,
            'message' => "Utilisateur {$etat}.",
            'data'    => $user->fresh(),
        ]);
    }

    // POST /api/admin/users/{id}/impersonate
    // Permet au super admin de se connecter en tant qu'un autre utilisateur
    public function impersonate(Request $request, string $id)
    {
        $user = User::findOrFail($id);

        if ($user->id === $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Action impossible sur votre propre compte.',
            ], 403);
        }

        // Révoquer les anciens tokens et créer un nouveau
        $token = $user->createToken('impersonate_token')->plainTextToken;

        $this->log(
            $request,
            'impersonate_user',
            'utilisateurs',
            "Impersonation: {$request->user()->email} → {$user->email}"
        );

        return response()->json([
            'success' => true,
            'message' => "Connecté en tant que {$user->name}.",
            'token'   => $token,
            'user'    => $user->load('ministere:id,nom'),
        ]);
    }

    // GET /api/ministry/users — Admin ministère : ses utilisateurs
    public function ministryIndex(Request $request)
    {
        $ministereId = $request->user()->ministere_id;

        $users = User::where('ministere_id', $ministereId)
            ->when($request->actif,  fn($q) => $q->where('actif', $request->actif === 'true'))
            ->when(
                $request->search,
                fn($q) =>
                $q->where('name', 'like', "%{$request->search}%")
                    ->orWhere('email', 'like', "%{$request->search}%")
            )
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $users]);
    }

    // POST /api/ministry/users — Admin ministère : créer un utilisateur pour son ministère
    public function ministryStore(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'prenom'   => 'nullable|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
        ]);

        $user = User::create([
            'name'         => $request->name,
            'prenom'       => $request->prenom,
            'email'        => $request->email,
            'password'     => Hash::make($request->password),
            'role'         => 'admin_ministere',
            'ministere_id' => $request->user()->ministere_id,
            'actif'        => true,
        ]);

        $this->log($request, 'create_ministry_user', 'utilisateurs', "Création: {$user->email}");

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur créé pour le ministère.',
            'data'    => $user,
        ], 201);
    }

    // PUT /api/ministry/users/{id}
    public function ministryUpdate(Request $request, string $id)
    {
        $user = User::where('ministere_id', $request->user()->ministere_id)
            ->findOrFail($id);

        $request->validate([
            'name'   => 'sometimes|string|max:255',
            'prenom' => 'nullable|string|max:255',
            'email'  => "sometimes|email|unique:users,email,{$id}",
            'actif'  => 'boolean',
        ]);

        $user->update($request->only(['name', 'prenom', 'email', 'actif']));

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur mis à jour.',
            'data'    => $user->fresh(),
        ]);
    }

    // DELETE /api/ministry/users/{id}
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

    private function log(Request $request, string $action, string $module, string $details): void
    {
        LogAction::create([
            'user_id'      => $request->user()->id,
            'ministere_id' => $request->user()->ministere_id,
            'action'       => $action,
            'module'       => $module,
            'details'      => $details,
            'ip'           => $request->ip(),
            'date_action'  => now(),
        ]);
    }
}
