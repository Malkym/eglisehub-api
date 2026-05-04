<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\LogAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOGIN_LOCKOUT_SECONDS = 300;

    /**
     * @OA\Post(
     *     path="/login",
     *     tags={"Auth"},
     *     summary="Connexion utilisateur",
     *     description="Retourne un token Sanctum valide avec expiration de 7 jours",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password"},
     *             @OA\Property(property="email",    type="string", format="email", example="admin@eglisehub.org"),
     *             @OA\Property(property="password", type="string", example="Password123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200, description="Succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="token",   type="string",  example="1|abc123def..."),
     *             @OA\Property(property="user",    ref="#/components/schemas/User")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Identifiants incorrects")
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
            
            throw ValidationException::withMessages([
                'email' => ['Identifiants incorrects.'],
            ]);
        }

        RateLimiter::clear($rateLimitKey);

        $user->update(['dernier_login' => now()]);

        $token = $user->createToken('auth_token', ['*'], now()->addDays(7))->plainTextToken;

        LogAction::create([
            'user_id'      => $user->id,
            'ministere_id' => $user->ministere_id,
            'action'     => 'login',
            'module'     => 'auth',
            'ip'         => $request->getClientIp(),
            'date_action' => now(),
        ]);

        return response()->json([
            'success' => true,
            'token'   => $token,
            'user'    => [
                'id'           => $user->id,
                'name'        => $user->name,
                'prenom'      => $user->prenom,
                'email'       => $user->email,
                'role'        => $user->role,
                'ministere_id' => $user->ministere_id,
                'ministere'  => $user->ministere,
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/register",
     *     tags={"Auth"},
     *     summary="Inscription nouveau ministère",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","email","password","ministere_nom","ministere_type","sous_domaine"},
     *             @OA\Property(property="name", type="string", example="Admin"),
     *             @OA\Property(property="prenom", type="string", nullable=true, example="Jean"),
     *             @OA\Property(property="email", type="string", format="email", example="admin@crc.org"),
     *             @OA\Property(property="password", type="string", example="Password123"),
     *             @OA\Property(property="password_confirmation", type="string", example="Password123"),
     *             @OA\Property(property="ministere_nom", type="string", example="CRC Bangui"),
     *             @OA\Property(property="ministere_type", type="string", enum={"eglise","ministere","organisation","para_ecclesial","mission"}),
     *             @OA\Property(property="sous_domaine", type="string", example="crc"),
     *             @OA\Property(property="ville", type="string", nullable=true, example="Bangui"),
     *             @OA\Property(property="pays", type="string", nullable=true, example="République centrafricaine"),
     *             @OA\Property(property="email_contact", type="string", format="email", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Ministère créé")
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
            'slug'             => \Illuminate\Support\Str::slug($validated['ministere_nom']),
            'sous_domaine'     => strtolower($validated['sous_domaine']),
            'ville'           => $validated['ville'] ?? null,
            'pays'            => $validated['pays'] ?? 'République centrafricaine',
            'email_contact'  => $validated['email_contact'] ?? $validated['email'],
            'couleur_primaire'   => '#1E3A8A',
            'couleur_secondaire' => '#FFFFFF',
            'statut'         => 'actif',
        ]);

        $user = \App\Models\User::create([
            'name'         => $validated['name'],
            'prenom'       => $validated['prenom'] ?? null,
            'email'       => $validated['email'],
            'password'    => Hash::make($validated['password']),
            'role'        => 'admin_ministere',
            'ministere_id' => $ministere->id,
            'actif'        => true,
        ]);

        $token = $user->createToken('auth_token', ['*'], now()->addDays(7))->plainTextToken;

        $superAdmins = \App\Models\User::where('role', 'super_admin')->where('actif', true)->get();
        foreach ($superAdmins as $sa) {
            \App\Helpers\NotifHelper::send(
                $sa->id,
                'Nouveau ministère inscrit',
                "{$validated['ministere_nom']} vient de rejoindre EgliseHub !",
                'success',
                '/admin/ministeres',
                'ministeres'
            );
        }

        return response()->json([
            'success'   => true,
            'message'   => 'Ministère créé avec succès.',
            'token'     => $token,
            'user'      => $user->load('ministere'),
            'ministere' => $ministere,
        ], 201);
    }

    /**
     * @OA\Post(
     *     path="/logout",
     *     tags={"Auth"},
     *     summary="Déconnexion",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Déconnecté")
     * )
     */
    public function logout(Request $request)
    {
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
     *     summary="Profil utilisateur actuel",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Profil retourné")
     * )
     */
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
     *             @OA\Property(property="ancien_mot_de_passe", type="string"),
     *             @OA\Property(property="nouveau_mot_de_passe", type="string"),
     *             @OA\Property(property="nouveau_mot_de_passe_confirmation", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Mot de passe modifié")
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

        $user->tokens()->where('id', '!=', $user->currentAccessTokenId())->delete();

        return response()->json([
            'success' => true,
            'message' => 'Mot de passe mis à jour. Autres sessions déconnectées.',
        ]);
    }
}