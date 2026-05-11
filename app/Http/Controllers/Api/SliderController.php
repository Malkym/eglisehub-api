<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Slider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class SliderController extends Controller
{
    public function index(Request $request)
    {
        $ministereId = $this->getMinistereId($request);

        $sliders = Slider::where('ministere_id', $ministereId)
            ->when($request->actif !== null, fn($q) => $q->where('actif', $request->actif === 'true'))
            ->orderBy('ordre')
            ->get()
            ->map(function ($slider) {
                if ($slider->image) $slider->url_image = Storage::url($slider->image);
                if ($slider->video_path) $slider->video_url = Storage::url($slider->video_path);
                if ($slider->video_thumbnail) $slider->video_thumbnail_url = Storage::url($slider->video_thumbnail);
                return $slider;
            });

        return $this->respondSuccess($sliders);
    }

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
            return $this->respondWithError('Validation échouée', 422, $validator->errors()->toArray());
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

        if ($typeMedia === 'image' && $request->hasFile('image')) {
            $nomFichier = Str::uuid() . '.' . $request->file('image')->extension();
            $chemin = $request->file('image')->storeAs("ministeres/{$ministereId}/sliders", $nomFichier, 'public');
            $data['image'] = $chemin;
        }

        if ($typeMedia === 'video' && $request->hasFile('video')) {
            $file = $request->file('video');
            $nomFichier = Str::uuid() . '.' . $file->extension();
            $chemin = $file->storeAs("ministeres/{$ministereId}/videos/sliders", $nomFichier, 'public');
            $data['video_path'] = $chemin;
            $data['video_size'] = $file->getSize();
            $data['video_mime_type'] = $file->getMimeType();
            $thumbnail = $this->generateVideoThumbnail($chemin, $ministereId);
            if ($thumbnail) $data['video_thumbnail'] = $thumbnail;
        }

        $slider = Slider::create($data);

        $responseData = $slider->toArray();
        if ($slider->image) $responseData['url_image'] = Storage::url($slider->image);
        if ($slider->video_path) $responseData['video_url'] = Storage::url($slider->video_path);
        if ($slider->video_thumbnail) $responseData['video_thumbnail_url'] = Storage::url($slider->video_thumbnail);

        $this->log($request, 'create_slider', 'slider', "Création slide: {$slider->titre}");

        return $this->respondSuccess($responseData, 'Slide créé.', 201);
    }

    public function update(Request $request, string $id)
    {
        $slider = $this->findForMinistere($request, Slider::class, $id);

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
            return $this->respondWithError('Validation échouée', 422, $validator->errors()->toArray());
        }

        $data = $request->only(['titre', 'sous_titre', 'bouton_texte', 'bouton_lien', 'position_texte', 'couleur_texte', 'couleur_fond', 'ordre', 'actif']);
        $typeMedia = $request->type_media ?? $slider->type_media;
        $data['type_media'] = $typeMedia;

        if ($typeMedia === 'image' && $request->hasFile('image')) {
            if ($slider->image) Storage::disk('public')->delete($slider->image);
            $nomFichier = Str::uuid() . '.' . $request->file('image')->extension();
            $chemin = $request->file('image')->storeAs("ministeres/{$slider->ministere_id}/sliders", $nomFichier, 'public');
            $data['image'] = $chemin;
            if ($slider->video_path) {
                Storage::disk('public')->delete($slider->video_path);
                if ($slider->video_thumbnail) Storage::disk('public')->delete($slider->video_thumbnail);
                $data['video_path'] = null;
                $data['video_thumbnail'] = null;
                $data['video_size'] = null;
                $data['video_mime_type'] = null;
            }
        }

        if ($typeMedia === 'video' && $request->hasFile('video')) {
            if ($slider->video_path) Storage::disk('public')->delete($slider->video_path);
            if ($slider->video_thumbnail) Storage::disk('public')->delete($slider->video_thumbnail);
            if ($slider->image) Storage::disk('public')->delete($slider->image);
            $file = $request->file('video');
            $nomFichier = Str::uuid() . '.' . $file->extension();
            $chemin = $file->storeAs("ministeres/{$slider->ministere_id}/videos/sliders", $nomFichier, 'public');
            $data['video_path'] = $chemin;
            $data['video_size'] = $file->getSize();
            $data['video_mime_type'] = $file->getMimeType();
            $data['image'] = null;
            $thumbnail = $this->generateVideoThumbnail($chemin, $slider->ministere_id);
            if ($thumbnail) $data['video_thumbnail'] = $thumbnail;
        }

        $slider->update($data);

        $responseData = $slider->fresh()->toArray();
        if ($slider->image) $responseData['url_image'] = Storage::url($slider->image);
        if ($slider->video_path) $responseData['video_url'] = Storage::url($slider->video_path);
        if ($slider->video_thumbnail) $responseData['video_thumbnail_url'] = Storage::url($slider->video_thumbnail);

        $this->log($request, 'update_slider', 'slider', "Modification slide: {$slider->titre}");

        return $this->respondSuccess($responseData, 'Slide mis à jour.');
    }

    public function destroy(Request $request, string $id)
    {
        $slider = $this->findForMinistere($request, Slider::class, $id);

        if ($slider->image) Storage::disk('public')->delete($slider->image);
        if ($slider->video_path) Storage::disk('public')->delete($slider->video_path);
        if ($slider->video_thumbnail) Storage::disk('public')->delete($slider->video_thumbnail);

        $titre = $slider->titre;
        $slider->delete();

        $this->log($request, 'delete_slider', 'slider', "Suppression slide: {$titre}");

        return $this->respondSuccess(null, 'Slide supprimé.');
    }

    public function show(Request $request, string $id)
    {
        $slider = $this->findForMinistere($request, Slider::class, $id);

        $responseData = $slider->toArray();
        if ($slider->image) $responseData['url_image'] = Storage::url($slider->image);
        if ($slider->video_path) $responseData['video_url'] = Storage::url($slider->video_path);
        if ($slider->video_thumbnail) $responseData['video_thumbnail_url'] = Storage::url($slider->video_thumbnail);

        return $this->respondSuccess($responseData);
    }

    public function toggle(Request $request, string $id)
    {
        $slider = $this->findForMinistere($request, Slider::class, $id);
        $slider->update(['actif' => !$slider->actif]);
        $etat = $slider->fresh()->actif ? 'activé' : 'désactivé';

        return $this->respondSuccess($slider->fresh(), "Slide {$etat}.");
    }

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

        return $this->respondSuccess(null, 'Ordre des slides mis à jour.');
    }

    public function publicIndex(Request $request)
    {
        $subdomain = $this->resolveSubdomain($request);

        $sliders = Slider::whereHas(
            'ministere',
            fn($q) => $q->where('sous_domaine', $subdomain)->where('statut', 'actif')
        )
            ->where('actif', true)
            ->orderBy('ordre')
            ->get()
            ->map(function ($slider) {
                $data = $slider->toArray();
                if ($slider->image) $data['url_image'] = Storage::url($slider->image);
                if ($slider->video_path) $data['video_url'] = Storage::url($slider->video_path);
                if ($slider->video_thumbnail) $data['video_thumbnail_url'] = Storage::url($slider->video_thumbnail);
                return $data;
            });

        return $this->respondSuccess($sliders);
    }

    public function publicGallery(Request $request)
    {
        $subdomain = $this->resolveSubdomain($request);

        $sliders = Slider::whereHas(
            'ministere',
            fn($q) => $q->where('sous_domaine', $subdomain)->where('statut', 'actif')
        )
            ->where('actif', true)
            ->orderBy('ordre')
            ->get()
            ->map(function ($slider) {
                return [
                    'id'               => $slider->id,
                    'titre'            => $slider->titre,
                    'sous_titre'       => $slider->sous_titre,
                    'type_media'       => $slider->type_media ?? 'image',
                    'url_image'        => $slider->image ? Storage::url($slider->image) : null,
                    'video_path'       => $slider->video_path ? Storage::url($slider->video_path) : null,
                    'video_thumbnail'  => $slider->video_thumbnail ? Storage::url($slider->video_thumbnail) : null,
                    'source'           => 'slider',
                ];
            });

        return $this->respondSuccess($sliders);
    }

    private function generateVideoThumbnail(string $videoPath, int $ministereId): ?string
    {
        if (!function_exists('shell_exec')) return null;

        $fullPath = Storage::disk('public')->path($videoPath);
        $thumbnailName = Str::uuid() . '.jpg';
        $thumbnailPath = "ministeres/{$ministereId}/thumbnails/{$thumbnailName}";
        $fullThumbPath = Storage::disk('public')->path($thumbnailPath);

        $thumbDir = dirname($fullThumbPath);
        if (!file_exists($thumbDir)) mkdir($thumbDir, 0755, true);

        $command = "ffmpeg -i " . escapeshellarg($fullPath) . " -ss 00:00:01 -vframes 1 -f image2 " . escapeshellarg($fullThumbPath) . " 2>&1";
        shell_exec($command);

        if (file_exists($fullThumbPath)) return $thumbnailPath;
        return null;
    }
}