<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LogAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user()->load('ministere:id,nom,sous_domaine,couleur_primaire,logo');
        return $this->respondSuccess($user);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name'   => 'sometimes|string|max:255',
            'prenom' => 'nullable|string|max:255',
            'email'  => "sometimes|email|unique:users,email,{$user->id}",
        ]);

        $user->update($request->only(['name', 'prenom', 'email']));

        $this->log($request, 'update_profile', 'profil', 'Mise à jour du profil');

        return $this->respondSuccess($user->fresh()->load('ministere:id,nom'), 'Profil mis à jour.');
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'ancien_mot_de_passe'  => 'required|string',
            'nouveau_mot_de_passe' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->ancien_mot_de_passe, $user->password)) {
            return $this->respondWithError('Ancien mot de passe incorrect.', 422);
        }

        $user->update(['password' => Hash::make($request->nouveau_mot_de_passe)]);

        $user->tokens()->where('id', '!=', $request->user()->currentAccessToken()->id)->delete();

        return $this->respondSuccess(null, 'Mot de passe mis à jour. Les autres sessions ont été déconnectées.');
    }

    public function activity(Request $request)
    {
        $user = $request->user();

        $activites = LogAction::where('user_id', $user->id)
            ->orderBy('date_action', 'desc')
            ->paginate(20);

        return $this->respondPaginated($activites);
    }
}