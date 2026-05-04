<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Evenement;
use App\Models\LogAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EvenementController extends Controller
{
    private const ALLOWED_IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    private const ALLOWED_VIDEO_EXTENSIONS = ['mp4', 'mov', 'avi', 'webm', 'mkv'];
    private const MAX_IMAGE_SIZE = 5120;
    private const MAX_VIDEO_SIZE = 20480;

    // GET /api/ministry/events
    public function index(Request $request)
    {
        $ministereId = $this->getMinistereId($request);

        $events = Evenement::when($ministereId, fn($q) => $q->where('ministere_id', $ministereId))
            ->when($request->statut, fn($q) => $q->where('statut', $request->statut))
            ->when($request->categorie, fn($q) => $q->where('categorie', $request->categorie))
            ->when($request->type, fn($q) => $q->where('type', $request->type))
            ->when($request->mode, fn($q) => $q->where('mode', $request->mode))
            ->when($request->search, fn($q) => $q->where('titre', 'like', "%{$request->search}%"))
            ->orderBy('date_debut', 'asc')
            ->paginate(15);

        return response()->json(['success' => true, 'data' => $events]);
    }

    // POST /api/ministry/events
    public function store(Request $request)
    {
        $validated = $request->validate([
            'titre'             => 'required|string|max:255',
            'description'       => 'nullable|string',
            'type_media'        => 'nullable|in:image,video',
            'image'             => 'nullable|file|image|max:' . self::MAX_IMAGE_SIZE,
            'video'             => 'nullable|file|mimes:' . implode(',', self::ALLOWED_VIDEO_EXTENSIONS) . '|max:' . self::MAX_VIDEO_SIZE,
            'type'             => 'required|in:culte,conference,seminaire,autre',
            'categorie'        => 'required|in:ponctuel,recurrent,permanent,saison',
            'mode'             => 'required|in:presentiel,en_ligne,hybride',
            'date_debut'       => 'nullable|date',
            'date_fin'         => 'nullable|date|after_or_equal:date_debut',
            'date_fin_recurrence' => 'nullable|date|after_or_equal:date_debut',
            'heure_debut'      => 'nullable|date_format:H:i',
            'heure_fin'        => 'nullable|date_format:H:i|after:heure_debut',
            'frequence'        => 'nullable|in:quotidien,hebdomadaire,bimensuel,mensuel,annuel',
            'jours_semaine'    => 'nullable|array',
            'jours_semaine.*'  => 'in:lundi,mardi,mercredi,jeudi,vendredi,samedi,dimanche',
            'lieu'             => 'nullable|string|max:255',
            'adresse_lieu'     => 'nullable|string',
            'lien_streaming'   => 'nullable|url',
            'capacite_max'    => 'nullable|integer|min:1',
            'inscription_requise' => 'nullable|boolean',
            'est_gratuit'      => 'nullable|boolean',
            'prix'            => 'nullable|numeric|min:0',
            'devise'          => 'nullable|string|max:10',
            'statut'          => 'nullable|in:a_venir,en_cours,termine,annule',
            'intervenant'     => 'nullable|string|max:255',
            'theme'          => 'nullable|string|max:255',
        ]);

        if (in_array($validated['categorie'], ['recurrent', 'permanent'])) {
            $request->validate([
                'frequence' => 'required|in:quotidien,hebdomadaire,bimensuel,mensuel,annuel',
                'heure_debut' => 'required|date_format:H:i',
                'heure_fin' => 'required|date_format:H:i|after:heure_debut',
            ]);

            if (($validated['frequence'] ?? null) !== 'quotidien') {
                $request->validate([
                    'jours_semaine' => 'required|array|min:1',
                    'jours_semaine.*' => 'in:lundi,mardi,mercredi,jeudi,vendredi,samedi,dimanche',
                ]);
            }
        }

        if ($validated['categorie'] === 'ponctuel') {
            $request->validate([
                'date_debut' => 'required|date',
            ]);
        }

        $ministereId = $this->getMinistereId($request);
        
        if (!$ministereId && $request->user()->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Veuillez spécifier un ministere_id'
            ], 400);
        }

        $typeMedia = $validated['type_media'] ?? 'image';
        $imagePath = null;
        $videoPath = null;
        $videoThumbnail = null;
        $videoSize = null;
        $videoMimeType = null;

        if ($typeMedia === 'image' && $request->hasFile('image')) {
            $imagePath = $this->uploadImage($request->file('image'), $ministereId);
        }

        if ($typeMedia === 'video' && $request->hasFile('video')) {
            $file = $request->file('video');
            $videoPath = $this->uploadVideo($file, $ministereId);
            $videoSize = $file->getSize();
            $videoMimeType = $file->getMimeType();
            $videoThumbnail = $this->generateVideoThumbnail($videoPath, $ministereId);
        }

        $evenement = Evenement::create([
            'ministere_id'       => $ministereId,
            'titre'            => $validated['titre'],
            'description'     => $validated['description'] ?? null,
            'image'           => $imagePath,
            'type'            => $validated['type'],
            'categorie'       => $validated['categorie'],
            'mode'            => $validated['mode'],
            'date_debut'      => $validated['date_debut'] ?? null,
            'date_fin'        => $validated['date_fin'] ?? null,
            'date_fin_recurrence' => $validated['date_fin_recurrence'] ?? null,
            'heure_debut'     => $validated['heure_debut'] ?? null,
            'heure_fin'       => $validated['heure_fin'] ?? null,
            'frequence'       => $validated['frequence'] ?? null,
            'jours_semaine'   => $validated['jours_semaine'] ?? null,
            'lieu'           => $validated['lieu'] ?? null,
            'adresse_lieu'    => $validated['adresse_lieu'] ?? null,
            'lien_streaming'  => $validated['lien_streaming'] ?? null,
            'capacite_max'   => $validated['capacite_max'] ?? null,
            'inscription_requise' => $validated['inscription_requise'] ?? false,
            'est_gratuit'    => $validated['est_gratuit'] ?? true,
            'prix'          => ($validated['est_gratuit'] ?? true) ? null : ($validated['prix'] ?? null),
            'devise'        => $validated['devise'] ?? 'XAF',
            'statut'        => $validated['statut'] ?? 'a_venir',
            'intervenant'   => $validated['intervenant'] ?? null,
            'theme'        => $validated['theme'] ?? null,
            'type_media'   => $typeMedia,
            'video_path'   => $videoPath,
            'video_thumbnail' => $videoThumbnail,
            'video_size'   => $videoSize,
            'video_mime_type' => $videoMimeType,
        ]);

        $this->log($request, 'create_event', 'evenements', "Création: {$evenement->titre}");

        return response()->json([
            'success' => true,
            'message' => 'Événement créé avec succès.',
            'data'   => $evenement,
        ], 201);
    }

    private function uploadVideo($file, int $ministereId): string
    {
        $extension = $file->getClientOriginalExtension();
        
        if (!in_array(strtolower($extension), self::ALLOWED_VIDEO_EXTENSIONS)) {
            throw new \Illuminate\Validation\ValidationException(
                validator(['video' => $file], ['video' => 'mimes:' . implode(',', self::ALLOWED_VIDEO_EXTENSIONS)]),
                ['video' => 'Extension non autorisée.']
            );
        }

        $nomFichier = Str::uuid() . '.' . $extension;
        $chemin = $file->storeAs(
            "ministeres/{$ministereId}/videos/evenements",
            $nomFichier,
            'public'
        );
        
        return $chemin;
    }

    private function generateVideoThumbnail(string $videoPath, int $ministereId): ?string
    {
        return null;
    }

    // PUT /api/ministry/events/{id}
    public function update(Request $request, string $id)
    {
        $evenement = $this->findForUser($request, $id);

        $validated = $request->validate([
            'titre'            => 'sometimes|string|max:255',
            'description'    => 'nullable|string',
            'image'        => 'nullable|file|image|max:' . self::MAX_IMAGE_SIZE,
            'type'         => 'sometimes|in:culte,conference,seminaire,autre',
            'categorie'    => 'sometimes|in:ponctuel,recurrent,permanent,saison',
            'mode'        => 'sometimes|in:presentiel,en_ligne,hybride',
            'date_debut'  => 'nullable|date',
            'date_fin'    => 'nullable|date|after_or_equal:date_debut',
            'date_fin_recurrence' => 'nullable|date|after_or_equal:date_debut',
            'heure_debut' => 'nullable|date_format:H:i',
            'heure_fin'   => 'nullable|date_format:H:i|after:heure_debut',
            'frequence'   => 'nullable|in:quotidien,hebdomadaire,bimensuel,mensuel,annuel',
            'jours_semaine' => 'nullable|array',
            'jours_semaine.*' => 'in:lundi,mardi,mercredi,jeudi,vendredi,samedi,dimanche',
            'lieu'       => 'nullable|string|max:255',
            'adresse_lieu' => 'nullable|string',
            'lien_streaming' => 'nullable|url',
            'capacite_max' => 'nullable|integer|min:1',
            'inscription_requise' => 'nullable|boolean',
            'est_gratuit' => 'nullable|boolean',
            'prix' => 'nullable|numeric|min:0',
            'devise' => 'nullable|string|max:10',
            'statut' => 'nullable|in:a_venir,en_cours,termine,annule',
            'intervenant' => 'nullable|string|max:255',
            'theme' => 'nullable|string|max:255',
        ]);

        if ($request->has('categorie') && in_array($request->categorie, ['recurrent', 'permanent'])) {
            if ($request->has('frequence') && $request->frequence !== 'quotidien') {
                $request->validate([
                    'jours_semaine' => 'required|array|min:1',
                ]);
            }
        }

        if ($request->has('est_gratuit')) {
            $validated['prix'] = $request->est_gratuit ? null : ($request->prix ?? null);
        }

        if ($request->hasFile('image')) {
            if ($evenement->image) {
                Storage::disk('public')->delete($evenement->image);
            }
            $validated['image'] = $this->uploadImage($request->file('image'), $evenement->ministere_id);
        }

        $evenement->update($validated);

        $this->log($request, 'update_event', 'evenements', "Modification: {$evenement->titre}");

        return response()->json([
            'success' => true,
            'message' => 'Événement mis à jour avec succès.',
            'data'   => $evenement->fresh(),
        ]);
    }

    // DELETE /api/ministry/events/{id}
    public function destroy(Request $request, string $id)
    {
        $evenement = $this->findForUser($request, $id);

        if ($evenement->image) {
            Storage::disk('public')->delete($evenement->image);
        }

        $titre = $evenement->titre;
        $evenement->delete();

        $this->log($request, 'delete_event', 'evenements', "Suppression: {$titre}");

        return response()->json([
            'success' => true,
            'message' => 'Événement supprimé avec succès.'
        ]);
    }

    // GET /api/ministry/events/{id}
    public function show(Request $request, string $id)
    {
        $evenement = $this->findForUser($request, $id);
        
        return response()->json(['success' => true, 'data' => $evenement]);
    }

    // PATCH /api/ministry/events/{id}/cancel
    public function cancel(Request $request, string $id)
    {
        $evenement = $this->findForUser($request, $id);
        $evenement->update(['statut' => 'annule']);

        $this->log($request, 'cancel_event', 'evenements', "Annulation: {$evenement->titre}");

        return response()->json([
            'success' => true,
            'message' => 'Événement annulé avec succès.',
            'data'   => $evenement->fresh()
        ]);
    }

    // GET /api/public/events
    public function publicIndex(Request $request)
    {
        $subdomain = $request->header('X-Subdomain') ?? $request->query('subdomain') ?? 'crc';

        $events = Evenement::whereHas(
            'ministere',
            fn($q) => $q->where('sous_domaine', $subdomain)->where('statut', 'actif')
        )
            ->whereNotIn('statut', ['annule'])
            ->when($request->categorie, fn($q) => $q->where('categorie', $request->categorie))
            ->when($request->type, fn($q) => $q->where('type', $request->type))
            ->when($request->mode, fn($q) => $q->where('mode', $request->mode))
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
            fn($q) => $q->where('sous_domaine', $subdomain)->where('statut', 'actif')
        )
            ->whereNotIn('statut', ['annule'])
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $evenement]);
    }

    private function uploadImage($file, int $ministereId): string
    {
        $extension = $file->getClientOriginalExtension();
        
        if (!in_array(strtolower($extension), self::ALLOWED_IMAGE_EXTENSIONS)) {
            throw new \Illuminate\Validation\ValidationException(
                validator(['image' => $file], ['image' => 'image']),
                ['image' => 'Extension non autorisée.']
            );
        }

        $nomFichier = Str::uuid() . '.' . $extension;
        $chemin = $file->storeAs(
            "ministeres/{$ministereId}/evenements",
            $nomFichier,
            'public'
        );
        
        return $chemin;
    }

    protected function getMinistereId(Request $request): ?int
    {
        if ($request->user()->isSuperAdmin()) {
            if ($request->has('ministere_id')) {
                return (int) $request->ministere_id;
            }
            return null;
        }

        return $request->user()->ministere_id;
    }

    protected function findForUser(Request $request, string $id): Evenement
    {
        $evenement = Evenement::findOrFail($id);

        if (!$request->user()->isSuperAdmin()) {
            if ($evenement->ministere_id !== $request->user()->ministere_id) {
                abort(403, 'Accès refusé.');
            }
        }

        return $evenement;
    }
}