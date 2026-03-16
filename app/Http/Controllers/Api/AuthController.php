<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\LogAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // POST /api/login
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        $user = User::where('email', $request->email)
                    ->where('actif', true)
                    ->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Identifiants incorrects.'],
            ]);
        }

        // Mettre à jour la date de dernière connexion
        $user->update(['dernier_login' => now()]);

        // Créer le token Sanctum
        $token = $user->createToken('auth_token')->plainTextToken;

        // Logger l'action
        LogAction::create([
            'user_id'     => $user->id,
            'ministere_id'=> $user->ministere_id,
            'action'      => 'login',
            'module'      => 'auth',
            'ip'          => $request->ip(),
            'date_action' => now(),
        ]);

        return response()->json([
            'success' => true,
            'token'   => $token,
            'user'    => [
                'id'           => $user->id,
                'name'         => $user->name,
                'prenom'       => $user->prenom,
                'email'        => $user->email,
                'role'         => $user->role,
                'ministere_id' => $user->ministere_id,
                'ministere'    => $user->ministere,
            ],
        ]);
    }

    // POST /api/logout
    public function logout(Request $request)
    {
        // Révoquer le token actuel
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Déconnexion réussie.',
        ]);
    }

    // GET /api/me
    public function me(Request $request)
    {
        $user = $request->user()->load('ministere');

        return response()->json([
            'success' => true,
            'user'    => $user,
        ]);
    }

    // POST /api/change-password
    public function changePassword(Request $request)
    {
        $request->validate([
            'ancien_mot_de_passe' => 'required|string',
            'nouveau_mot_de_passe'=> 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (! Hash::check($request->ancien_mot_de_passe, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Ancien mot de passe incorrect.',
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->nouveau_mot_de_passe),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Mot de passe mis à jour.',
        ]);
    }
}