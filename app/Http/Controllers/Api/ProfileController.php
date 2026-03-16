<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LogAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    // GET /api/profile
    public function show(Request $request)
    {
        $user = $request->user()->load('ministere:id,nom,sous_domaine,couleur_primaire,logo');

        return response()->json(['success' => true, 'data' => $user]);
    }

    // PUT /api/profile
    public function update(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name'   => 'sometimes|string|max:255',
            'prenom' => 'nullable|string|max:255',
            'email'  => "sometimes|email|unique:users,email,{$user->id}",
        ]);

        $user->update($request->only(['name', 'prenom', 'email']));

        LogAction::create([
            'user_id'      => $user->id,
            'ministere_id' => $user->ministere_id,
            'action'       => 'update_profile',
            'module'       => 'profil',
            'details'      => 'Mise à jour du profil',
            'ip'           => $request->ip(),
            'date_action'  => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Profil mis à jour.',
            'data'    => $user->fresh()->load('ministere:id,nom'),
        ]);
    }

    // POST /api/profile/change-password
    public function changePassword(Request $request)
    {
        $request->validate([
            'ancien_mot_de_passe'  => 'required|string',
            'nouveau_mot_de_passe' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (! Hash::check($request->ancien_mot_de_passe, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Ancien mot de passe incorrect.',
            ], 422);
        }

        $user->update(['password' => Hash::make($request->nouveau_mot_de_passe)]);

        // Révoquer tous les autres tokens (sécurité)
        $user->tokens()->where('id', '!=', $request->user()->currentAccessToken()->id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Mot de passe mis à jour. Les autres sessions ont été déconnectées.',
        ]);
    }

    // GET /api/profile/activity
    public function activity(Request $request)
    {
        $user = $request->user();

        $activites = LogAction::where('user_id', $user->id)
            ->orderBy('date_action', 'desc')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $activites]);
    }
}
