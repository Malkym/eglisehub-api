<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Evenement;
use App\Models\LogAction;
use Illuminate\Http\Request;

class EvenementController extends Controller
{
    // GET /api/ministry/events
    public function index(Request $request)
    {
        $ministereId = $this->getMinistereId($request);

        $events = Evenement::when($ministereId, fn($q) => $q->where('ministere_id', $ministereId))
            ->when($request->statut,    fn($q) => $q->where('statut', $request->statut))
            ->when($request->categorie, fn($q) => $q->where('categorie', $request->categorie))
            ->when($request->type,      fn($q) => $q->where('type', $request->type))
            ->when($request->mode,      fn($q) => $q->where('mode', $request->mode))
            ->when($request->search,    fn($q) => $q->where('titre', 'like', "%{$request->search}%"))
            ->orderBy('date_debut', 'asc')
            ->paginate(15);

        return response()->json(['success' => true, 'data' => $events]);
    }

    // POST /api/ministry/events
    public function store(Request $request)
    {
        $request->validate([
            'titre'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'image'       => 'nullable|string',
            'date_debut'  => 'required_if:categorie,ponctuel,saison|nullable|date',
            'date_fin'    => 'nullable|date|after_or_equal:date_debut',
            'lieu'        => 'nullable|string|max:255',
            'adresse_lieu' => 'nullable|string',
            'type'        => 'in:culte,conference,seminaire,autre',
            'categorie'   => 'required|in:ponctuel,recurrent,permanent,saison',
            'frequence'   => 'required_if:categorie,recurrent|in:aucune,quotidien,hebdomadaire,bimensuel,mensuel,annuel',
            'jours_semaine'       => 'required_if:frequence,hebdomadaire,bimensuel|nullable|array',
            'jours_semaine.*'     => 'in:lundi,mardi,mercredi,jeudi,vendredi,samedi,dimanche',
            'heure_debut'         => 'nullable|date_format:H:i',
            'heure_fin'           => 'nullable|date_format:H:i',
            'date_fin_recurrence' => 'nullable|date',
            'lien_streaming'      => 'nullable|url',
            'mode'                => 'in:presentiel,en_ligne,hybride',
            'capacite_max'        => 'nullable|integer|min:1',
            'inscription_requise' => 'boolean',
            'est_gratuit'         => 'boolean',
            'prix'                => 'required_if:est_gratuit,false|nullable|numeric|min:0',
            'devise'              => 'nullable|string|max:10',
            'statut'              => 'in:a_venir,en_cours,termine,annule',
        ]);

        $ministereId = $this->getMinistereId($request);

        $evenement = Evenement::create([
            'ministere_id'        => $ministereId,
            'titre'               => $request->titre,
            'description'         => $request->description,
            'image'               => $request->image,
            'date_debut'          => $request->date_debut,
            'date_fin'            => $request->date_fin,
            'lieu'                => $request->lieu,
            'adresse_lieu'        => $request->adresse_lieu,
            'type'                => $request->type ?? 'autre',
            'categorie'           => $request->categorie,
            'frequence'           => $request->frequence ?? 'aucune',
            'jours_semaine'       => $request->jours_semaine,
            'heure_debut'         => $request->heure_debut,
            'heure_fin'           => $request->heure_fin,
            'date_fin_recurrence' => $request->date_fin_recurrence,
            'lien_streaming'      => $request->lien_streaming,
            'mode'                => $request->mode ?? 'presentiel',
            'capacite_max'        => $request->capacite_max,
            'inscription_requise' => $request->inscription_requise ?? false,
            'est_gratuit'         => $request->est_gratuit ?? true,
            'prix'                => $request->est_gratuit ? null : $request->prix,
            'devise'              => $request->devise ?? 'XAF',
            'statut'              => $request->statut ?? 'a_venir',
        ]);

        $this->log($request, 'create_event', 'evenements', "Création: {$evenement->titre}");

        return response()->json([
            'success' => true,
            'message' => 'Événement créé.',
            'data'    => $evenement,
        ], 201);
    }

    // GET /api/ministry/events/{id}
    public function show(Request $request, string $id)
    {
        $evenement = $this->findForUser($request, $id);
        return response()->json(['success' => true, 'data' => $evenement]);
    }

    // PUT /api/ministry/events/{id}
    public function update(Request $request, string $id)
    {
        $evenement = $this->findForUser($request, $id);

        $request->validate([
            'titre'               => 'sometimes|string|max:255',
            'description'         => 'nullable|string',
            'image'               => 'nullable|string',
            'date_debut'          => 'nullable|date',
            'date_fin'            => 'nullable|date',
            'lieu'                => 'nullable|string|max:255',
            'adresse_lieu'        => 'nullable|string',
            'type'                => 'in:culte,conference,seminaire,autre',
            'categorie'           => 'in:ponctuel,recurrent,permanent,saison',
            'frequence'           => 'in:aucune,quotidien,hebdomadaire,bimensuel,mensuel,annuel',
            'jours_semaine'       => 'nullable|array',
            'heure_debut'         => 'nullable|date_format:H:i',
            'heure_fin'           => 'nullable|date_format:H:i',
            'date_fin_recurrence' => 'nullable|date',
            'lien_streaming'      => 'nullable|url',
            'mode'                => 'in:presentiel,en_ligne,hybride',
            'capacite_max'        => 'nullable|integer|min:1',
            'inscription_requise' => 'boolean',
            'est_gratuit'         => 'boolean',
            'prix'                => 'nullable|numeric|min:0',
            'devise'              => 'nullable|string|max:10',
            'statut'              => 'in:a_venir,en_cours,termine,annule',
        ]);

        $evenement->update($request->only([
            'titre',
            'description',
            'image',
            'date_debut',
            'date_fin',
            'lieu',
            'adresse_lieu',
            'type',
            'categorie',
            'frequence',
            'jours_semaine',
            'heure_debut',
            'heure_fin',
            'date_fin_recurrence',
            'lien_streaming',
            'mode',
            'capacite_max',
            'inscription_requise',
            'est_gratuit',
            'prix',
            'devise',
            'statut',
        ]));

        $this->log($request, 'update_event', 'evenements', "Modification: {$evenement->titre}");

        return response()->json([
            'success' => true,
            'message' => 'Événement mis à jour.',
            'data'    => $evenement->fresh(),
        ]);
    }

    // DELETE /api/ministry/events/{id}
    public function destroy(Request $request, string $id)
    {
        $evenement = $this->findForUser($request, $id);
        $titre = $evenement->titre;
        $evenement->delete();

        $this->log($request, 'delete_event', 'evenements', "Suppression: {$titre}");

        return response()->json(['success' => true, 'message' => 'Événement supprimé.']);
    }

    // PATCH /api/ministry/events/{id}/cancel
    public function cancel(Request $request, string $id)
    {
        $evenement = $this->findForUser($request, $id);
        $evenement->update(['statut' => 'annule']);

        $this->log($request, 'cancel_event', 'evenements', "Annulation: {$evenement->titre}");

        return response()->json(['success' => true, 'message' => 'Événement annulé.', 'data' => $evenement->fresh()]);
    }

    // GET /api/public/events
    public function publicIndex(Request $request)
    {
        $subdomain = $request->header('X-Subdomain') ?? $request->query('subdomain') ?? 'crc';

        $events = Evenement::whereHas(
            'ministere',
            fn($q) =>
            $q->where('sous_domaine', $subdomain)->where('statut', 'actif')
        )
            ->whereNotIn('statut', ['annule'])
            ->when($request->categorie, fn($q) => $q->where('categorie', $request->categorie))
            ->when($request->type,      fn($q) => $q->where('type', $request->type))
            ->orderBy('date_debut', 'asc')
            ->paginate(12);

        return response()->json(['success' => true, 'data' => $events]);
    }

    // GET /api/public/events/{id}
    public function publicShow(Request $request, string $id)
    {
        $subdomain = $request->header('X-Subdomain') ?? $request->query('subdomain') ?? 'crc';

        $evenement = Evenement::whereHas(
            'ministere',
            fn($q) =>
            $q->where('sous_domaine', $subdomain)->where('statut', 'actif')
        )
            ->whereNotIn('statut', ['annule'])
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $evenement]);
    }

    // Helpers
    private function getMinistereId(Request $request): ?int
    {
        // Super admin peut cibler n'importe quel ministère
        if ($request->user()->isSuperAdmin()) {
            if ($request->has('ministere_id')) {
                return (int) $request->ministere_id;
            }
            // Super admin sans ministere_id spécifié = voir tout
            return null;
        }

        return $request->user()->ministere_id;
    }

    private function findForUser(Request $request, string $id): Evenement
    {
        $evenement = Evenement::findOrFail($id);

        if (! $request->user()->isSuperAdmin()) {
            if ($evenement->ministere_id !== $request->user()->ministere_id) {
                abort(403, 'Accès refusé.');
            }
        }

        return $evenement;
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
