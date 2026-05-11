<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Page;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TagController extends Controller
{

    /**
     * @OA\Get(
     *     path="/ministry/tags",
     *     tags={"Tags"},
     *     summary="Liste tous les tags du ministère",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="ministere_id", in="query", @OA\Schema(type="integer"), description="Super admin uniquement"),
     *     @OA\Response(response=200, description="Liste des tags",
     *         @OA\JsonContent(@OA\Property(property="success", type="boolean", example=true))
     *     )
     * )
     */
    public function index(Request $request)
    {
        $ministereId = $this->getMinistereId($request);

        $tags = Tag::where('ministere_id', $ministereId)
            ->withCount(['articles', 'pages'])
            ->when($request->search, fn($q) => $q->where('nom', 'like', "%{$request->search}%"))
            ->orderBy('nom')
            ->get();

        return $this->respondSuccess($tags);
    }

    /**
     * @OA\Post(
     *     path="/ministry/tags",
     *     tags={"Tags"},
     *     summary="Créer un tag",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"nom"},
     *             @OA\Property(property="nom", type="string", example="Enseignements"),
     *             @OA\Property(property="couleur", type="string", example="#1E3A8A")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Tag créé"),
     *     @OA\Response(response=422, description="Tag existe déjà")
     * )
     */
    public function store(Request $request)
    {
        $ministereId = $this->getMinistereId($request);

        $request->validate([
            'nom'     => 'required|string|max:50',
            'couleur' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        $slug = Str::slug($request->nom);

        if (Tag::where('ministere_id', $ministereId)->where('slug', $slug)->exists()) {
            return $this->respondWithError('Ce tag existe déjà.', 422);
        }

        $tag = Tag::create([
            'ministere_id' => $ministereId,
            'nom'          => $request->nom,
            'slug'         => $slug,
            'couleur'      => $request->couleur ?? '#6B7280',
        ]);

        return $this->respondSuccess($tag, 'Tag créé.', 201);
    }

    /**
     * @OA\Put(
     *     path="/ministry/tags/{id}",
     *     tags={"Tags"},
     *     summary="Modifier un tag",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Tag mis à jour")
     * )
     */
    public function update(Request $request, string $id)
    {
        $tag = $this->findForMinistere($request, Tag::class, $id);

        $request->validate([
            'nom'     => 'sometimes|string|max:50',
            'couleur' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        if ($request->has('nom')) {
            $request->merge(['slug' => Str::slug($request->nom)]);
        }

        $tag->update($request->only(['nom', 'slug', 'couleur']));

        return $this->respondSuccess($tag->fresh(), 'Tag mis à jour.');
    }

    /**
     * @OA\Delete(
     *     path="/ministry/tags/{id}",
     *     tags={"Tags"},
     *     summary="Supprimer un tag",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Tag supprimé")
     * )
     */
    public function destroy(Request $request, string $id)
    {
        $tag = $this->findForMinistere($request, Tag::class, $id);
        $tag->delete();

        return $this->respondSuccess(null, 'Tag supprimé.');
    }

    /**
     * @OA\Post(
     *     path="/ministry/tags/attach",
     *     tags={"Tags"},
     *     summary="Attacher un tag à un article ou une page",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Tag attaché")
     * )
     */
    public function attach(Request $request)
    {
        $request->validate([
            'tag_id'     => 'required|integer|exists:tags,id',
            'type'       => 'required|in:article,page',
            'contenu_id' => 'required|integer',
        ]);

        $tag = Tag::findOrFail($request->tag_id);

        if ($request->type === 'article') {
            $contenu = Article::findOrFail($request->contenu_id);
        } else {
            $contenu = Page::findOrFail($request->contenu_id);
        }

        $contenu->tags()->syncWithoutDetaching([$tag->id]);

        return $this->respondSuccess(null, "Tag attaché au {$request->type}.");
    }

    /**
     * @OA\Post(
     *     path="/ministry/tags/detach",
     *     tags={"Tags"},
     *     summary="Détacher un tag d'un article ou d'une page",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Tag détaché")
     * )
     */
    public function detach(Request $request)
    {
        $request->validate([
            'tag_id'     => 'required|integer|exists:tags,id',
            'type'       => 'required|in:article,page',
            'contenu_id' => 'required|integer',
        ]);

        if ($request->type === 'article') {
            $contenu = Article::findOrFail($request->contenu_id);
        } else {
            $contenu = Page::findOrFail($request->contenu_id);
        }

        $contenu->tags()->detach($request->tag_id);

        return $this->respondSuccess(null, "Tag détaché du {$request->type}.");
    }

    /**
     * @OA\Get(
     *     path="/ministry/tags/popular",
     *     tags={"Tags"},
     *     summary="Liste les tags les plus utilisés",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Top 10 des tags")
     * )
     */
    public function popular(Request $request)
    {
        $ministereId = $this->getMinistereId($request);

        $tags = Tag::where('ministere_id', $ministereId)
            ->withCount(['articles', 'pages'])
            ->orderByRaw('articles_count + pages_count DESC')
            ->take(10)
            ->get();

        return $this->respondSuccess($tags);
    }

    /**
     * @OA\Get(
     *     path="/public/tags",
     *     tags={"Public"},
     *     summary="Liste les tags publics d'un ministère",
     *     @OA\Parameter(name="subdomain", in="query", @OA\Schema(type="string", example="crc")),
     *     @OA\Response(response=200, description="Tags publics")
     * )
     */
    public function publicIndex(Request $request)
    {
        $subdomain = $this->resolveSubdomain($request);

        $tags = Tag::whereHas(
            'ministere',
            fn($q) => $q->where('sous_domaine', $subdomain)->where('statut', 'actif')
        )
            ->withCount(['articles', 'pages'])
            ->orderBy('nom')
            ->get(['id', 'nom', 'slug', 'couleur']);

        return $this->respondSuccess($tags);
    }
}