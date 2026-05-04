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
    private const ALLOWED_IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    private const ALLOWED_VIDEO_EXTENSIONS = ['mp4', 'webm'];
    private const ALLOWED_AUDIO_EXTENSIONS = ['mp3', 'wav', 'ogg'];
    private const ALLOWED_DOCUMENT_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];
    private const MAX_FILE_SIZE = 51200;

    // GET /api/ministry/media
    public function index(Request $request)
    {
        $ministereId = $this->getMinistereId($request);

        $medias = Media::when($ministereId, fn($q) => $q->where('ministere_id', $ministereId))
            ->when($request->type, fn($q) => $q->where('type', $request->type))
            ->when($request->categorie, fn($q) => $q->where('categorie', $request->categorie))
            ->when($request->search, fn($q) => $q->where('nom_original', 'like', "%{$request->search}%"))
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $medias]);
    }

    // POST /api/ministry/media/upload
    public function upload(Request $request)
    {
        $validated = $request->validate([
            'fichier'   => 'required|file|max:' . self::MAX_FILE_SIZE,
            'categorie' => 'nullable|string|max:100',
            'alt_text'  => 'nullable|string|max:255',
            'visible'   => 'nullable|boolean',
        ]);

        $ministereId = $this->getMinistereId($request);
        $fichier = $request->file('fichier');

        $extension = strtolower($fichier->getClientOriginalExtension());
        $mime = $fichier->getMimeType();

        $type = $this->determineFileType($mime, $extension);
        
        if (!$this->isAllowedExtension($extension, $type)) {
            return response()->json([
                'success' => false,
                'message' => 'Extension de fichier non autorisée: .' . $extension,
            ], 422);
        }

        $fileSize = $fichier->getSize();
        if ($fileSize > self::MAX_FILE_SIZE * 1024) {
            return response()->json([
                'success' => false,
                'message' => 'Le fichier dépasse la taille maximale autorisée de ' . (self::MAX_FILE_SIZE / 1024) . ' Mo.',
            ], 422);
        }

        $nomFichier = Str::uuid() . '.' . $extension;
        $dossier = "ministeres/{$ministereId}/{$type}s";
        $chemin = $fichier->storeAs($dossier, $nomFichier, 'public');

        if (!$chemin) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du stockage du fichier',
            ], 500);
        }

        $url = Storage::url($chemin);

        $media = Media::create([
            'ministere_id'  => $ministereId,
            'nom_original' => $fichier->getClientOriginalName(),
            'nom_fichier'  => $nomFichier,
            'chemin'     => $chemin,
            'url'        => $url,
            'type'       => $type,
            'mime_type' => $mime,
            'taille'    => $fileSize,
            'categorie' => $validated['categorie'] ?? null,
            'alt_text'  => $validated['alt_text'] ?? null,
            'visible'  => $validated['visible'] ?? true,
        ]);

        $this->log($request, 'upload_media', 'medias', "Upload: {$media->nom_original}");

        return response()->json([
            'success' => true,
            'message' => 'Fichier uploadé avec succès.',
            'data'   => $media,
        ], 201);
    }

    // PATCH /api/ministry/media/{id}/toggle-visibility
    public function toggleVisibility(Request $request, string $id)
    {
        $media = $this->findForUser($request, $id);
        
        $media->update(['visible' => !$media->visible]);
        
        $status = $media->fresh()->visible ? 'visible' : 'masqué';

        return response()->json([
            'success' => true,
            'message' => "Le média est maintenant {$status}.",
            'data'   => $media->fresh(),
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

        $validated = $request->validate([
            'alt_text'  => 'nullable|string|max:255',
            'categorie' => 'nullable|string|max:100',
            'visible'   => 'nullable|boolean',
        ]);

        $media->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Média mis à jour.',
            'data'   => $media->fresh(),
        ]);
    }

    // DELETE /api/ministry/media/{id}
    public function destroy(Request $request, string $id)
    {
        $media = $this->findForUser($request, $id);

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
        $validated = $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'integer|exists:medias,id',
        ]);

        $ministereId = $this->getMinistereId($request);

        $medias = Media::whereIn('id', $validated['ids'])
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

    // GET /api/public/gallery
    public function publicGallery(Request $request)
    {
        $subdomain = $request->header('X-Subdomain') ?? $request->query('subdomain') ?? 'crc';

        $medias = Media::whereHas(
            'ministere',
            fn($q) => $q->where('sous_domaine', $subdomain)->where('statut', 'actif')
        )
            ->where('visible', true)
            ->orderBy('created_at', 'desc')
            ->paginate(24);

        return response()->json(['success' => true, 'data' => $medias]);
    }

    // GET /api/public/media
    public function publicIndex(Request $request)
    {
        $subdomain = $request->header('X-Subdomain') ?? $request->query('subdomain') ?? 'crc';

        $medias = Media::whereHas(
            'ministere',
            fn($q) => $q->where('sous_domaine', $subdomain)->where('statut', 'actif')
        )
            ->where('visible', true)
            ->when($request->type, fn($q) => $q->where('type', $request->type))
            ->when($request->categorie, fn($q) => $q->where('categorie', $request->categorie))
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $medias]);
    }

    private function determineFileType(string $mime, string $extension): string
    {
        if (str_starts_with($mime, 'image/')) {
            return 'image';
        }
        if (str_starts_with($mime, 'video/')) {
            return 'video';
        }
        if (str_starts_with($mime, 'audio/')) {
            return 'audio';
        }
        return 'document';
    }

    private function isAllowedExtension(string $extension, string $type): bool
    {
        $allowed = match ($type) {
            'image' => self::ALLOWED_IMAGE_EXTENSIONS,
            'video' => self::ALLOWED_VIDEO_EXTENSIONS,
            'audio' => self::ALLOWED_AUDIO_EXTENSIONS,
            'document' => self::ALLOWED_DOCUMENT_EXTENSIONS,
            default => false,
        };

        return in_array($extension, $allowed);
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
}