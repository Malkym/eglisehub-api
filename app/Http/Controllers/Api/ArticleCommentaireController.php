<?php
// app/Http/Controllers/Api/ArticleCommentaireController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ArticleCommentaire;
use Illuminate\Http\Request;

class ArticleCommentaireController extends Controller
{
    // ===== ROUTES PUBLIQUES (existantes) =====

    /**
     * @OA\Get(
     *     path="/public/articles/{slug}/comments",
     *     tags={"Public - Commentaires"},
     *     summary="Liste les commentaires d'un article",
     *     @OA\Parameter(name="slug", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="subdomain", in="query", @OA\Schema(type="string", example="crc")),
     *     @OA\Response(response=200, description="Liste des commentaires",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/ArticleCommentaire"))
     *         )
     *     )
     * )
     */

    public function publicIndex(Request $request, string $slug)
    {
        $subdomain = $request->query('subdomain', 'crc');

        $article = Article::whereHas('ministere', function ($q) use ($subdomain) {
            $q->where('sous_domaine', $subdomain)->where('statut', 'actif');
        })->where('slug', $slug)->where('statut', 'publie')->firstOrFail();

        $commentaires = ArticleCommentaire::where('article_id', $article->id)
            ->where('statut', 'approuve')
            ->whereNull('parent_id')
            ->with(['reponses' => function ($q) {
                $q->where('statut', 'approuve');
            }])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $commentaires]);
    }

    /**
     * @OA\Post(
     *     path="/public/articles/{slug}/comments",
     *     tags={"Public - Commentaires"},
     *     summary="Ajouter un commentaire",
     *     @OA\Parameter(name="slug", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="subdomain", in="query", @OA\Schema(type="string", example="crc")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="contenu", type="string", example="Très bon article !"),
     *             @OA\Property(property="nom_auteur", type="string", nullable=true, example="Jean"),
     *             @OA\Property(property="email", type="string", format="email", nullable=true),
     *             @OA\Property(property="parent_id", type="integer", nullable=true, description="ID du commentaire parent pour une réponse")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Commentaire créé",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Commentaire envoyé. En attente de modération."),
     *             @OA\Property(property="data", ref="#/components/schemas/ArticleCommentaire")
     *         )
     *     )
     * )
     */

    public function publicStore(Request $request, string $slug)
    {
        $request->validate([
            'nom_auteur' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'contenu' => 'required|string|min:2|max:1000',
            'parent_id' => 'nullable|exists:article_commentaires,id',
        ]);

        $subdomain = $request->query('subdomain', 'crc');

        $article = Article::whereHas('ministere', function ($q) use ($subdomain) {
            $q->where('sous_domaine', $subdomain)->where('statut', 'actif');
        })->where('slug', $slug)->where('statut', 'publie')->firstOrFail();

        $commentaire = ArticleCommentaire::create([
            'article_id' => $article->id,
            'parent_id' => $request->parent_id,
            'nom_auteur' => $request->nom_auteur ?? 'Anonyme',
            'email' => $request->email,
            'contenu' => $request->contenu,
            'statut' => 'en_attente',
            'ip' => $request->ip(),
        ]);

        // Notifier les admins
        \App\Helpers\NotifHelper::notifyMinistryAdmins(
            $article->ministere_id,
            'Nouveau commentaire en attente',
            "{$commentaire->nom_auteur} a commenté l'article: {$article->titre}",
            'info',
            "/admin/comments?article={$article->id}",
            'comments'
        );

        return response()->json([
            'success' => true,
            'message' => 'Commentaire envoyé. En attente de modération.',
            'data' => $commentaire
        ], 201);
    }

    // ===== ROUTES ADMIN =====

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


    /**
     * @OA\Get(
     *     path="/ministry/comments",
     *     tags={"Admin - Commentaires"},
     *     summary="Liste tous les commentaires (admin)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="statut", in="query", @OA\Schema(type="string", enum={"en_attente","approuve","rejete"})),
     *     @OA\Parameter(name="article_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Liste des commentaires",
     *         @OA\JsonContent(ref="#/components/schemas/Paginated")
     *     )
     * )
     */
    public function index(Request $request)
    {
        $ministereId = $this->getMinistereId($request);

        $query = ArticleCommentaire::with(['article:id,titre,slug,ministere_id', 'article.ministere:id,nom'])
            ->withCount('reponses')
            ->orderBy('created_at', 'desc');

        // Filtrer par ministère
        if ($ministereId) {
            $query->whereHas('article', function ($q) use ($ministereId) {
                $q->where('ministere_id', $ministereId);
            });
        }

        // Filtres optionnels
        if ($request->has('statut')) {
            $query->where('statut', $request->statut);
        }

        if ($request->has('article_id')) {
            $query->where('article_id', $request->article_id);
        }

        if ($request->has('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('contenu', 'like', "%{$request->search}%")
                    ->orWhere('nom_auteur', 'like', "%{$request->search}%")
                    ->orWhere('email', 'like', "%{$request->search}%");
            });
        }

        $commentaires = $query->paginate($request->per_page ?? 20);

        return response()->json(['success' => true, 'data' => $commentaires]);
    }


    /**
     * @OA\Get(
     *     path="/ministry/comments/pending",
     *     tags={"Admin - Commentaires"},
     *     summary="Liste les commentaires en attente",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Commentaires en attente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/ArticleCommentaire")),
     *             @OA\Property(property="total", type="integer", example=3)
     *         )
     *     )
     * )
     */
    public function pending(Request $request)
    {
        $ministereId = $this->getMinistereId($request);

        $query = ArticleCommentaire::where('statut', 'en_attente')
            ->with(['article:id,titre,slug', 'article.ministere:id,nom'])
            ->orderBy('created_at', 'asc');

        if ($ministereId) {
            $query->whereHas('article', function ($q) use ($ministereId) {
                $q->where('ministere_id', $ministereId);
            });
        }

        $commentaires = $query->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'data' => $commentaires,
            'total' => $query->count()
        ]);
    }

    /**
     * @OA\Patch(
     *     path="/ministry/comments/{id}/approve",
     *     tags={"Admin - Commentaires"},
     *     summary="Approuver un commentaire",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Commentaire approuvé",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Commentaire approuvé"),
     *             @OA\Property(property="data", ref="#/components/schemas/ArticleCommentaire")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Accès refusé")
     * )
     */

    public function approve(Request $request, $id)
    {
        $commentaire = ArticleCommentaire::findOrFail($id);

        // Vérifier les permissions
        if (! $request->user()->isSuperAdmin()) {
            if ($commentaire->article->ministere_id !== $request->user()->ministere_id) {
                return response()->json(['success' => false, 'message' => 'Accès refusé'], 403);
            }
        }

        $commentaire->update(['statut' => 'approuve']);

        return response()->json([
            'success' => true,
            'message' => 'Commentaire approuvé',
            'data' => $commentaire
        ]);
    }

    /**
     * @OA\Patch(
     *     path="/ministry/comments/{id}/reject",
     *     tags={"Admin - Commentaires"},
     *     summary="Rejeter un commentaire",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Commentaire rejeté",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Commentaire rejeté"),
     *             @OA\Property(property="data", ref="#/components/schemas/ArticleCommentaire")
     *         )
     *     )
     * )
     */
    public function reject(Request $request, $id)
    {
        $commentaire = ArticleCommentaire::findOrFail($id);

        // Vérifier les permissions
        if (! $request->user()->isSuperAdmin()) {
            if ($commentaire->article->ministere_id !== $request->user()->ministere_id) {
                return response()->json(['success' => false, 'message' => 'Accès refusé'], 403);
            }
        }

        $commentaire->update(['statut' => 'rejete']);

        return response()->json([
            'success' => true,
            'message' => 'Commentaire rejeté',
            'data' => $commentaire
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/ministry/comments/{id}",
     *     tags={"Admin - Commentaires"},
     *     summary="Supprimer un commentaire",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Commentaire supprimé",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Commentaire supprimé")
     *         )
     *     )
     * )
     */

    public function destroy(Request $request, $id)
    {
        $commentaire = ArticleCommentaire::findOrFail($id);

        // Vérifier les permissions
        if (! $request->user()->isSuperAdmin()) {
            if ($commentaire->article->ministere_id !== $request->user()->ministere_id) {
                return response()->json(['success' => false, 'message' => 'Accès refusé'], 403);
            }
        }

        $commentaire->delete();

        return response()->json([
            'success' => true,
            'message' => 'Commentaire supprimé'
        ]);
    }

      /**
     * @OA\Post(
     *     path="/ministry/comments/bulk-approve",
     *     tags={"Admin - Commentaires"},
     *     summary="Approuver plusieurs commentaires",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="ids", type="array", @OA\Items(type="integer"), example={1,2,3})
     *         )
     *     ),
     *     @OA\Response(response=200, description="Commentaires approuvés",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="3 commentaire(s) approuvé(s)")
     *         )
     *     )
     * )
     */
    public function bulkApprove(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:article_commentaires,id'
        ]);

        $ministereId = $this->getMinistereId($request);

        $query = ArticleCommentaire::whereIn('id', $request->ids);

        // Filtrer par ministère si nécessaire
        if ($ministereId) {
            $query->whereHas('article', function ($q) use ($ministereId) {
                $q->where('ministere_id', $ministereId);
            });
        }

        $count = $query->update(['statut' => 'approuve']);

        return response()->json([
            'success' => true,
            'message' => "{$count} commentaire(s) approuvé(s)"
        ]);
    }

      /**
     * @OA\Post(
     *     path="/ministry/comments/bulk-reject",
     *     tags={"Admin - Commentaires"},
     *     summary="Rejeter plusieurs commentaires",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="ids", type="array", @OA\Items(type="integer"), example={1,2,3})
     *         )
     *     ),
     *     @OA\Response(response=200, description="Commentaires rejetés",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="3 commentaire(s) rejeté(s)")
     *         )
     *     )
     * )
     */
    public function bulkReject(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:article_commentaires,id'
        ]);

        $ministereId = $this->getMinistereId($request);

        $query = ArticleCommentaire::whereIn('id', $request->ids);

        if ($ministereId) {
            $query->whereHas('article', function ($q) use ($ministereId) {
                $q->where('ministere_id', $ministereId);
            });
        }

        $count = $query->update(['statut' => 'rejete']);

        return response()->json([
            'success' => true,
            'message' => "{$count} commentaire(s) rejeté(s)"
        ]);
    }

        /**
     * @OA\Post(
     *     path="/ministry/comments/bulk-delete",
     *     tags={"Admin - Commentaires"},
     *     summary="Supprimer plusieurs commentaires",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="ids", type="array", @OA\Items(type="integer"), example={1,2,3})
     *         )
     *     ),
     *     @OA\Response(response=200, description="Commentaires supprimés",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="3 commentaire(s) supprimé(s)")
     *         )
     *     )
     * )
     */

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:article_commentaires,id'
        ]);

        $ministereId = $this->getMinistereId($request);

        $query = ArticleCommentaire::whereIn('id', $request->ids);

        if ($ministereId) {
            $query->whereHas('article', function ($q) use ($ministereId) {
                $q->where('ministere_id', $ministereId);
            });
        }

        $count = $query->delete();

        return response()->json([
            'success' => true,
            'message' => "{$count} commentaire(s) supprimé(s)"
        ]);
    }
}
