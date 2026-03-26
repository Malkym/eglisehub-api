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
        $validated = $request->validate([
            'titre'               => 'required|string|max:255',
            'description'         => 'nullable|string',
            'type_media'          => 'in:image,video',
            'image'               => 'required_if:type_media,image|nullable|file|image|max:5120',
            'video'               => 'required_if:type_media,video|nullable|file|mimes:mp4,mov,avi,webm,mkv|max:20480',
            'type'                => 'required|in:culte,conference,seminaire,autre',
            'categorie'           => 'required|in:ponctuel,recurrent,permanent,saison',
            'mode'                => 'required|in:presentiel,en_ligne,hybride',
            'date_debut'          => 'nullable|date',
            'date_fin'            => 'nullable|date|after_or_equal:date_debut',
            'date_fin_recurrence' => 'nullable|date|after_or_equal:date_debut',
            'heure_debut'         => 'nullable|date_format:H:i',
            'heure_fin'           => 'nullable|date_format:H:i|after:heure_debut',
            'frequence'           => 'nullable|in:quotidien,hebdomadaire,bimensuel,mensuel,annuel',
            'jours_semaine'       => 'nullable|array',
            'jours_semaine.*'     => 'in:lundi,mardi,mercredi,jeudi,vendredi,samedi,dimanche',
            'lieu'                => 'nullable|string|max:255',
            'adresse_lieu'        => 'nullable|string',
            'lien_streaming'      => 'nullable|url',
            'capacite_max'        => 'nullable|integer|min:1',
            'inscription_requise' => 'boolean',
            'est_gratuit'         => 'boolean',
            'prix'                => 'nullable|numeric|min:0',
            'devise'              => 'nullable|string|max:10',
            'statut'              => 'nullable|in:a_venir,en_cours,termine,annule',
            'intervenant'         => 'nullable|string|max:255',
            'theme'               => 'nullable|string|max:255',
        ]);


        // Validation conditionnelle selon la catégorie
        if ($validated['categorie'] === 'recurrent' || $validated['categorie'] === 'permanent') {
            $request->validate([
                'frequence' => 'required|in:quotidien,hebdomadaire,bimensuel,mensuel,annuel',
                'heure_debut' => 'required|date_format:H:i',
                'heure_fin' => 'required|date_format:H:i|after:heure_debut',
            ]);

            // Pour les fréquences non quotidiennes, les jours sont obligatoires
            if ($validated['frequence'] !== 'quotidien' && isset($validated['frequence'])) {
                $request->validate([
                    'jours_semaine' => 'required|array|min:1',
                    'jours_semaine.*' => 'in:lundi,mardi,mercredi,jeudi,vendredi,samedi,dimanche',
                ]);
            }
        }

        // Pour les événements ponctuels, date_debut est obligatoire
        if ($validated['categorie'] === 'ponctuel') {
            $request->validate([
                'date_debut' => 'required|date',
            ]);
        }

        $ministereId = $this->getMinistereId($request);
        // Si super admin et pas de ministere_id spécifié, retourner erreur
        if (!$ministereId && $request->user()->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Veuillez spécifier un ministere_id'
            ], 400);
        }
        $typeMedia = $request->type_media ?? 'image';

        // Gestion des médias
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
            'ministere_id'        => $ministereId,
            'titre'               => $validated['titre'],
            'description'         => $validated['description'] ?? null,
            'image'               => $imagePath,
            'type'                => $validated['type'],
            'categorie'           => $validated['categorie'],
            'mode'                => $validated['mode'],
            'date_debut'          => $validated['date_debut'] ?? null,
            'date_fin'            => $validated['date_fin'] ?? null,
            'date_fin_recurrence' => $validated['date_fin_recurrence'] ?? null,
            'heure_debut'         => $validated['heure_debut'] ?? null,
            'heure_fin'           => $validated['heure_fin'] ?? null,
            'frequence'           => $validated['frequence'] ?? null,
            'jours_semaine'       => $validated['jours_semaine'] ?? null,
            'lieu'                => $validated['lieu'] ?? null,
            'adresse_lieu'        => $validated['adresse_lieu'] ?? null,
            'lien_streaming'      => $validated['lien_streaming'] ?? null,
            'capacite_max'        => $validated['capacite_max'] ?? null,
            'inscription_requise' => $validated['inscription_requise'] ?? false,
            'est_gratuit'         => $validated['est_gratuit'] ?? true,
            'prix'                => ($validated['est_gratuit'] ?? true) ? null : ($validated['prix'] ?? null),
            'devise'              => $validated['devise'] ?? 'XAF',
            'statut'              => $validated['statut'] ?? 'a_venir',
            'intervenant'         => $validated['intervenant'] ?? null,
            'theme'               => $validated['theme'] ?? null,
            'type_media'          => $typeMedia,
            'video_path'          => $videoPath,
            'video_thumbnail'     => $videoThumbnail,
            'video_size'          => $videoSize,
            'video_mime_type'     => $videoMimeType,
        ]);


        $this->log($request, 'create_event', 'evenements', "Création: {$evenement->titre}");

        return response()->json([
            'success' => true,
            'message' => 'Événement créé avec succès.',
            'data'    => $evenement,
        ], 201);
    }

    /**
     * Uploader une vidéo
     */
    private function uploadVideo($file, int $ministereId): string
    {
        $nomFichier = Str::uuid() . '.' . $file->extension();
        $chemin = $file->storeAs(
            "ministeres/{$ministereId}/videos/evenements",
            $nomFichier,
            'public'
        );
        return $chemin;
    }

    /**
     * Génère une miniature à partir d'une vidéo
     */
    private function generateVideoThumbnail(string $videoPath, int $ministereId): ?string
    {
        if (!function_exists('shell_exec')) {
            return null;
        }

        $fullPath = Storage::disk('public')->path($videoPath);
        $thumbnailName = Str::uuid() . '.jpg';
        $thumbnailPath = "ministeres/{$ministereId}/thumbnails/{$thumbnailName}";
        $fullThumbPath = Storage::disk('public')->path($thumbnailPath);

        $thumbDir = dirname($fullThumbPath);
        if (!file_exists($thumbDir)) {
            mkdir($thumbDir, 0755, true);
        }

        $command = "ffmpeg -i " . escapeshellarg($fullPath) . " -ss 00:00:01 -vframes 1 -f image2 " . escapeshellarg($fullThumbPath) . " 2>&1";
        shell_exec($command);

        if (file_exists($fullThumbPath)) {
            return $thumbnailPath;
        }

        return null;
    }

    // PUT /api/ministry/events/{id}
    public function update(Request $request, string $id)
    {
        $evenement = $this->findForUser($request, $id);

        $validated = $request->validate([
            'titre'               => 'sometimes|string|max:255',
            'description'         => 'nullable|string',
            'image'               => 'nullable|file|image|max:5120',
            'type'                => 'sometimes|in:culte,conference,seminaire,autre',
            'categorie'           => 'sometimes|in:ponctuel,recurrent,permanent,saison',
            'mode'                => 'sometimes|in:presentiel,en_ligne,hybride',
            'date_debut'          => 'nullable|date',
            'date_fin'            => 'nullable|date|after_or_equal:date_debut',
            'date_fin_recurrence' => 'nullable|date|after_or_equal:date_debut',
            'heure_debut'         => 'nullable|date_format:H:i',
            'heure_fin'           => 'nullable|date_format:H:i|after:heure_debut',
            'frequence'           => 'nullable|in:quotidien,hebdomadaire,bimensuel,mensuel,annuel',
            'jours_semaine'       => 'nullable|array',
            'jours_semaine.*'     => 'in:lundi,mardi,mercredi,jeudi,vendredi,samedi,dimanche',
            'lieu'                => 'nullable|string|max:255',
            'adresse_lieu'        => 'nullable|string',
            'lien_streaming'      => 'nullable|url',
            'capacite_max'        => 'nullable|integer|min:1',
            'inscription_requise' => 'boolean',
            'est_gratuit'         => 'boolean',
            'prix'                => 'nullable|numeric|min:0',
            'devise'              => 'nullable|string|max:10',
            'statut'              => 'nullable|in:a_venir,en_cours,termine,annule',
            'intervenant'         => 'nullable|string|max:255',
            'theme'               => 'nullable|string|max:255',
        ]);

        // Validation conditionnelle si la catégorie change
        if ($request->has('categorie') && ($request->categorie === 'recurrent' || $request->categorie === 'permanent')) {
            if ($request->has('frequence') && $request->frequence !== 'quotidien') {
                $request->validate([
                    'jours_semaine' => 'required|array|min:1',
                ]);
            }
        }

        // Gestion du prix en fonction de est_gratuit
        if ($request->has('est_gratuit')) {
            $validated['prix'] = $request->est_gratuit ? null : ($request->prix ?? null);
        }

        // Gestion de l'image
        if ($request->hasFile('image')) {
            // Supprimer l'ancienne image
            if ($evenement->image) {
                Storage::disk('public')->delete($evenement->image);
            }
            $validated['image'] = $this->uploadImage(
                $request->file('image'),
                $evenement->ministere_id
            );
        }

        $evenement->update($validated);

        $this->log($request, 'update_event', 'evenements', "Modification: {$evenement->titre}");

        return response()->json([
            'success' => true,
            'message' => 'Événement mis à jour avec succès.',
            'data'    => $evenement->fresh(),
        ]);
    }

    // DELETE /api/ministry/events/{id}
    public function destroy(Request $request, string $id)
    {
        $evenement = $this->findForUser($request, $id);

        // Supprimer l'image associée
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
            'data' => $evenement->fresh()
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
            ->when($request->type,      fn($q) => $q->where('type', $request->type))
            ->when($request->mode,      fn($q) => $q->where('mode', $request->mode))
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

    // Méthode privée pour uploader l'image
    private function uploadImage($file, int $ministereId): string
    {
        $nomFichier = Str::uuid() . '.' . $file->extension();
        $chemin = $file->storeAs(
            "ministeres/{$ministereId}/evenements",
            $nomFichier,
            'public'
        );
        return $chemin;
    }

    // Helper pour obtenir le ministere_id
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

    // Helper pour trouver un événement avec vérification des droits
    private function findForUser(Request $request, string $id): Evenement
    {
        $evenement = Evenement::findOrFail($id);

        if (!$request->user()->isSuperAdmin()) {
            if ($evenement->ministere_id !== $request->user()->ministere_id) {
                abort(403, 'Accès refusé.');
            }
        }

        return $evenement;
    }

    // Helper pour logger les actions
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
