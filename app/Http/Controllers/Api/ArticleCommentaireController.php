<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ArticleCommentaire;
use App\Models\LogAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class ArticleCommentaireController extends Controller
{
    private const RATE_LIMIT_MAX = 5;
    private const RATE_LIMIT_DECAY = 3600;

    // GET /api/public/articles/{slug}/comments
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

    // POST /api/public/articles/{slug}/comments
    public function publicStore(Request $request, string $slug)
    {
        $rateLimitKey = 'comment:' . $slug . ':' . ($request->ip() ?? 'unknown');
        
        if (RateLimiter::tooManyAttempts($rateLimitKey, self::RATE_LIMIT_MAX)) {
            return response()->json([
                'success' => false,
                'message' => 'Trop de commentaires. Veuillez réessayer plus tard.',
            ], 429);
        }

        $validated = $request->validate([
            'nom_auteur' => 'nullable|string|max:255',
            'email'     => 'nullable|email|max:255',
            'contenu'    => 'required|string|min:2|max:1000',
            'parent_id' => 'nullable|integer|exists:article_commentaires,id',
        ]);

        $subdomain = $request->query('subdomain', 'crc');

        $article = Article::whereHas('ministere', function ($q) use ($subdomain) {
            $q->where('sous_domaine', $subdomain)->where('statut', 'actif');
        })->where('slug', $slug)->where('statut', 'publie')->firstOrFail();

        $commentaire = ArticleCommentaire::create([
            'article_id'  => $article->id,
            'parent_id'  => $validated['parent_id'] ?? null,
            'nom_auteur' => $validated['nom_auteur'] ?? 'Anonyme',
            'email'     => $validated['email'] ?? null,
            'contenu'   => $validated['contenu'],
            'statut'    => 'en_attente',
            'ip'       => $request->getClientIp(),
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

        return response()->json([
            'success' => true,
            'message' => 'Commentaire envoyé. En attente de modération.',
            'data'   => $commentaire
        ], 201);
    }

    // GET /api/ministry/comments
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

        $commentaires = $query->paginate($request->per_page ?? 20);

        return response()->json(['success' => true, 'data' => $commentaires]);
    }

    // GET /api/ministry/comments/pending
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
        $total = $query->count();

        return response()->json([
            'success' => true,
            'data'   => $commentaires,
            'total'  => $total,
        ]);
    }

    // PATCH /api/ministry/comments/{id}/approve
    public function approve(Request $request, int $id)
    {
        $commentaire = ArticleCommentaire::findOrFail($id);

        if (!$request->user()->isSuperAdmin()) {
            if ($commentaire->article->ministere_id !== $request->user()->ministere_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé',
                ], 403);
            }
        }

        $commentaire->update(['statut' => 'approuve']);

        return response()->json([
            'success' => true,
            'message' => 'Commentaire approuvé',
            'data'   => $commentaire
        ]);
    }

    // PATCH /api/ministry/comments/{id}/reject
    public function reject(Request $request, int $id)
    {
        $commentaire = ArticleCommentaire::findOrFail($id);

        if (!$request->user()->isSuperAdmin()) {
            if ($commentaire->article->ministere_id !== $request->user()->ministere_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé',
                ], 403);
            }
        }

        $commentaire->update(['statut' => 'rejete']);

        return response()->json([
            'success' => true,
            'message' => 'Commentaire rejeté',
            'data'   => $commentaire
        ]);
    }

    // DELETE /api/ministry/comments/{id}
    public function destroy(Request $request, int $id)
    {
        $commentaire = ArticleCommentaire::findOrFail($id);

        if (!$request->user()->isSuperAdmin()) {
            if ($commentaire->article->ministere_id !== $request->user()->ministere_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé',
                ], 403);
            }
        }

        $commentaire->delete();

        return response()->json([
            'success' => true,
            'message' => 'Commentaire supprimé'
        ]);
    }

    // POST /api/ministry/comments/bulk-approve
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

        return response()->json([
            'success' => true,
            'message' => "{$count} commentaire(s) approuvé(s)"
        ]);
    }

    // POST /api/ministry/comments/bulk-reject
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

        return response()->json([
            'success' => true,
            'message' => "{$count} commentaire(s) rejeté(s)"
        ]);
    }

    // POST /api/ministry/comments/bulk-delete
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

        return response()->json([
            'success' => true,
            'message' => "{$count} commentaire(s) supprimé(s)"
        ]);
    }
}