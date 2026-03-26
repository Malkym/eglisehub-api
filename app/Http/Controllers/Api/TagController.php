<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use App\Models\Article;
use App\Models\Page;
//use App\Models\LogAction;
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
     *     @OA\Response(
     *         response=200,
     *         description="Liste des tags",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Tag"))
     *         )
     *     )
     * )
     */


    // GET /api/ministry/tags
    public function index(Request $request)
    {
        $ministereId = $this->getMinistereId($request);

        $tags = Tag::where('ministere_id', $ministereId)
            ->withCount(['articles', 'pages'])
            ->when($request->search, fn($q) => $q->where('nom', 'like', "%{$request->search}%"))
            ->orderBy('nom')
            ->get();

        return response()->json(['success' => true, 'data' => $tags]);
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
     *             @OA\Property(property="couleur", type="string", example="#1E3A8A", description="Couleur en hexadécimal")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Tag créé",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tag créé."),
     *             @OA\Property(property="data", ref="#/components/schemas/Tag")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Tag existe déjà")
     * )
     */

    // POST /api/ministry/tags
    public function store(Request $request)
    {
        $ministereId = $this->getMinistereId($request);

        $request->validate([
            'nom'     => 'required|string|max:50',
            'couleur' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        $slug = Str::slug($request->nom);

        // Vérifier unicité dans ce ministère
        if (Tag::where('ministere_id', $ministereId)->where('slug', $slug)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Ce tag existe déjà.',
            ], 422);
        }

        $tag = Tag::create([
            'ministere_id' => $ministereId,
            'nom'          => $request->nom,
            'slug'         => $slug,
            'couleur'      => $request->couleur ?? '#6B7280',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tag créé.',
            'data'    => $tag,
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/ministry/tags/{id}",
     *     tags={"Tags"},
     *     summary="Modifier un tag",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="nom", type="string"),
     *             @OA\Property(property="couleur", type="string", example="#1E3A8A")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tag mis à jour",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tag mis à jour."),
     *             @OA\Property(property="data", ref="#/components/schemas/Tag")
     *         )
     *     )
     * )
     */


    // PUT /api/ministry/tags/{id}
    public function update(Request $request, string $id)
    {
        $tag = $this->findForUser($request, $id);

        $request->validate([
            'nom'     => 'sometimes|string|max:50',
            'couleur' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        if ($request->has('nom')) {
            $request->merge(['slug' => Str::slug($request->nom)]);
        }

        $tag->update($request->only(['nom', 'slug', 'couleur']));

        return response()->json([
            'success' => true,
            'message' => 'Tag mis à jour.',
            'data'    => $tag->fresh(),
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/ministry/tags/{id}",
     *     tags={"Tags"},
     *     summary="Supprimer un tag",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Tag supprimé",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tag supprimé.")
     *         )
     *     )
     * )
     */

    // DELETE /api/ministry/tags/{id}
    public function destroy(Request $request, string $id)
    {
        $tag = $this->findForUser($request, $id);
        $tag->delete(); // Supprime aussi les entrées dans taggables (cascade)

        return response()->json(['success' => true, 'message' => 'Tag supprimé.']);
    }

    /**
     * @OA\Post(
     *     path="/ministry/tags/attach",
     *     tags={"Tags"},
     *     summary="Attacher un tag à un article ou une page",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"tag_id","type","contenu_id"},
     *             @OA\Property(property="tag_id", type="integer", example=1),
     *             @OA\Property(property="type", type="string", enum={"article","page"}),
     *             @OA\Property(property="contenu_id", type="integer", example=5)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tag attaché",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tag attaché à l'article.")
     *         )
     *     )
     * )
     */
    // POST /api/ministry/tags/attach — Attacher un tag à un contenu
    public function attach(Request $request)
    {
        $request->validate([
            'tag_id'      => 'required|integer|exists:tags,id',
            'type'        => 'required|in:article,page',
            'contenu_id'  => 'required|integer',
        ]);

        $tag = Tag::findOrFail($request->tag_id);

        if ($request->type === 'article') {
            $contenu = Article::findOrFail($request->contenu_id);
        } else {
            $contenu = Page::findOrFail($request->contenu_id);
        }

        // Attacher si pas déjà attaché
        $contenu->tags()->syncWithoutDetaching([$tag->id]);

        return response()->json([
            'success' => true,
            'message' => "Tag attaché au {$request->type}.",
        ]);
    }

    /**
     * @OA\Post(
     *     path="/ministry/tags/detach",
     *     tags={"Tags"},
     *     summary="Détacher un tag d'un article ou d'une page",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"tag_id","type","contenu_id"},
     *             @OA\Property(property="tag_id", type="integer", example=1),
     *             @OA\Property(property="type", type="string", enum={"article","page"}),
     *             @OA\Property(property="contenu_id", type="integer", example=5)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tag détaché",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tag détaché de l'article.")
     *         )
     *     )
     * )
     */

    // POST /api/ministry/tags/detach — Détacher un tag
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

        return response()->json([
            'success' => true,
            'message' => "Tag détaché du {$request->type}.",
        ]);
    }


    /**
     * @OA\Get(
     *     path="/ministry/tags/popular",
     *     tags={"Tags"},
     *     summary="Liste les tags les plus utilisés",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Top 10 des tags",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Tag"))
     *         )
     *     )
     * )
     */

    // GET /api/ministry/tags/popular
    public function popular(Request $request)
    {
        $ministereId = $this->getMinistereId($request);

        $tags = Tag::where('ministere_id', $ministereId)
            ->withCount(['articles', 'pages'])
            ->orderByRaw('articles_count + pages_count DESC')
            ->take(10)
            ->get();

        return response()->json(['success' => true, 'data' => $tags]);
    }

    /**
     * @OA\Get(
     *     path="/public/tags",
     *     tags={"Public"},
     *     summary="Liste les tags publics d'un ministère",
     *     @OA\Parameter(name="subdomain", in="query", @OA\Schema(type="string", example="crc")),
     *     @OA\Response(
     *         response=200,
     *         description="Tags publics",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="nom", type="string"),
     *                 @OA\Property(property="slug", type="string"),
     *                 @OA\Property(property="couleur", type="string")
     *             ))
     *         )
     *     )
     * )
     */

    // GET /api/public/tags
    public function publicIndex(Request $request)
    {
        $subdomain = $request->header('X-Subdomain') ?? $request->query('subdomain') ?? 'crc';

        $tags = Tag::whereHas(
            'ministere',
            fn($q) =>
            $q->where('sous_domaine', $subdomain)->where('statut', 'actif')
        )
            ->withCount(['articles', 'pages'])
            ->orderBy('nom')
            ->get(['id', 'nom', 'slug', 'couleur']);

        return response()->json(['success' => true, 'data' => $tags]);
    }

    private function getMinistereId(Request $request): int
    {
        if ($request->user()->isSuperAdmin() && $request->has('ministere_id')) {
            return (int) $request->ministere_id;
        }
        return $request->user()->ministere_id ?? 1;
    }

    private function findForUser(Request $request, string $id): Tag
    {
        $tag = Tag::findOrFail($id);
        if (! $request->user()->isSuperAdmin()) {
            if ($tag->ministere_id !== $request->user()->ministere_id) {
                abort(403, 'Accès refusé.');
            }
        }
        return $tag;
    }
}
