<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\LogAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
        $request->validate([
            'fichier'   => 'required|file|max:20480', // 20MB max (augmenté)
            'categorie' => 'nullable|string|max:100',
            'alt_text'  => 'nullable|string|max:255',
        ]);


        $ministereId = $this->getMinistereId($request);
        $fichier     = $request->file('fichier');

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
        $dossier     = "ministeres/{$ministereId}/{$type}s";

        // Stocker le fichier
        $chemin = $fichier->storeAs($dossier, $nomFichier, 'public');
        $url    = Storage::url($chemin);

        $media = Media::create([
            'ministere_id' => $ministereId,
            'nom_original' => $fichier->getClientOriginalName(),
            'nom_fichier'  => $nomFichier,
            'chemin'       => $chemin,
            'url'          => $url,
            'type'         => $type,
            'mime_type'    => $mime,
            'taille'       => $fichier->getSize(),
            'categorie'    => $request->categorie,
            'alt_text'     => $request->alt_text,
        ]);

        $this->log($request, 'upload_media', 'medias', "Upload: {$media->nom_original}");

        return response()->json([
            'success' => true,
            'message' => 'Fichier uploadé.',
            'data'    => $media,
        ], 201);
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
        ]);

        $media->update($request->only(['alt_text', 'categorie']));

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

    // GET /api/public/media
    public function publicIndex(Request $request)
    {
        $subdomain = $request->header('X-Subdomain') ?? $request->query('subdomain') ?? 'crc';

        $medias = Media::whereHas(
            'ministere',
            fn($q) =>
            $q->where('sous_domaine', $subdomain)->where('statut', 'actif')
        )
            ->when($request->type,      fn($q) => $q->where('type', $request->type))
            ->when($request->categorie, fn($q) => $q->where('categorie', $request->categorie))
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $medias]);
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
    private function findForUser(Request $request, string $id): Media
    {
        $media = Media::findOrFail($id);

        if (! $request->user()->isSuperAdmin()) {
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
