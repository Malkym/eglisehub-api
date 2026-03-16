<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ministere;
use App\Models\LogAction;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MinistereController extends Controller
{
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
            'couleur_secondaire'=> $request->couleur_secondaire ?? '#FFFFFF',
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
            'nom', 'type', 'slug', 'sous_domaine', 'description',
            'couleur_primaire', 'couleur_secondaire',
            'email_contact', 'telephone', 'adresse', 'ville', 'pays',
            'facebook_url', 'youtube_url', 'whatsapp', 'statut',
        ]));

        $this->log($request, 'update_ministere', 'ministeres', "Modification: {$ministere->nom}");

        return response()->json([
            'success' => true,
            'message' => 'Ministère mis à jour.',
            'data'    => $ministere->fresh(),
        ]);
    }

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

    private function log(Request $request, string $action, string $module, string $details): void
    {
        LogAction::create([
            'user_id'      => $request->user()->id,
            'ministere_id' => $request->user()->ministere_id,
            'action'       => $action,
            'module'       => $module,
            'details'      => $details,
            'ip'           => $request->ip(),
            'date_action'  => now(),
        ]);
    }
}