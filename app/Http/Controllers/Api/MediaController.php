<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\LogAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class MediaController extends Controller
{
    // GET /api/ministry/media
    public function index(Request $request)
    {
        $ministereId = $this->getMinistereId($request);

        $medias = Media::when($ministereId, fn($q) => $q->where('ministere_id', $ministereId))
            ->when($request->type,      fn($q) => $q->where('type', $request->type))
            ->when($request->categorie, fn($q) => $q->where('categorie', $request->categorie))
            ->when($request->search,    fn($q) => $q->where('nom_original', 'like', "%{$request->search}%"))
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $medias]);
    }

    // POST /api/ministry/media/upload
    public function upload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fichier'   => 'required|file|max:51200', // 50MB max
            'categorie' => 'nullable|string|max:100',
            'alt_text'  => 'nullable|string|max:255',
            'visible'   => 'boolean',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            if ($errors->has('fichier') && str_contains($errors->first('fichier'), 'max')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le fichier dépasse la taille maximale autorisée de 50 Mo. Veuillez choisir un fichier plus petit.'
                ], 422);
            }
            return response()->json(['success' => false, 'errors' => $errors], 422);
        }

        $ministereId = $this->getMinistereId($request);
        $fichier     = $request->file('fichier');

        // Vérification supplémentaire de la taille
        $fileSize = $fichier->getSize();
        $maxSize = 50 * 1024 * 1024; // 50MB
        if ($fileSize > $maxSize) {
            return response()->json([
                'success' => false,
                'message' => "Le fichier '{$fichier->getClientOriginalName()}' fait " . round($fileSize / 1024 / 1024, 2) . " Mo. La taille maximale autorisée est de 50 Mo."
            ], 422);
        }

        // Déterminer le type
        $mime = $fichier->getMimeType();
        $type = match (true) {
            str_starts_with($mime, 'image/') => 'image',
            str_starts_with($mime, 'video/') => 'video',
            str_starts_with($mime, 'audio/') => 'audio',
            default                          => 'document',
        };

        // Générer un nom de fichier unique
        $extension   = $fichier->getClientOriginalExtension();
        $nomFichier  = Str::uuid() . '.' . $extension;

        $dossier = "ministeres/{$ministereId}/{$type}s";

        // Stocker le fichier
        $chemin = $fichier->storeAs($dossier, $nomFichier, 'public');

        // Vérifier que le stockage a réussi
        if (!$chemin) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du stockage du fichier'
            ], 500);
        }

        $url = Storage::url($chemin);

        $media = Media::create([
            'ministere_id' => $ministereId,
            'nom_original' => $fichier->getClientOriginalName(),
            'nom_fichier'  => $nomFichier,
            'chemin'       => $chemin,
            'url'          => $url,
            'type'         => $type,
            'mime_type'    => $mime,
            'taille'       => $fileSize,
            'categorie'    => $request->categorie,
            'alt_text'     => $request->alt_text,
            'visible'      => $request->visible ?? true,
        ]);

        $this->log($request, 'upload_media', 'medias', "Upload: {$media->nom_original}");

        return response()->json([
            'success' => true,
            'message' => 'Fichier uploadé avec succès.',
            'data'    => $media,
        ], 201);
    }

    // PATCH /api/ministry/media/{id}/toggle-visibility
    public function toggleVisibility(Request $request, string $id)
    {
        $media = $this->findForUser($request, $id);
        $media->visible = !$media->visible;
        $media->save();

        $status = $media->visible ? 'visible' : 'masqué';

        return response()->json([
            'success' => true,
            'message' => "Le média est maintenant {$status}.",
            'data'    => $media,
        ]);
    }

    // GET /api/ministry/media/{id}
    public function show(Request $request, string $id)
    {
        $media = $this->findForUser($request, $id);
        return response()->json(['success' => true, 'data' => $media]);
    }

    // PUT /api/ministry/media/{id}
    public function update(Request $request, string $id)
    {
        $media = $this->findForUser($request, $id);

        $request->validate([
            'alt_text'  => 'nullable|string|max:255',
            'categorie' => 'nullable|string|max:100',
            'visible'   => 'boolean',
        ]);

        $media->update($request->only(['alt_text', 'categorie', 'visible']));

        return response()->json([
            'success' => true,
            'message' => 'Média mis à jour.',
            'data'    => $media->fresh(),
        ]);
    }

    // DELETE /api/ministry/media/{id}
    public function destroy(Request $request, string $id)
    {
        $media = $this->findForUser($request, $id);

        // Supprimer le fichier physique
        if (Storage::disk('public')->exists($media->chemin)) {
            Storage::disk('public')->delete($media->chemin);
        }

        $nom = $media->nom_original;
        $media->delete();

        $this->log($request, 'delete_media', 'medias', "Suppression: {$nom}");

        return response()->json(['success' => true, 'message' => 'Média supprimé.']);
    }

    // POST /api/ministry/media/bulk-delete
    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'integer',
        ]);

        $ministereId = $this->getMinistereId($request);

        $medias = Media::whereIn('id', $request->ids)
            ->where('ministere_id', $ministereId)
            ->get();

        foreach ($medias as $media) {
            if (Storage::disk('public')->exists($media->chemin)) {
                Storage::disk('public')->delete($media->chemin);
            }
            $media->delete();
        }

        return response()->json([
            'success' => true,
            'message' => "{$medias->count()} médias supprimés.",
        ]);
    }

    // GET /api/public/gallery - NOUVELLE ROUTE POUR LA GALERIE
    public function publicGallery(Request $request)
    {
        $subdomain = $request->header('X-Subdomain') ?? $request->query('subdomain') ?? 'crc';

        $medias = Media::whereHas(
            'ministere',
            fn($q) => $q->where('sous_domaine', $subdomain)->where('statut', 'actif')
        )
            ->visible() // UNIQUEMENT LES MÉDIAS VISIBLES
            ->orderBy('created_at', 'desc')
            ->paginate(24);

        return response()->json(['success' => true, 'data' => $medias]);
    }

    // GET /api/public/media (ancienne route)
    public function publicIndex(Request $request)
    {
        $subdomain = $request->header('X-Subdomain') ?? $request->query('subdomain') ?? 'crc';

        $medias = Media::whereHas(
            'ministere',
            fn($q) => $q->where('sous_domaine', $subdomain)->where('statut', 'actif')
        )
            ->visible()
            ->when($request->type,      fn($q) => $q->where('type', $request->type))
            ->when($request->categorie, fn($q) => $q->where('categorie', $request->categorie))
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $medias]);
    }

    // Helpers
    private function getMinistereId(Request $request): ?int
    {
        if ($request->user()->isSuperAdmin()) {
            if ($request->has('ministere_id')) {
                return (int) $request->ministere_id;
            }
            return null;
        }

        return $request->user()->ministere_id;
    }

    private function findForUser(Request $request, string $id): Media
    {
        $media = Media::findOrFail($id);

        if (!$request->user()->isSuperAdmin()) {
            if ($media->ministere_id !== $request->user()->ministere_id) {
                abort(403, 'Accès refusé.');
            }
        }

        return $media;
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
