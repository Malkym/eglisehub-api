<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ArticleCommentaire;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class ArticleCommentaireController extends Controller
{
    private const RATE_LIMIT_MAX = 5;
    private const RATE_LIMIT_DECAY = 3600;

    public function publicIndex(Request $request, string $slug)
    {
        $subdomain = $this->resolveSubdomain($request);

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

        return $this->respondSuccess($commentaires);
    }

    public function publicStore(Request $request, string $slug)
    {
        $rateLimitKey = 'comment:' . $slug . ':' . ($request->ip() ?? 'unknown');

        if (RateLimiter::tooManyAttempts($rateLimitKey, self::RATE_LIMIT_MAX)) {
            return $this->respondWithError('Trop de commentaires. Veuillez réessayer plus tard.', 429);
        }

        $validated = $request->validate([
            'nom_auteur' => 'nullable|string|max:255',
            'email'      => 'nullable|email|max:255',
            'contenu'    => 'required|string|min:2|max:1000',
            'parent_id'  => 'nullable|integer|exists:article_commentaires,id',
        ]);

        $subdomain = $this->resolveSubdomain($request);

        $article = Article::whereHas('ministere', function ($q) use ($subdomain) {
            $q->where('sous_domaine', $subdomain)->where('statut', 'actif');
        })->where('slug', $slug)->where('statut', 'publie')->firstOrFail();

        $commentaire = ArticleCommentaire::create([
            'article_id' => $article->id,
            'parent_id'  => $validated['parent_id'] ?? null,
            'nom_auteur' => $validated['nom_auteur'] ?? 'Anonyme',
            'email'      => $validated['email'] ?? null,
            'contenu'    => $validated['contenu'],
            'statut'     => 'en_attente',
            'ip'         => $request->getClientIp(),
        ]);

        RateLimiter::hit($rateLimitKey, self::RATE_LIMIT_DECAY);

        \App\Helpers\NotifHelper::notifyMinistryAdmins(
            $article->ministere_id,
            'Nouveau commentaire en attente',
            "{$commentaire->nom_auteur} a commenté l'article: {$article->titre}",
            'info',
            "/admin/comments?article={$article->id}",
            'comments'
        );

        return $this->respondSuccess($commentaire, 'Commentaire envoyé. En attente de modération.', 201);
    }

    public function index(Request $request)
    {
        $ministereId = $this->getMinistereId($request);

        $query = ArticleCommentaire::with(['article:id,titre,slug,ministere_id', 'article.ministere:id,nom'])
            ->withCount('reponses')
            ->orderBy('created_at', 'desc');

        if ($ministereId) {
            $query->whereHas('article', function ($q) use ($ministereId) {
                $q->where('ministere_id', $ministereId);
            });
        }

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

        return $this->respondPaginated($query->paginate($request->per_page ?? 20));
    }

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

        return $this->respondSuccess([
            'data'  => $commentaires,
            'total' => $query->count(),
        ]);
    }

    public function approve(Request $request, int $id)
    {
        $commentaire = $this->findForMinistereWithArticle($request, $id);

        $commentaire->update(['statut' => 'approuve']);

        return $this->respondSuccess($commentaire, 'Commentaire approuvé');
    }

    public function reject(Request $request, int $id)
    {
        $commentaire = $this->findForMinistereWithArticle($request, $id);

        $commentaire->update(['statut' => 'rejete']);

        return $this->respondSuccess($commentaire, 'Commentaire rejeté');
    }

    public function destroy(Request $request, int $id)
    {
        $commentaire = $this->findForMinistereWithArticle($request, $id);

        $commentaire->delete();

        return $this->respondSuccess(null, 'Commentaire supprimé');
    }

    public function bulkApprove(Request $request)
    {
        $validated = $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'integer|exists:article_commentaires,id',
        ]);

        $ministereId = $this->getMinistereId($request);

        $query = ArticleCommentaire::whereIn('id', $validated['ids']);

        if ($ministereId) {
            $query->whereHas('article', function ($q) use ($ministereId) {
                $q->where('ministere_id', $ministereId);
            });
        }

        $count = $query->update(['statut' => 'approuve']);

        return $this->respondSuccess(null, "{$count} commentaire(s) approuvé(s)");
    }

    public function bulkReject(Request $request)
    {
        $validated = $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'integer|exists:article_commentaires,id',
        ]);

        $ministereId = $this->getMinistereId($request);

        $query = ArticleCommentaire::whereIn('id', $validated['ids']);

        if ($ministereId) {
            $query->whereHas('article', function ($q) use ($ministereId) {
                $q->where('ministere_id', $ministereId);
            });
        }

        $count = $query->update(['statut' => 'rejete']);

        return $this->respondSuccess(null, "{$count} commentaire(s) rejeté(s)");
    }

    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'integer|exists:article_commentaires,id',
        ]);

        $ministereId = $this->getMinistereId($request);

        $query = ArticleCommentaire::whereIn('id', $validated['ids']);

        if ($ministereId) {
            $query->whereHas('article', function ($q) use ($ministereId) {
                $q->where('ministere_id', $ministereId);
            });
        }

        $count = $query->delete();

        return $this->respondSuccess(null, "{$count} commentaire(s) supprimé(s)");
    }

    private function findForMinistereWithArticle(Request $request, int $id): ArticleCommentaire
    {
        $commentaire = ArticleCommentaire::with('article')->findOrFail($id);

        if (!$request->user()->isSuperAdmin()) {
            if ($commentaire->article->ministere_id !== $request->user()->ministere_id) {
                abort(403, 'Accès refusé');
            }
        }

        return $commentaire;
    }
}