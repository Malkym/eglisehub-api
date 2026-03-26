<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ministere;
use Illuminate\Http\Request;

class DonController extends Controller
{

    /**
     * @OA\Post(
     *     path="/public/dons",
     *     tags={"Public"},
     *     summary="Faire un don en ligne",
     *     description="Enregistre une demande de don via Mobile Money (Orange, Moov, Airtel)",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"subdomain","nom","telephone","montant","type_don","operateur"},
     *             @OA\Property(property="subdomain", type="string", example="crc", description="Sous-domaine du ministère"),
     *             @OA\Property(property="nom", type="string", example="Jean Dupont", description="Nom complet du donateur"),
     *             @OA\Property(property="telephone", type="string", example="+236 74 02 67 55", description="Numéro Mobile Money"),
     *             @OA\Property(property="montant", type="number", format="float", example=5000, description="Montant du don (minimum 100 FCFA)"),
     *             @OA\Property(property="type_don", type="string", enum={"don","dime","offrande"}, example="don"),
     *             @OA\Property(property="operateur", type="string", enum={"orange","moov","airtel"}, example="orange", description="Opérateur Mobile Money")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Demande de don enregistrée",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Demande de don enregistrée. Vous allez recevoir un appel de confirmation.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Erreur de validation",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Le montant doit être d'au moins 100.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Ministère non trouvé",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Ministère introuvable.")
     *         )
     *     )
     * )
     */


    // POST /api/public/dons
    public function store(Request $request)
    {
        $request->validate([
            'subdomain'    => 'required|string',
            'nom'          => 'required|string|max:255',
            'telephone'    => 'required|string|max:20',
            'montant'      => 'required|numeric|min:100',
            'type_don'     => 'required|in:don,dime,offrande',
            'operateur'    => 'required|in:orange,moov,airtel',
        ]);

        $ministere = Ministere::where('sous_domaine', $request->subdomain)
            ->where('statut', 'actif')
            ->firstOrFail();

        // Ici on pourrait intégrer l'API Orange Money
        // Pour l'instant on enregistre la demande
        $don = \App\Models\MessageContact::create([
            'ministere_id'    => $ministere->id,
            'nom_expediteur'  => $request->nom,
            'email'           => $request->telephone . '@don.local',
            'sujet'           => "DON - {$request->type_don} - {$request->montant} FCFA via {$request->operateur}",
            'message'         => "Demande de don:\nNom: {$request->nom}\nTéléphone: {$request->telephone}\nMontant: {$request->montant} FCFA\nType: {$request->type_don}\nOpérateur: {$request->operateur}",
            'statut'          => 'non_lu',
        ]);

        // Notification à l'admin
        $admins = $ministere->utilisateurs()->get();
        foreach ($admins as $admin) {
            \App\Helpers\NotifHelper::send(
                $admin->id,
                'Nouveau don reçu',
                "{$request->nom} souhaite faire un {$request->type_don} de {$request->montant} FCFA",
                'success',
                '/messages',
                'dons'
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Demande de don enregistrée. Vous allez recevoir un appel de confirmation.',
        ]);
    }
}
