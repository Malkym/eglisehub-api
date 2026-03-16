<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Slider;
use App\Models\LogAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SliderController extends Controller
{
    // GET /api/ministry/sliders
    public function index(Request $request)
    {
        $ministereId = $this->getMinistereId($request);

        $sliders = Slider::where('ministere_id', $ministereId)
            ->when($request->actif !== null, fn($q) => $q->where('actif', $request->actif === 'true'))
            ->orderBy('ordre')
            ->get();

        return response()->json(['success' => true, 'data' => $sliders]);
    }

    // POST /api/ministry/sliders
    public function store(Request $request)
    {
        $request->validate([
            'titre'          => 'required|string|max:255',
            'sous_titre'     => 'nullable|string|max:500',
            'image'          => 'required|file|image|max:5120',
            'bouton_texte'   => 'nullable|string|max:50',
            'bouton_lien'    => 'nullable|string|max:255',
            'position_texte' => 'in:gauche,centre,droite',
            'couleur_texte'  => 'nullable|string|max:7',
            'couleur_fond'   => 'nullable|string|max:7',
            'ordre'          => 'integer',
            'actif'          => 'boolean',
        ]);

        $ministereId = $this->getMinistereId($request);

        // Upload image
        $nomFichier = Str::uuid().'.'.$request->file('image')->extension();
        $chemin     = $request->file('image')->storeAs(
            "ministeres/{$ministereId}/sliders",
            $nomFichier,
            'public'
        );

        $slider = Slider::create([
            'ministere_id'   => $ministereId,
            'titre'          => $request->titre,
            'sous_titre'     => $request->sous_titre,
            'image'          => $chemin,
            'url_image'      => Storage::url($chemin),
            'bouton_texte'   => $request->bouton_texte,
            'bouton_lien'    => $request->bouton_lien,
            'position_texte' => $request->position_texte ?? 'centre',
            'couleur_texte'  => $request->couleur_texte ?? '#FFFFFF',
            'couleur_fond'   => $request->couleur_fond,
            'ordre'          => $request->ordre ?? 0,
            'actif'          => $request->actif ?? true,
        ]);

        $this->log($request, 'create_slider', "Création slide: {$slider->titre}");

        return response()->json([
            'success' => true,
            'message' => 'Slide créé.',
            'data'    => $slider,
        ], 201);
    }

    // GET /api/ministry/sliders/{id}
    public function show(Request $request, string $id)
    {
        $slider = $this->findForUser($request, $id);
        return response()->json(['success' => true, 'data' => $slider]);
    }

    // PUT /api/ministry/sliders/{id}
    public function update(Request $request, string $id)
    {
        $slider = $this->findForUser($request, $id);

        $request->validate([
            'titre'          => 'sometimes|string|max:255',
            'sous_titre'     => 'nullable|string|max:500',
            'image'          => 'nullable|file|image|max:5120',
            'bouton_texte'   => 'nullable|string|max:50',
            'bouton_lien'    => 'nullable|string|max:255',
            'position_texte' => 'in:gauche,centre,droite',
            'couleur_texte'  => 'nullable|string|max:7',
            'couleur_fond'   => 'nullable|string|max:7',
            'ordre'          => 'integer',
            'actif'          => 'boolean',
        ]);

        $data = $request->only([
            'titre', 'sous_titre', 'bouton_texte', 'bouton_lien',
            'position_texte', 'couleur_texte', 'couleur_fond', 'ordre', 'actif',
        ]);

        // Nouvelle image
        if ($request->hasFile('image')) {
            if ($slider->image) Storage::disk('public')->delete($slider->image);
            $nomFichier      = Str::uuid().'.'.$request->file('image')->extension();
            $chemin          = $request->file('image')->storeAs(
                "ministeres/{$slider->ministere_id}/sliders",
                $nomFichier,
                'public'
            );
            $data['image']     = $chemin;
            $data['url_image'] = Storage::url($chemin);
        }

        $slider->update($data);

        $this->log($request, 'update_slider', "Modification slide: {$slider->titre}");

        return response()->json([
            'success' => true,
            'message' => 'Slide mis à jour.',
            'data'    => $slider->fresh(),
        ]);
    }

    // DELETE /api/ministry/sliders/{id}
    public function destroy(Request $request, string $id)
    {
        $slider = $this->findForUser($request, $id);

        if ($slider->image) Storage::disk('public')->delete($slider->image);

        $titre = $slider->titre;
        $slider->delete();

        $this->log($request, 'delete_slider', "Suppression slide: {$titre}");

        return response()->json(['success' => true, 'message' => 'Slide supprimé.']);
    }

    // PATCH /api/ministry/sliders/{id}/toggle
    public function toggle(Request $request, string $id)
    {
        $slider = $this->findForUser($request, $id);
        $slider->update(['actif' => ! $slider->actif]);
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

        $sliders = Slider::whereHas('ministere', fn($q) =>
                            $q->where('sous_domaine', $subdomain)->where('statut', 'actif')
                          )
                          ->where('actif', true)
                          ->orderBy('ordre')
                          ->get();

        return response()->json(['success' => true, 'data' => $sliders]);
    }

    private function getMinistereId(Request $request): int
    {
        if ($request->user()->isSuperAdmin() && $request->has('ministere_id')) {
            return (int) $request->ministere_id;
        }
        return $request->user()->ministere_id ?? 1;
    }

    private function findForUser(Request $request, string $id): Slider
    {
        $slider = Slider::findOrFail($id);
        if (! $request->user()->isSuperAdmin()) {
            if ($slider->ministere_id !== $request->user()->ministere_id) {
                abort(403, 'Accès refusé.');
            }
        }
        return $slider;
    }

    private function log(Request $request, string $action, string $details): void
    {
        LogAction::create([
            'user_id'      => $request->user()->id,
            'ministere_id' => $request->user()->ministere_id,
            'action'       => $action,
            'module'       => 'sliders',
            'details'      => $details,
            'ip'           => $request->ip(),
            'date_action'  => now(),
        ]);
    }
}