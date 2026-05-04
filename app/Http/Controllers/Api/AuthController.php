<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\LogAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
//use Illuminate\Validation\ValidationException;

/**
 * 
 * 
 * @OA\Schema(
 *     schema="User",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Mologbama"),
 *     @OA\Property(property="prenom", type="string", nullable=true, example="Abishadai"),
 *     @OA\Property(property="email", type="string", format="email", example="admin@eglisehub.org"),
 *     @OA\Property(property="role", type="string", enum={"super_admin", "admin_ministere", "createur_contenu", "moderateur"}, example="super_admin"),
 *     @OA\Property(property="ministere_id", type="integer", nullable=true, example=1),
 *     @OA\Property(property="actif", type="boolean", example=true),
 *     @OA\Property(property="ministere", type="object", nullable=true)
 * )
 * 
 * @OA\Schema(
 *     schema="LoginRequest",
 *     required={"email", "password"},
 *     @OA\Property(property="email", type="string", format="email", example="admin@eglisehub.org"),
 *     @OA\Property(property="password", type="string", format="password", example="Password123")
 * )
 * 
 * @OA\Schema(
 *     schema="LoginResponse",
 *     @OA\Property(property="success", type="boolean", example=true),
 *     @OA\Property(property="token", type="string", description="Token d'authentification Sanctum", example="74|a58e4541dfbc0e91b5b2746daf3dcafa65c22719733ad09709385c5b6536e0fe"),
 *     @OA\Property(property="user", ref="#/components/schemas/User")
 * )
 * 
 * @OA\Schema(
 *     schema="RegisterRequest",
 *     required={"name", "email", "password", "ministere_nom", "ministere_type", "sous_domaine"},
 *     @OA\Property(property="name", type="string", example="Admin"),
 *     @OA\Property(property="prenom", type="string", nullable=true, example="Jean"),
 *     @OA\Property(property="email", type="string", format="email", example="admin@crc.org"),
 *     @OA\Property(property="password", type="string", format="password", example="Password123"),
 *     @OA\Property(property="password_confirmation", type="string", example="Password123"),
 *     @OA\Property(property="ministere_nom", type="string", example="CRC Bangui"),
 *     @OA\Property(property="ministere_type", type="string", enum={"eglise","ministere","organisation","para_ecclesial","mission"}, example="eglise"),
 *     @OA\Property(property="sous_domaine", type="string", example="crc"),
 *     @OA\Property(property="ville", type="string", nullable=true, example="Bangui"),
 *     @OA\Property(property="pays", type="string", nullable=true, example="République centrafricaine"),
 *     @OA\Property(property="email_contact", type="string", format="email", nullable=true, example="contact@crc.org")
 * )
 * 
 * @OA\Schema(
 *     schema="ChangePasswordRequest",
 *     required={"ancien_mot_de_passe", "nouveau_mot_de_passe", "nouveau_mot_de_passe_confirmation"},
 *     @OA\Property(property="ancien_mot_de_passe", type="string", format="password", example="OldPassword123"),
 *     @OA\Property(property="nouveau_mot_de_passe", type="string", format="password", example="NewPassword123"),
 *     @OA\Property(property="nouveau_mot_de_passe_confirmation", type="string", format="password", example="NewPassword123")
 * )
 */
class AuthController extends Controller
{
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOGIN_LOCKOUT_SECONDS = 300;

    /**
     * Connexion utilisateur
     * 
     * @OA\Post(
     *     path="/login",
     *     operationId="login",
     *     tags={"Auth"},
     *     summary="Connexion utilisateur",
     *     description="Authentifie un utilisateur et retourne un token Sanctum valide",
     *     
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/LoginRequest")
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Connexion réussie",
     *         @OA\JsonContent(ref="#/components/schemas/LoginResponse")
     *     ),
     *     
     *     @OA\Response(
     *         response=401,
     *         description="Identifiants incorrects",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Identifiants incorrects.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=422,
     *         description="Validation échouée",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="L'email est requis."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=429,
     *         description="Trop de tentatives",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Trop de tentatives de connexion. Réessayez dans 5 minute(s)."),
     *             @OA\Property(property="retry_after", type="integer", example=300)
     *         )
     *     )
     * )
     */
    public function login(Request $request)
    {
        $rateLimitKey = 'login:' . $request->ip();
        
        if (RateLimiter::tooManyAttempts($rateLimitKey, self::MAX_LOGIN_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            return response()->json([
                'success' => false,
                'message' => 'Trop de tentatives de connexion. Réessayez dans ' . ceil($seconds / 60) . ' minute(s).',
                'retry_after' => $seconds,
            ], 429);
        }

        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        $user = User::where('email', $request->email)
            ->where('actif', true)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            RateLimiter::hit($rateLimitKey, self::LOGIN_LOCKOUT_SECONDS);
            
            return response()->json([
                'success' => false,
                'message' => 'Identifiants incorrects.',
            ], 401);
        }

        RateLimiter::clear($rateLimitKey);

        $user->update(['dernier_login' => now()]);

        // Garder seulement les 5 derniers tokens
        $oldTokens = $user->tokens()->orderBy('created_at', 'desc')->skip(5)->take(100)->get();
        foreach ($oldTokens as $token) {
            $token->delete();
        }

        // Nom de token unique
        $tokenName = 'auth_token_' . $user->id . '_' . now()->format('Ymd_His');
        $token = $user->createToken($tokenName, ['*'], now()->addDays(7))->plainTextToken;

        // Gestion du ministere_id pour super_admin
        $ministereId = $user->ministere_id;
        if ($user->role === 'super_admin') {
            $ministereId = null;
            if ($user->ministere_id === 0) {
                $user->update(['ministere_id' => null]);
            }
        }

        LogAction::create([
            'user_id'      => $user->id,
            'ministere_id' => $user->ministere_id,
            'action'       => 'login',
            'module'       => 'auth',
            'ip'           => $request->ip(),
            'date_action'  => now(),
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
                'ministere_id' => $ministereId,
                'actif'        => $user->actif,
                'ministere'    => $user->role === 'super_admin' ? null : $user->ministere,
            ],
        ]);
    }

    /**
     * Inscription nouveau ministère
     * 
     * @OA\Post(
     *     path="/api/register",
     *     operationId="register",
     *     tags={"Auth"},
     *     summary="Inscription nouveau ministère",
     *     description="Crée un nouveau ministère et un compte administrateur associé",
     *     
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/RegisterRequest")
     *     ),
     *     
     *     @OA\Response(
     *         response=201,
     *         description="Ministère créé avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Ministère créé avec succès."),
     *             @OA\Property(property="token", type="string", example="74|a58e4541dfbc0e91b5b2746daf3dcafa65c22719733ad09709385c5b6536e0fe"),
     *             @OA\Property(property="user", ref="#/components/schemas/User"),
     *             @OA\Property(property="ministere", type="object")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=422,
     *         description="Validation échouée",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'prenom'         => 'nullable|string|max:255',
            'email'          => 'required|email|max:255|unique:users,email',
            'password'       => 'required|string|min:8|confirmed',
            'ministere_nom'  => 'required|string|max:255',
            'ministere_type' => 'required|in:eglise,ministere,organisation,para_ecclesial,mission',
            'sous_domaine'   => 'required|string|max:100|unique:ministeres,sous_domaine|alpha_dash',
            'ville'          => 'nullable|string|max:100',
            'pays'           => 'nullable|string|max:100',
            'email_contact'  => 'nullable|email|max:255',
        ]);

        $ministere = \App\Models\Ministere::create([
            'nom'               => $validated['ministere_nom'],
            'type'              => $validated['ministere_type'],
            'slug'              => \Illuminate\Support\Str::slug($validated['ministere_nom']),
            'sous_domaine'      => strtolower($validated['sous_domaine']),
            'ville'             => $validated['ville'] ?? null,
            'pays'              => $validated['pays'] ?? 'République centrafricaine',
            'email_contact'     => $validated['email_contact'] ?? $validated['email'],
            'couleur_primaire'   => '#1E3A8A',
            'couleur_secondaire' => '#FFFFFF',
            'statut'            => 'actif',
        ]);

        $user = \App\Models\User::create([
            'name'         => $validated['name'],
            'prenom'       => $validated['prenom'] ?? null,
            'email'        => $validated['email'],
            'password'     => Hash::make($validated['password']),
            'role'         => 'admin_ministere',
            'ministere_id' => $ministere->id,
            'actif'        => true,
        ]);

        $tokenName = 'auth_token_' . $user->id . '_' . now()->format('Ymd_His');
        $token = $user->createToken($tokenName, ['*'], now()->addDays(7))->plainTextToken;

        $superAdmins = \App\Models\User::where('role', 'super_admin')->where('actif', true)->get();
        foreach ($superAdmins as $sa) {
            if (class_exists('\App\Helpers\NotifHelper')) {
                \App\Helpers\NotifHelper::send(
                    $sa->id,
                    'Nouveau ministère inscrit',
                    "{$validated['ministere_nom']} vient de rejoindre EgliseHub !",
                    'success',
                    '/admin/ministeres',
                    'ministeres'
                );
            }
        }

        return response()->json([
            'success'   => true,
            'message'   => 'Ministère créé avec succès.',
            'token'     => $token,
            'user'      => [
                'id'           => $user->id,
                'name'         => $user->name,
                'prenom'       => $user->prenom,
                'email'        => $user->email,
                'role'         => $user->role,
                'ministere_id' => $user->ministere_id,
                'actif'        => $user->actif,
                'ministere'    => $user->ministere,
            ],
            'ministere' => $ministere,
        ], 201);
    }

    /**
     * Déconnexion
     * 
     * @OA\Post(
     *     path="/api/logout",
     *     operationId="logout",
     *     tags={"Auth"},
     *     summary="Déconnexion",
     *     description="Déconnecte l'utilisateur et révoque son token",
     *     security={{"bearerAuth":{}}},
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Déconnexion réussie",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Déconnexion réussie.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=401,
     *         description="Non authentifié"
     *     )
     * )
     */
    public function logout(Request $request)
    {
        $user = $request->user();
        $tokenId = $user->currentAccessToken()->id;
        
        $user->tokens()->where('id', $tokenId)->delete();

        LogAction::create([
            'user_id'      => $user->id,
            'ministere_id' => $user->ministere_id,
            'action'       => 'logout',
            'module'       => 'auth',
            'ip'           => $request->ip(),
            'date_action'  => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Déconnexion réussie.',
        ]);
    }

    /**
     * Profil utilisateur actuel
     * 
     * @OA\Get(
     *     path="/api/me",
     *     operationId="me",
     *     tags={"Auth"},
     *     summary="Profil utilisateur actuel",
     *     description="Retourne les informations de l'utilisateur connecté",
     *     security={{"bearerAuth":{}}},
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Profil retourné",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="user", ref="#/components/schemas/User")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=401,
     *         description="Non authentifié"
     *     )
     * )
     */
    public function me(Request $request)
    {
        $user = $request->user();
        
        $ministereId = $user->ministere_id;
        if ($user->role === 'super_admin' && ($ministereId === 0 || $ministereId === null)) {
            $ministereId = null;
        }
        
        $user->load('ministere');

        return response()->json([
            'success' => true,
            'user'    => [
                'id'           => $user->id,
                'name'         => $user->name,
                'prenom'       => $user->prenom,
                'email'        => $user->email,
                'role'         => $user->role,
                'ministere_id' => $ministereId,
                'actif'        => $user->actif,
                'ministere'    => $user->role === 'super_admin' ? null : $user->ministere,
            ],
        ]);
    }

    /**
     * Changer le mot de passe
     * 
     * @OA\Post(
     *     path="/api/change-password",
     *     operationId="changePassword",
     *     tags={"Auth"},
     *     summary="Changer le mot de passe",
     *     description="Permet à l'utilisateur de modifier son mot de passe",
     *     security={{"bearerAuth":{}}},
     *     
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/ChangePasswordRequest")
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Mot de passe modifié",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Mot de passe mis à jour. Autres sessions déconnectées.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=422,
     *         description="Validation échouée ou ancien mot de passe incorrect",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Ancien mot de passe incorrect.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=401,
     *         description="Non authentifié"
     *     )
     * )
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'ancien_mot_de_passe' => 'required|string',
            'nouveau_mot_de_passe' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->ancien_mot_de_passe, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Ancien mot de passe incorrect.',
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->nouveau_mot_de_passe),
        ]);

        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

        LogAction::create([
            'user_id'      => $user->id,
            'ministere_id' => $user->ministere_id,
            'action'       => 'change_password',
            'module'       => 'auth',
            'ip'           => $request->ip(),
            'date_action'  => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Mot de passe mis à jour. Autres sessions déconnectées.',
        ]);
    }

    /**
     * Rafraîchir le token
     * 
     * @OA\Post(
     *     path="/api/refresh-token",
     *     operationId="refreshToken",
     *     tags={"Auth"},
     *     summary="Rafraîchir le token",
     *     description="Génère un nouveau token pour l'utilisateur connecté",
     *     security={{"bearerAuth":{}}},
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Token rafraîchi",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="token", type="string", example="74|a58e4541dfbc0e91b5b2746daf3dcafa65c22719733ad09709385c5b6536e0fe")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=401,
     *         description="Non authentifié"
     *     )
     * )
     */
    public function refreshToken(Request $request)
    {
        $user = $request->user();
        
        $user->currentAccessToken()->delete();
        
        $tokenName = 'auth_token_' . $user->id . '_' . now()->format('Ymd_His');
        $token = $user->createToken($tokenName, ['*'], now()->addDays(7))->plainTextToken;
        
        return response()->json([
            'success' => true,
            'token'   => $token,
        ]);
    }
}