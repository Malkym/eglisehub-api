<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\LogAction;
use Illuminate\Http\Request;
//use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 *
 * @OA\Server(
 *     url="http://127.0.0.1:8000/api",
 *     description="Serveur local de développement"
 * )
 *
 *
 * @OA\Tag(name="Auth", description="Authentification")
 * @OA\Tag(name="Ministères", description="Gestion des ministères")
 * @OA\Tag(name="Pages", description="Gestion des pages")
 * @OA\Tag(name="Articles", description="Gestion des articles")
 * @OA\Tag(name="Événements", description="Gestion des événements")
 * @OA\Tag(name="Médias", description="Gestion des médias")
 * @OA\Tag(name="Messages", description="Messages de contact")
 * @OA\Tag(name="Utilisateurs", description="Gestion des utilisateurs")
 * @OA\Tag(name="Paramètres", description="Paramètres du ministère")
 * @OA\Tag(name="Dashboard", description="Statistiques et dashboard")
 * @OA\Tag(name="FAQ", description="Gestion des FAQs")
 * @OA\Tag(name="Sliders", description="Gestion des sliders")
 * @OA\Tag(name="Tags", description="Gestion des tags")
 * @OA\Tag(name="Logs", description="Logs et activité")
 * @OA\Tag(name="Notifications", description="Notifications")
 * @OA\Tag(name="Public", description="Routes publiques sans authentification")
 */

class AuthController extends Controller
{

    /**
     * @OA\Post(
     *     path="/login",
     *     tags={"Auth"},
     *     summary="Connexion utilisateur",
     *     description="Retourne un token Sanctum valide",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password"},
     *             @OA\Property(property="email",    type="string", format="email", example="admin@eglisehub.org"),
     *             @OA\Property(property="password", type="string", example="password123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200, description="Succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="token",   type="string",  example="1|abc123"),
     *             @OA\Property(property="user",    ref="#/components/schemas/User")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Identifiants incorrects")
     * )
     */

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
            'ministere_id' => $user->ministere_id,
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

    /**
     * @OA\Post(
     *     path="/logout",
     *     tags={"Auth"},
     *     summary="Déconnexion",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Déconnecté",
     *         @OA\JsonContent(ref="#/components/schemas/Success")
     *     ),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */


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


    /**
     * @OA\Get(
     *     path="/me",
     *     tags={"Auth"},
     *     summary="Profil de l'utilisateur connecté",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Profil retourné",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="user",    ref="#/components/schemas/User")
     *         )
     *     )
     * )
     */

    // GET /api/me
    public function me(Request $request)
    {
        $user = $request->user()->load('ministere');

        return response()->json([
            'success' => true,
            'user'    => $user,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/change-password",
     *     tags={"Auth"},
     *     summary="Changer le mot de passe",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"ancien_mot_de_passe","nouveau_mot_de_passe","nouveau_mot_de_passe_confirmation"},
     *             @OA\Property(property="ancien_mot_de_passe",               type="string", example="password123"),
     *             @OA\Property(property="nouveau_mot_de_passe",              type="string", example="newpassword456"),
     *             @OA\Property(property="nouveau_mot_de_passe_confirmation", type="string", example="newpassword456")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Mot de passe modifié",
     *         @OA\JsonContent(ref="#/components/schemas/Success")
     *     ),
     *     @OA\Response(response=422, description="Ancien mot de passe incorrect")
     * )
     */

    // POST /api/change-password
    public function changePassword(Request $request)
    {
        $request->validate([
            'ancien_mot_de_passe' => 'required|string',
            'nouveau_mot_de_passe' => 'required|string|min:8|confirmed',
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
