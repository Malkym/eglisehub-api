<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\LogAction;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PageController extends Controller
{
    // GET /api/ministry/pages
    public function index(Request $request)
    {
        $ministereId = $request->user()->ministere_id;

        // Super admin peut voir toutes les pages d'un ministère donné
        if ($request->user()->isSuperAdmin() && $request->has('ministere_id')) {
            $ministereId = $request->ministere_id;
        }

        $pages = Page::when($ministereId, fn($q) => $q->where('ministere_id', $ministereId))
            ->when($request->statut, fn($q) => $q->where('statut', $request->statut))
            ->when($request->search, fn($q) => $q->where('titre', 'like', "%{$request->search}%"))
            ->orderBy('ordre_menu')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $pages,
        ]);
    }

    // POST /api/ministry/pages
    public function store(Request $request)
    {
        $request->validate([
            'titre'       => 'required|string|max:255',
            'contenu'     => 'nullable|string',
            'image_hero'  => 'nullable|string',
            'dans_menu'   => 'boolean',
            'ordre_menu'  => 'integer',
            'statut'      => 'in:publie,brouillon',
            'meta_titre'  => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
        ]);

        $ministereId = $request->user()->ministere_id;
        if ($request->user()->isSuperAdmin() && $request->has('ministere_id')) {
            $ministereId = $request->ministere_id;
        }

        // Générer slug unique pour ce ministère
        $slug = $this->generateUniqueSlug($request->titre, $ministereId);

        $page = Page::create([
            'ministere_id'     => $ministereId,
            'titre'            => $request->titre,
            'slug'             => $slug,
            'contenu'          => $request->contenu,
            'image_hero'       => $request->image_hero,
            'dans_menu'        => $request->dans_menu ?? true,
            'ordre_menu'       => $request->ordre_menu ?? 0,
            'statut'           => $request->statut ?? 'brouillon',
            'meta_titre'       => $request->meta_titre,
            'meta_description' => $request->meta_description,
        ]);

        $this->log($request, 'create_page', 'pages', "Création page: {$page->titre}");

        return response()->json([
            'success' => true,
            'message' => 'Page créée avec succès.',
            'data'    => $page,
        ], 201);
    }

    // GET /api/ministry/pages/{id}
    public function show(Request $request, string $id)
    {
        $page = $this->findPageForUser($request, $id);

        return response()->json([
            'success' => true,
            'data'    => $page,
        ]);
    }

    // PUT /api/ministry/pages/{id}
    public function update(Request $request, string $id)
    {
        $page = $this->findPageForUser($request, $id);

        $request->validate([
            'titre'       => 'sometimes|string|max:255',
            'contenu'     => 'nullable|string',
            'image_hero'  => 'nullable|string',
            'dans_menu'   => 'boolean',
            'ordre_menu'  => 'integer',
            'statut'      => 'in:publie,brouillon',
            'meta_titre'  => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
        ]);

        if ($request->has('titre') && $request->titre !== $page->titre) {
            $request->merge([
                'slug' => $this->generateUniqueSlug($request->titre, $page->ministere_id, $id)
            ]);
        }

        $page->update($request->only([
            'titre',
            'slug',
            'contenu',
            'image_hero',
            'dans_menu',
            'ordre_menu',
            'statut',
            'meta_titre',
            'meta_description',
        ]));

        $this->log($request, 'update_page', 'pages', "Modification page: {$page->titre}");

        return response()->json([
            'success' => true,
            'message' => 'Page mise à jour.',
            'data'    => $page->fresh(),
        ]);
    }

    // DELETE /api/ministry/pages/{id}
    public function destroy(Request $request, string $id)
    {
        $page = $this->findPageForUser($request, $id);
        $titre = $page->titre;
        $page->delete();

        $this->log($request, 'delete_page', 'pages', "Suppression page: {$titre}");

        return response()->json([
            'success' => true,
            'message' => 'Page supprimée.',
        ]);
    }

    // PATCH /api/ministry/pages/{id}/publish
    public function publish(Request $request, string $id)
    {
        $page = $this->findPageForUser($request, $id);
        $page->update(['statut' => 'publie']);

        return response()->json([
            'success' => true,
            'message' => 'Page publiée.',
            'data'    => $page->fresh(),
        ]);
    }

    // PATCH /api/ministry/pages/{id}/unpublish
    public function unpublish(Request $request, string $id)
    {
        $page = $this->findPageForUser($request, $id);
        $page->update(['statut' => 'brouillon']);

        return response()->json([
            'success' => true,
            'message' => 'Page dépubliée.',
            'data'    => $page->fresh(),
        ]);
    }

    // POST /api/ministry/pages/reorder
    public function reorder(Request $request)
    {
        $request->validate([
            'pages'          => 'required|array',
            'pages.*.id'     => 'required|integer',
            'pages.*.ordre'  => 'required|integer',
        ]);

        foreach ($request->pages as $item) {
            Page::where('id', $item['id'])->update(['ordre_menu' => $item['ordre']]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Ordre des pages mis à jour.',
        ]);
    }

    // GET /api/public/pages — pages publiques d'un ministère
    public function publicIndex(Request $request)
    {
        $subdomain = $request->header('X-Subdomain') ?? $request->query('subdomain') ?? 'crc';

        $pages = Page::whereHas(
            'ministere',
            fn($q) =>
            $q->where('sous_domaine', $subdomain)->where('statut', 'actif')
        )
            ->where('statut', 'publie')
            ->where('dans_menu', true)
            ->orderBy('ordre_menu')
            ->get(['id', 'titre', 'slug', 'ordre_menu']);

        return response()->json(['success' => true, 'data' => $pages]);
    }

    // GET /api/public/pages/{slug}
    public function publicShow(Request $request, string $slug)
    {
        $subdomain = $request->header('X-Subdomain') ?? $request->query('subdomain') ?? 'crc';

        $page = Page::whereHas(
            'ministere',
            fn($q) =>
            $q->where('sous_domaine', $subdomain)->where('statut', 'actif')
        )
            ->where('slug', $slug)
            ->where('statut', 'publie')
            ->firstOrFail();

        return response()->json(['success' => true, 'data' => $page]);
    }

    // Helpers privés
    private function findPageForUser(Request $request, string $id): Page
    {
        $query = Page::findOrFail($id);

        if (! $request->user()->isSuperAdmin()) {
            if ($query->ministere_id !== $request->user()->ministere_id) {
                abort(403, 'Accès refusé à cette page.');
            }
        }

        return $query;
    }

    private function generateUniqueSlug(string $titre, int $ministereId, ?string $exceptId = null): string
    {
        $slug = Str::slug($titre);
        $original = $slug;
        $count = 1;

        while (
            Page::where('ministere_id', $ministereId)
            ->where('slug', $slug)
            ->when($exceptId, fn($q) => $q->where('id', '!=', $exceptId))
            ->exists()
        ) {
            $slug = $original . '-' . $count++;
        }

        return $slug;
    }

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
