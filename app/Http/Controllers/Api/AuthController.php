<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\LogAction;
use App\Models\Ministere;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOGIN_LOCKOUT_SECONDS = 300;

    /**
     * @OA\Post(
     *     path="/login",
     *     operationId="login",
     *     tags={"Auth"},
     *     summary="Connexion utilisateur",
     *     description="Authentifie un utilisateur et retourne un token Sanctum",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/LoginRequest")
     *     ),
     *     @OA\Response(response=200, description="Connexion réussie", @OA\JsonContent(ref="#/components/schemas/LoginResponse")),
     *     @OA\Response(response=401, description="Identifiants incorrects"),
     *     @OA\Response(response=422, description="Validation échouée"),
     *     @OA\Response(response=429, description="Trop de tentatives")
     * )
     */
    public function login(Request $request)
    {
        $rateLimitKey = 'login:' . $request->ip();

        if (RateLimiter::tooManyAttempts($rateLimitKey, self::MAX_LOGIN_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            return $this->respondWithError(
                'Trop de tentatives de connexion. Réessayez dans ' . ceil($seconds / 60) . ' minute(s).',
                429
            );
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

            return $this->respondWithError('Identifiants incorrects.', 401);
        }

        RateLimiter::clear($rateLimitKey);

        $user->update(['dernier_login' => now()]);

        $oldTokens = $user->tokens()->orderBy('created_at', 'desc')->skip(5)->take(100)->get();
        foreach ($oldTokens as $token) {
            $token->delete();
        }

        $tokenName = 'auth_token_' . $user->id . '_' . now()->format('Ymd_His');
        $token = $user->createToken($tokenName, ['*'], now()->addDays(7))->plainTextToken;

        if ($user->role === 'super_admin' && ($user->ministere_id === 0 || $user->ministere_id === null)) {
            $user->update(['ministere_id' => null]);
        }

        LogAction::create([
            'user_id'      => $user->id,
            'ministere_id' => $user->ministere_id,
            'action'       => 'login',
            'module'       => 'auth',
            'ip'           => $request->ip(),
            'date_action'  => now(),
        ]);

        return $this->respondSuccess([
            'token' => $token,
            'user'  => new UserResource($user),
        ], 'Connexion réussie');
    }

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

        $ministere = Ministere::create([
            'nom'               => $validated['ministere_nom'],
            'type'              => $validated['ministere_type'],
            'slug'              => Str::slug($validated['ministere_nom']),
            'sous_domaine'      => strtolower($validated['sous_domaine']),
            'ville'             => $validated['ville'] ?? null,
            'pays'              => $validated['pays'] ?? 'République centrafricaine',
            'email_contact'     => $validated['email_contact'] ?? $validated['email'],
            'couleur_primaire'  => '#1E3A8A',
            'couleur_secondaire' => '#FFFFFF',
            'statut'            => 'actif',
        ]);

        $user = User::create([
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

        $superAdmins = User::where('role', 'super_admin')->where('actif', true)->get();
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

        return $this->respondSuccess([
            'token'     => $token,
            'user'      => new UserResource($user),
            'ministere' => $ministere,
        ], 'Ministère créé avec succès.', 201);
    }

    /**
     * @OA\Post(
     *     path="/logout",
     *     operationId="logout",
     *     tags={"Auth"},
     *     summary="Déconnexion",
     *     description="Déconnecte l'utilisateur et révoque son token",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Déconnexion réussie"),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function logout(Request $request)
    {
        $user = $request->user();
        $tokenId = $user->currentAccessToken()->id;

        $user->tokens()->where('id', $tokenId)->delete();

        $this->log($request, 'logout', 'auth', 'Déconnexion');

        return $this->respondSuccess(null, 'Déconnexion réussie.');
    }

    /**
     * @OA\Get(
     *     path="/me",
     *     operationId="me",
     *     tags={"Auth"},
     *     summary="Profil utilisateur actuel",
     *     description="Retourne les informations de l'utilisateur connecté",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Profil retourné"),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function me(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'super_admin' && ($user->ministere_id === 0 || $user->ministere_id === null)) {
            $user->ministere_id = null;
        }

        $user->load('ministere');

        return $this->respondSuccess(['user' => new UserResource($user)]);
    }

    /**
     * @OA\Post(
     *     path="/change-password",
     *     operationId="changePassword",
     *     tags={"Auth"},
     *     summary="Changer le mot de passe",
     *     description="Permet à l'utilisateur de modifier son mot de passe",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/ChangePasswordRequest")),
     *     @OA\Response(response=200, description="Mot de passe modifié"),
     *     @OA\Response(response=422, description="Ancien mot de passe incorrect"),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function changePassword(ChangePasswordRequest $request)
    {
        $user = $request->user();

        if (!Hash::check($request->ancien_mot_de_passe, $user->password)) {
            return $this->respondWithError('Ancien mot de passe incorrect.', 422);
        }

        $user->update([
            'password' => Hash::make($request->nouveau_mot_de_passe),
        ]);

        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

        $this->log($request, 'change_password', 'auth', 'Mot de passe modifié');

        return $this->respondSuccess(null, 'Mot de passe mis à jour. Autres sessions déconnectées.');
    }

    /**
     * @OA\Post(
     *     path="/refresh-token",
     *     operationId="refreshToken",
     *     tags={"Auth"},
     *     summary="Rafraîchir le token",
     *     description="Génère un nouveau token pour l'utilisateur connecté",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Token rafraîchi"),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function refreshToken(Request $request)
    {
        $user = $request->user();

        $user->currentAccessToken()->delete();

        $tokenName = 'auth_token_' . $user->id . '_' . now()->format('Ymd_His');
        $token = $user->createToken($tokenName, ['*'], now()->addDays(7))->plainTextToken;

        return $this->respondSuccess(['token' => $token]);
    }
}