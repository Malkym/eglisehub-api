<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function index(Request $request)
    {
        $ministereId = $request->user()->ministere_id;

        if ($request->user()->isSuperAdmin() && $request->has('ministere_id')) {
            $ministereId = $request->ministere_id;
        }

        $pages = Page::when($ministereId, fn($q) => $q->where('ministere_id', $ministereId))
            ->when($request->statut, fn($q) => $q->where('statut', $request->statut))
            ->when($request->search, fn($q) => $q->where('titre', 'like', "%{$request->search}%"))
            ->orderBy('ordre_menu')
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->respondSuccess($pages);
    }

    public function store(Request $request)
    {
        $request->validate([
            'titre'            => 'required|string|max:255',
            'contenu'          => 'nullable|string',
            'image_hero'       => 'nullable|string',
            'dans_menu'        => 'boolean',
            'ordre_menu'       => 'integer',
            'statut'           => 'in:publie,brouillon',
            'meta_titre'       => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
        ]);

        $ministereId = $request->user()->ministere_id;
        if ($request->user()->isSuperAdmin() && $request->has('ministere_id')) {
            $ministereId = $request->ministere_id;
        }

        $slug = $this->generateUniqueSlug($request->titre, $ministereId, Page::class);

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

        return $this->respondSuccess($page, 'Page créée avec succès.', 201);
    }

    public function show(Request $request, string $id)
    {
        $page = $this->findForMinistere($request, Page::class, $id);

        return $this->respondSuccess($page);
    }

    public function update(Request $request, string $id)
    {
        $page = $this->findForMinistere($request, Page::class, $id);

        $request->validate([
            'titre'            => 'sometimes|string|max:255',
            'contenu'          => 'nullable|string',
            'image_hero'       => 'nullable|string',
            'dans_menu'        => 'boolean',
            'ordre_menu'       => 'integer',
            'statut'           => 'in:publie,brouillon',
            'meta_titre'       => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
        ]);

        if ($request->has('titre') && $request->titre !== $page->titre) {
            $request->merge([
                'slug' => $this->generateUniqueSlug($request->titre, $page->ministere_id, Page::class, $id)
            ]);
        }

        $page->update($request->only([
            'titre', 'slug', 'contenu', 'image_hero', 'dans_menu',
            'ordre_menu', 'statut', 'meta_titre', 'meta_description',
        ]));

        $this->log($request, 'update_page', 'pages', "Modification page: {$page->titre}");

        return $this->respondSuccess($page->fresh(), 'Page mise à jour.');
    }

    public function destroy(Request $request, string $id)
    {
        $page = $this->findForMinistere($request, Page::class, $id);
        $titre = $page->titre;
        $page->delete();

        $this->log($request, 'delete_page', 'pages', "Suppression page: {$titre}");

        return $this->respondSuccess(null, 'Page supprimée.');
    }

    public function publish(Request $request, string $id)
    {
        $page = $this->findForMinistere($request, Page::class, $id);
        $page->update(['statut' => 'publie']);

        return $this->respondSuccess($page->fresh(), 'Page publiée.');
    }

    public function unpublish(Request $request, string $id)
    {
        $page = $this->findForMinistere($request, Page::class, $id);
        $page->update(['statut' => 'brouillon']);

        return $this->respondSuccess($page->fresh(), 'Page dépubliée.');
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'pages'         => 'required|array',
            'pages.*.id'    => 'required|integer',
            'pages.*.ordre' => 'required|integer',
        ]);

        foreach ($request->pages as $item) {
            Page::where('id', $item['id'])->update(['ordre_menu' => $item['ordre']]);
        }

        return $this->respondSuccess(null, 'Ordre des pages mis à jour.');
    }

    public function publicIndex(Request $request)
    {
        $subdomain = $this->resolveSubdomain($request);

        $pages = Page::whereHas(
            'ministere',
            fn($q) => $q->where('sous_domaine', $subdomain)->where('statut', 'actif')
        )
            ->where('statut', 'publie')
            ->where('dans_menu', true)
            ->orderBy('ordre_menu')
            ->get(['id', 'titre', 'slug', 'ordre_menu']);

        return $this->respondSuccess($pages);
    }

    public function publicShow(Request $request, string $slug)
    {
        $subdomain = $this->resolveSubdomain($request);

        $page = Page::whereHas(
            'ministere',
            fn($q) => $q->where('sous_domaine', $subdomain)->where('statut', 'actif')
        )
            ->where('slug', $slug)
            ->where('statut', 'publie')
            ->firstOrFail();

        return $this->respondSuccess($page);
    }
}