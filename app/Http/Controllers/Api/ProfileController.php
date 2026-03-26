<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LogAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    /**
     * @OA\Get(
     *     path="/profile",
     *     tags={"Profil"},
     *     summary="Récupérer le profil de l'utilisateur connecté",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Profil retourné",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/User")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */

    // GET /api/profile
    public function show(Request $request)
    {
        $user = $request->user()->load('ministere:id,nom,sous_domaine,couleur_primaire,logo');

        return response()->json(['success' => true, 'data' => $user]);
    }

    /**
     * @OA\Put(
     *     path="/profile",
     *     tags={"Profil"},
     *     summary="Mettre à jour le profil",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Mologbama"),
     *             @OA\Property(property="prenom", type="string", example="Abishadai"),
     *             @OA\Property(property="email", type="string", format="email", example="mologbama@eglisehub.org")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Profil mis à jour",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Profil mis à jour."),
     *             @OA\Property(property="data", ref="#/components/schemas/User")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Email déjà utilisé")
     * )
     */


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


    /**
     * @OA\Post(
     *     path="/profile/change-password",
     *     tags={"Profil"},
     *     summary="Changer le mot de passe",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"ancien_mot_de_passe","nouveau_mot_de_passe","nouveau_mot_de_passe_confirmation"},
     *             @OA\Property(property="ancien_mot_de_passe", type="string", example="password123"),
     *             @OA\Property(property="nouveau_mot_de_passe", type="string", example="newpassword456"),
     *             @OA\Property(property="nouveau_mot_de_passe_confirmation", type="string", example="newpassword456")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Mot de passe changé",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Mot de passe mis à jour. Les autres sessions ont été déconnectées.")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Ancien mot de passe incorrect")
     * )
     */

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

    /**
     * @OA\Get(
     *     path="/profile/activity",
     *     tags={"Profil"},
     *     summary="Historique des activités de l'utilisateur",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des activités",
     *         @OA\JsonContent(ref="#/components/schemas/Paginated")
     *     )
     * )
     */

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
