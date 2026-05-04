<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Slider;
use App\Models\LogAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class SliderController extends Controller
{
    // GET /api/ministry/sliders
    public function index(Request $request)
    {
        $ministereId = $this->getMinistereId($request);

        $sliders = Slider::where('ministere_id', $ministereId)
            ->when($request->actif !== null, fn($q) => $q->where('actif', $request->actif === 'true'))
            ->orderBy('ordre')
            ->get()
            ->map(function ($slider) {
                // Ajouter les URLs complètes au moment de la réponse
                if ($slider->image) {
                    $slider->url_image = Storage::url($slider->image);
                }
                if ($slider->video_path) {
                    $slider->video_url = Storage::url($slider->video_path);
                }
                if ($slider->video_thumbnail) {
                    $slider->video_thumbnail_url = Storage::url($slider->video_thumbnail);
                }
                return $slider;
            });

        return response()->json(['success' => true, 'data' => $sliders]);
    }

    // POST /api/ministry/sliders
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'titre'          => 'required|string|max:255',
            'sous_titre'     => 'nullable|string|max:500',
            'type_media'     => 'in:image,video',
            'image'          => 'required_if:type_media,image|nullable|file|image|max:5120',
            'video'          => 'required_if:type_media,video|nullable|file|mimes:mp4,mov,avi,webm,mkv|max:20480',
            'bouton_texte'   => 'nullable|string|max:50',
            'bouton_lien'    => 'nullable|string|max:255',
            'position_texte' => 'in:gauche,centre,droite',
            'couleur_texte'  => 'nullable|string|max:7',
            'couleur_fond'   => 'nullable|string|max:7',
            'ordre'          => 'integer',
            'actif'          => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $ministereId = $this->getMinistereId($request);
        $typeMedia = $request->type_media ?? 'image';

        $data = [
            'ministere_id'   => $ministereId,
            'titre'          => $request->titre,
            'sous_titre'     => $request->sous_titre,
            'bouton_texte'   => $request->bouton_texte,
            'bouton_lien'    => $request->bouton_lien,
            'position_texte' => $request->position_texte ?? 'centre',
            'couleur_texte'  => $request->couleur_texte ?? '#FFFFFF',
            'couleur_fond'   => $request->couleur_fond,
            'ordre'          => $request->ordre ?? 0,
            'actif'          => $request->actif ?? true,
            'type_media'     => $typeMedia,
        ];

        // Gestion de l'image
        if ($typeMedia === 'image' && $request->hasFile('image')) {
            $nomFichier = Str::uuid() . '.' . $request->file('image')->extension();
            $chemin = $request->file('image')->storeAs(
                "ministeres/{$ministereId}/sliders",
                $nomFichier,
                'public'
            );
            $data['image'] = $chemin;  // Stocke le chemin relatif
        }

        // Gestion de la vidéo
        if ($typeMedia === 'video' && $request->hasFile('video')) {
            $file = $request->file('video');
            $nomFichier = Str::uuid() . '.' . $file->extension();
            $chemin = $file->storeAs(
                "ministeres/{$ministereId}/videos/sliders",
                $nomFichier,
                'public'
            );
            
            $data['video_path'] = $chemin;  // Stocke le chemin relatif
            $data['video_size'] = $file->getSize();
            $data['video_mime_type'] = $file->getMimeType();
            
            // Générer une miniature à partir de la première frame de la vidéo
            $thumbnail = $this->generateVideoThumbnail($chemin, $ministereId);
            if ($thumbnail) {
                $data['video_thumbnail'] = $thumbnail;  // Stocke le chemin relatif
            }
        }

        $slider = Slider::create($data);

        // Ajouter les URLs complètes pour la réponse
        $responseData = $slider->toArray();
        if ($slider->image) {
            $responseData['url_image'] = Storage::url($slider->image);
        }
        if ($slider->video_path) {
            $responseData['video_url'] = Storage::url($slider->video_path);
        }
        if ($slider->video_thumbnail) {
            $responseData['video_thumbnail_url'] = Storage::url($slider->video_thumbnail);
        }

        $this->log($request, 'create_slider', 'slider', "Création slide: {$slider->titre}");

        return response()->json([
            'success' => true,
            'message' => 'Slide créé.',
            'data'    => $responseData,
        ], 201);
    }

    // PUT /api/ministry/sliders/{id}
    public function update(Request $request, string $id)
    {
        $slider = $this->findForUser($request, $id);

        $validator = Validator::make($request->all(), [
            'titre'          => 'sometimes|string|max:255',
            'sous_titre'     => 'nullable|string|max:500',
            'type_media'     => 'in:image,video',
            'image'          => 'nullable|file|image|max:5120',
            'video'          => 'nullable|file|mimes:mp4,mov,avi,webm,mkv|max:20480',
            'bouton_texte'   => 'nullable|string|max:50',
            'bouton_lien'    => 'nullable|string|max:255',
            'position_texte' => 'in:gauche,centre,droite',
            'couleur_texte'  => 'nullable|string|max:7',
            'couleur_fond'   => 'nullable|string|max:7',
            'ordre'          => 'integer',
            'actif'          => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $request->only([
            'titre',
            'sous_titre',
            'bouton_texte',
            'bouton_lien',
            'position_texte',
            'couleur_texte',
            'couleur_fond',
            'ordre',
            'actif',
        ]);

        $typeMedia = $request->type_media ?? $slider->type_media;
        $data['type_media'] = $typeMedia;

        // Gestion de l'image
        if ($typeMedia === 'image' && $request->hasFile('image')) {
            // Supprimer l'ancienne image
            if ($slider->image) Storage::disk('public')->delete($slider->image);
            
            $nomFichier = Str::uuid() . '.' . $request->file('image')->extension();
            $chemin = $request->file('image')->storeAs(
                "ministeres/{$slider->ministere_id}/sliders",
                $nomFichier,
                'public'
            );
            $data['image'] = $chemin;

            // Supprimer l'ancienne vidéo si on passe de vidéo à image
            if ($slider->video_path) {
                Storage::disk('public')->delete($slider->video_path);
                if ($slider->video_thumbnail) {
                    Storage::disk('public')->delete($slider->video_thumbnail);
                }
                $data['video_path'] = null;
                $data['video_thumbnail'] = null;
                $data['video_size'] = null;
                $data['video_mime_type'] = null;
            }
        } else if ($typeMedia === 'image' && $request->type_media === 'image' && !$request->hasFile('image')) {
            // Si on reste en image mais qu'on ne change pas l'image, on garde l'existante
            $data['image'] = $slider->image;
        }

        // Gestion de la vidéo
        if ($typeMedia === 'video' && $request->hasFile('video')) {
            // Supprimer l'ancienne vidéo et ses thumbnails
            if ($slider->video_path) Storage::disk('public')->delete($slider->video_path);
            if ($slider->video_thumbnail) Storage::disk('public')->delete($slider->video_thumbnail);
            if ($slider->image) Storage::disk('public')->delete($slider->image);

            $file = $request->file('video');
            $nomFichier = Str::uuid() . '.' . $file->extension();
            $chemin = $file->storeAs(
                "ministeres/{$slider->ministere_id}/videos/sliders",
                $nomFichier,
                'public'
            );

            $data['video_path'] = $chemin;
            $data['video_size'] = $file->getSize();
            $data['video_mime_type'] = $file->getMimeType();
            $data['image'] = null;

            // Générer une miniature
            $thumbnail = $this->generateVideoThumbnail($chemin, $slider->ministere_id);
            if ($thumbnail) {
                $data['video_thumbnail'] = $thumbnail;
            }
        } else if ($typeMedia === 'video' && !$request->hasFile('video')) {
            // Si on reste en vidéo mais qu'on ne change pas la vidéo, on garde l'existante
            $data['video_path'] = $slider->video_path;
            $data['video_thumbnail'] = $slider->video_thumbnail;
        }

        $slider->update($data);

        // Ajouter les URLs complètes pour la réponse
        $responseData = $slider->fresh()->toArray();
        if ($slider->image) {
            $responseData['url_image'] = Storage::url($slider->image);
        }
        if ($slider->video_path) {
            $responseData['video_url'] = Storage::url($slider->video_path);
        }
        if ($slider->video_thumbnail) {
            $responseData['video_thumbnail_url'] = Storage::url($slider->video_thumbnail);
        }

        $this->log($request, 'update_slider', 'slider', "Modification slide: {$slider->titre}");

        return response()->json([
            'success' => true,
            'message' => 'Slide mis à jour.',
            'data'    => $responseData,
        ]);
    }

    // DELETE /api/ministry/sliders/{id}
    public function destroy(Request $request, string $id)
    {
        $slider = $this->findForUser($request, $id);

        if ($slider->image) Storage::disk('public')->delete($slider->image);
        if ($slider->video_path) Storage::disk('public')->delete($slider->video_path);
        if ($slider->video_thumbnail) Storage::disk('public')->delete($slider->video_thumbnail);

        $titre = $slider->titre;
        $slider->delete();

        $this->log($request, 'delete_slider', 'slider', "Suppression slide: {$titre}");

        return response()->json(['success' => true, 'message' => 'Slide supprimé.']);
    }

    // GET /api/ministry/sliders/{id}
    public function show(Request $request, string $id)
    {
        $slider = $this->findForUser($request, $id);
        
        $responseData = $slider->toArray();
        if ($slider->image) {
            $responseData['url_image'] = Storage::url($slider->image);
        }
        if ($slider->video_path) {
            $responseData['video_url'] = Storage::url($slider->video_path);
        }
        if ($slider->video_thumbnail) {
            $responseData['video_thumbnail_url'] = Storage::url($slider->video_thumbnail);
        }
        
        return response()->json(['success' => true, 'data' => $responseData]);
    }

    // PATCH /api/ministry/sliders/{id}/toggle
    public function toggle(Request $request, string $id)
    {
        $slider = $this->findForUser($request, $id);
        $slider->update(['actif' => !$slider->actif]);
        $etat = $slider->fresh()->actif ? 'activé' : 'désactivé';

        return response()->json([
            'success' => true,
            'message' => "Slide {$etat}.",
            'data'    => $slider->fresh(),
        ]);
    }

    // POST /api/ministry/sliders/reorder
    public function reorder(Request $request)
    {
        $request->validate([
            'items'         => 'required|array',
            'items.*.id'    => 'required|integer',
            'items.*.ordre' => 'required|integer',
        ]);

        foreach ($request->items as $item) {
            Slider::where('id', $item['id'])->update(['ordre' => $item['ordre']]);
        }

        return response()->json(['success' => true, 'message' => 'Ordre des slides mis à jour.']);
    }

    // GET /api/public/sliders — Route publique
    public function publicIndex(Request $request)
    {
        $subdomain = $request->header('X-Subdomain') ?? $request->query('subdomain') ?? 'crc';

        $sliders = Slider::whereHas(
            'ministere',
            fn($q) =>
            $q->where('sous_domaine', $subdomain)->where('statut', 'actif')
        )
            ->where('actif', true)
            ->orderBy('ordre')
            ->get()
            ->map(function ($slider) {
                $data = $slider->toArray();
                if ($slider->image) {
                    $data['url_image'] = Storage::url($slider->image);
                }
                if ($slider->video_path) {
                    $data['video_url'] = Storage::url($slider->video_path);
                }
                if ($slider->video_thumbnail) {
                    $data['video_thumbnail_url'] = Storage::url($slider->video_thumbnail);
                }
                return $data;
            });

        return response()->json(['success' => true, 'data' => $sliders]);
    }

    /**
     * GET /api/public/gallery
     * Récupère tous les médias (sliders + événements) pour la galerie
     */
    public function publicGallery(Request $request)
    {
        $subdomain = $request->header('X-Subdomain') ?? $request->query('subdomain') ?? 'crc';

        // Récupérer les sliders actifs
        $sliders = Slider::whereHas(
            'ministere',
            fn($q) =>
            $q->where('sous_domaine', $subdomain)->where('statut', 'actif')
        )
            ->where('actif', true)
            ->orderBy('ordre')
            ->get()
            ->map(function ($slider) {
                return [
                    'id' => $slider->id,
                    'titre' => $slider->titre,
                    'sous_titre' => $slider->sous_titre,
                    'type_media' => $slider->type_media ?? 'image',
                    'url_image' => $slider->image ? Storage::url($slider->image) : null,
                    'video_path' => $slider->video_path ? Storage::url($slider->video_path) : null,
                    'video_thumbnail' => $slider->video_thumbnail ? Storage::url($slider->video_thumbnail) : null,
                    'source' => 'slider',
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $sliders
        ]);
    }

    /**
     * Génère une miniature à partir d'une vidéo
     */
    private function generateVideoThumbnail(string $videoPath, int $ministereId): ?string
    {
        // Vérifier si ffmpeg est installé
        if (!function_exists('shell_exec')) {
            return null;
        }

        $fullPath = Storage::disk('public')->path($videoPath);
        $thumbnailName = Str::uuid() . '.jpg';
        $thumbnailPath = "ministeres/{$ministereId}/thumbnails/{$thumbnailName}";
        $fullThumbPath = Storage::disk('public')->path($thumbnailPath);

        // Créer le dossier si nécessaire
        $thumbDir = dirname($fullThumbPath);
        if (!file_exists($thumbDir)) {
            mkdir($thumbDir, 0755, true);
        }

        // Utiliser ffmpeg pour extraire la frame à 1 seconde
        $command = "ffmpeg -i " . escapeshellarg($fullPath) . " -ss 00:00:01 -vframes 1 -f image2 " . escapeshellarg($fullThumbPath) . " 2>&1";
        shell_exec($command);

        if (file_exists($fullThumbPath)) {
            return $thumbnailPath;
        }

        return null;
    }

    private function findForUser(Request $request, string $id): Slider
    {
        $slider = Slider::findOrFail($id);
        if (!$request->user()->isSuperAdmin()) {
            if ($slider->ministere_id !== $request->user()->ministere_id) {
                abort(403, 'Accès refusé.');
            }
        }
        return $slider;
    }
}