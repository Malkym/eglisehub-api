<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ministere;
use App\Models\LogAction;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MinistereController extends Controller
{

    /**
     * @OA\Get(
     *     path="/admin/ministeres",
     *     tags={"Ministères"},
     *     summary="Liste tous les ministères",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="statut", in="query", @OA\Schema(type="string", enum={"actif","inactif","suspendu"})),
     *     @OA\Parameter(name="type",   in="query", @OA\Schema(type="string", enum={"eglise","ministere","organisation","para_ecclesial","mission"})),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="page",   in="query", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Liste paginée",
     *         @OA\JsonContent(ref="#/components/schemas/Paginated")

     *     ),
     *     @OA\Response(response=403, description="Accès refusé — Super Admin uniquement")
     * )
     */

    // GET /api/admin/ministeres
    public function index(Request $request)
    {
        $query = Ministere::withCount(['pages', 'articles', 'evenements', 'utilisateurs']);

        if ($request->has('statut')) {
            $query->where('statut', $request->statut);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('search')) {
            $query->where('nom', 'like', '%' . $request->search . '%');
        }

        $ministeres = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json([
            'success' => true,
            'data'    => $ministeres,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/admin/ministeres",
     *     tags={"Ministères"},
     *     summary="Créer un ministère",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"nom","type","sous_domaine"},
     *             @OA\Property(property="nom",                type="string",  example="Église Victoire"),
     *             @OA\Property(property="type",               type="string",  enum={"eglise","ministere","organisation","para_ecclesial","mission"}),
     *             @OA\Property(property="sous_domaine",       type="string",  example="eglise-victoire"),
     *             @OA\Property(property="description",        type="string",  nullable=true),
     *             @OA\Property(property="couleur_primaire",   type="string",  example="#1E3A8A"),
     *             @OA\Property(property="couleur_secondaire", type="string",  example="#FFFFFF"),
     *             @OA\Property(property="email_contact",      type="string",  format="email", nullable=true),
     *             @OA\Property(property="telephone",          type="string",  nullable=true),
     *             @OA\Property(property="pays",               type="string",  example="République centrafricaine")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Ministère créé",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data",    ref="#/components/schemas/Ministere")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation échouée"),
     *     @OA\Response(response=403, description="Accès refusé")
     * )
     */

    // POST /api/admin/ministeres
    public function store(Request $request)
    {
        $request->validate([
            'nom'          => 'required|string|max:255',
            'type'         => 'required|in:eglise,ministere,organisation,para_ecclesial,mission',
            'sous_domaine' => 'required|string|max:100|unique:ministeres,sous_domaine|alpha_dash',
            'description'  => 'nullable|string',
            'couleur_primaire'   => 'nullable|string|max:7',
            'couleur_secondaire' => 'nullable|string|max:7',
            'email_contact' => 'nullable|email',
            'telephone'    => 'nullable|string|max:20',
            'adresse'      => 'nullable|string',
            'ville'        => 'nullable|string|max:100',
            'pays'         => 'nullable|string|max:100',
            'facebook_url' => 'nullable|url',
            'youtube_url'  => 'nullable|url',
            'whatsapp'     => 'nullable|string|max:20',
        ]);

        $ministere = Ministere::create([
            'nom'               => $request->nom,
            'type'              => $request->type,
            'slug'              => Str::slug($request->nom),
            'sous_domaine'      => strtolower($request->sous_domaine),
            'description'       => $request->description,
            'couleur_primaire'  => $request->couleur_primaire ?? '#1E3A8A',
            'couleur_secondaire' => $request->couleur_secondaire ?? '#FFFFFF',
            'email_contact'     => $request->email_contact,
            'telephone'         => $request->telephone,
            'adresse'           => $request->adresse,
            'ville'             => $request->ville,
            'pays'              => $request->pays ?? 'République centrafricaine',
            'facebook_url'      => $request->facebook_url,
            'youtube_url'       => $request->youtube_url,
            'whatsapp'          => $request->whatsapp,
            'statut'            => 'actif',
        ]);

        $this->log($request, 'create_ministere', 'ministeres', "Création: {$ministere->nom}");

        return response()->json([
            'success' => true,
            'message' => 'Ministère créé avec succès.',
            'data'    => $ministere,
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/admin/ministeres/{id}",
     *     tags={"Ministères"},
     *     summary="Détail d'un ministère",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Ministère trouvé",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data",    ref="#/components/schemas/Ministere")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Non trouvé")
     * )
     */

    // GET /api/admin/ministeres/{id}
    public function show(string $id)
    {
        $ministere = Ministere::withCount(['pages', 'articles', 'evenements', 'utilisateurs'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $ministere,
        ]);
    }

    /**
     * @OA\Put(
     *     path="/admin/ministeres/{id}",
     *     tags={"Ministères"},
     *     summary="Modifier un ministère",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="nom",          type="string"),
     *             @OA\Property(property="type",         type="string"),
     *             @OA\Property(property="description",  type="string"),
     *             @OA\Property(property="statut",       type="string", enum={"actif","inactif","suspendu"})
     *         )
     *     ),
     *     @OA\Response(response=200, description="Ministère modifié"),
     *     @OA\Response(response=404, description="Non trouvé")
     * )
     */

    // PUT /api/admin/ministeres/{id}
    public function update(Request $request, string $id)
    {
        $ministere = Ministere::findOrFail($id);

        $request->validate([
            'nom'          => 'sometimes|string|max:255',
            'type'         => 'sometimes|in:eglise,ministere,organisation,para_ecclesial,mission',
            'sous_domaine' => "sometimes|string|max:100|alpha_dash|unique:ministeres,sous_domaine,{$id}",
            'description'  => 'nullable|string',
            'couleur_primaire'   => 'nullable|string|max:7',
            'couleur_secondaire' => 'nullable|string|max:7',
            'email_contact' => 'nullable|email',
            'telephone'    => 'nullable|string|max:20',
            'adresse'      => 'nullable|string',
            'ville'        => 'nullable|string|max:100',
            'pays'         => 'nullable|string|max:100',
            'facebook_url' => 'nullable|url',
            'youtube_url'  => 'nullable|url',
            'whatsapp'     => 'nullable|string|max:20',
            'statut'       => 'nullable|in:actif,inactif,suspendu',
        ]);

        if ($request->has('nom')) {
            $request->merge(['slug' => Str::slug($request->nom)]);
        }

        $ministere->update($request->only([
            'nom',
            'type',
            'slug',
            'sous_domaine',
            'description',
            'couleur_primaire',
            'couleur_secondaire',
            'email_contact',
            'telephone',
            'adresse',
            'ville',
            'pays',
            'facebook_url',
            'youtube_url',
            'whatsapp',
            'statut',
        ]));

        $this->log($request, 'update_ministere', 'ministeres', "Modification: {$ministere->nom}");

        return response()->json([
            'success' => true,
            'message' => 'Ministère mis à jour.',
            'data'    => $ministere->fresh(),
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/admin/ministeres/{id}",
     *     tags={"Ministères"},
     *     summary="Désactiver un ministère",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Ministère désactivé"),
     *     @OA\Response(response=404, description="Non trouvé")
     * )
     */

    // DELETE /api/admin/ministeres/{id}
    public function destroy(Request $request, string $id)
    {
        $ministere = Ministere::findOrFail($id);
        $ministere->update(['statut' => 'inactif']);

        $this->log($request, 'delete_ministere', 'ministeres', "Désactivation: {$ministere->nom}");

        return response()->json([
            'success' => true,
            'message' => 'Ministère désactivé.',
        ]);
    }

    /**
     * @OA\Patch(
     *     path="/admin/ministeres/{id}/toggle",
     *     tags={"Ministères"},
     *     summary="Activer ou désactiver un ministère",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Statut modifié",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string",  example="Ministère actif."),
     *             @OA\Property(property="data",    ref="#/components/schemas/Ministere")
     *         )
     *     )
     * )
     */

    // PATCH /api/admin/ministeres/{id}/toggle
    public function toggle(Request $request, string $id)
    {
        $ministere = Ministere::findOrFail($id);

        $nouveauStatut = $ministere->statut === 'actif' ? 'inactif' : 'actif';
        $ministere->update(['statut' => $nouveauStatut]);

        $this->log($request, 'toggle_ministere', 'ministeres', "Toggle: {$ministere->nom} → {$nouveauStatut}");

        return response()->json([
            'success' => true,
            'message' => "Ministère {$nouveauStatut}.",
            'data'    => $ministere->fresh(),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/admin/ministeres/{id}/stats",
     *     tags={"Ministères"},
     *     summary="Statistiques d'un ministère",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Stats retournées",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data",    type="object")
     *         )
     *     )
     * )
     */

    // GET /api/admin/ministeres/{id}/stats
    public function stats(string $id)
    {
        $ministere = Ministere::findOrFail($id);

        $stats = [
            'ministere'   => $ministere->nom,
            'type'        => $ministere->type,
            'statut'      => $ministere->statut,
            'pages'       => [
                'total'    => $ministere->pages()->count(),
                'publies'  => $ministere->pages()->where('statut', 'publie')->count(),
                'brouillons' => $ministere->pages()->where('statut', 'brouillon')->count(),
            ],
            'articles'    => [
                'total'          => $ministere->articles()->count(),
                'publies'        => $ministere->articles()->where('statut', 'publie')->count(),
                'brouillons'     => $ministere->articles()->where('statut', 'brouillon')->count(),
                'vues_totales'   => $ministere->articles()->sum('vues'),
                'en_avant'       => $ministere->articles()->where('en_avant', true)->count(),
                'par_type'       => $ministere->articles()
                    ->selectRaw('type_contenu, count(*) as total')
                    ->groupBy('type_contenu')
                    ->pluck('total', 'type_contenu'),
            ],
            'evenements'  => [
                'total'      => $ministere->evenements()->count(),
                'a_venir'    => $ministere->evenements()->where('statut', 'a_venir')->count(),
                'en_cours'   => $ministere->evenements()->where('statut', 'en_cours')->count(),
                'termines'   => $ministere->evenements()->where('statut', 'termine')->count(),
                'recurrents' => $ministere->evenements()->where('categorie', 'recurrent')->count(),
                'permanents' => $ministere->evenements()->where('categorie', 'permanent')->count(),
            ],
            'utilisateurs' => [
                'total'  => $ministere->utilisateurs()->count(),
                'actifs' => $ministere->utilisateurs()->where('actif', true)->count(),
            ],
        ];

        return response()->json([
            'success' => true,
            'data'    => $stats,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/public/ministere",
     *     tags={"Public"},
     *     summary="Récupérer un ministère par sous-domaine",
     *     description="Route publique — aucune authentification requise",
     *     @OA\Parameter(name="subdomain", in="query", required=false, @OA\Schema(type="string", example="crc"),
     *         description="Sous-domaine du ministère"
     *     ),
     *     @OA\Response(response=200, description="Ministère trouvé",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data",    ref="#/components/schemas/Ministere")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Ministère non trouvé ou inactif")
     * )
     */

    // GET /api/public/ministere
    public function getBySubdomain(Request $request)
    {
        $sousdomaine = $request->header('X-Subdomain')
            ?? $request->query('subdomain')
            ?? 'crc';

        $ministere = Ministere::where('sous_domaine', $sousdomaine)
            ->where('statut', 'actif')
            ->first();

        if (! $ministere) {
            return response()->json([
                'success' => false,
                'message' => 'Ministère non trouvé.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $ministere,
        ]);
    }

private function log(Request $request, string $action, string $module, string $details, ?string $lien = null): void
{
    $log = LogAction::create([
        'user_id'      => $request->user()->id,
        'ministere_id' => $request->user()->ministere_id,
        'action'       => $action,
        'module'       => $module,
        'details'      => $details,
        'ip'           => $request->ip(),
        'date_action'  => now(),
    ]);

    // Envoyer les notifications
    $ministere = $request->user()->ministere;
    LogAction::notifyForAction($action, [
        'ministere_id' => $request->user()->ministere_id,
        'ministere_nom' => $ministere?->nom,
        'details' => $details,
        'lien' => $lien,
    ]);
}
}
