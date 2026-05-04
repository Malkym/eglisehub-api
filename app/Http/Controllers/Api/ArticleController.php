<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\LogAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class ArticleController extends Controller
{
    private const RATE_LIMIT_MAX = 10;
    private const RATE_LIMIT_DECAY = 3600;

    // GET /api/ministry/articles
    public function index(Request $request)
    {
        $ministereId = $this->getMinistereId($request);
        
        $articles = Article::when($ministereId, fn($q) => $q->where('ministere_id', $ministereId))
            ->with('auteur:id,name,prenom')
            ->when($request->statut, fn($q) => $q->where('statut', $request->statut))
            ->when($request->categorie, fn($q) => $q->where('categorie', $request->categorie))
            ->when($request->type_contenu, fn($q) => $q->where('type_contenu', $request->type_contenu))
            ->when($request->en_avant, fn($q) => $q->where('en_avant', true))
            ->when($request->search, fn($q) => $q->where('titre', 'like', "%{$request->search}%"))
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json(['success' => true, 'data' => $articles]);
    }

    // POST /api/ministry/articles
    public function store(Request $request)
    {
        $validated = $request->validate([
            'titre'              => 'required|string|max:255',
            'type_contenu'      => 'required|in:texte,lien_externe,video_youtube,audio,mixte',
            'resume'            => 'nullable|string|max:500',
            'contenu'           => 'nullable|string',
            'image_une'         => 'nullable|string',
            'categorie'         => 'nullable|string|max:100',
            'url_externe'       => 'required_if:type_contenu,lien_externe|nullable|url',
            'youtube_id'        => 'required_if:type_contenu,video_youtube|nullable|string|max:20',
            'duree'             => 'nullable|string|max:20',
            'auteur_externe'    => 'nullable|string|max:255',
            'en_avant'          => 'nullable|boolean',
            'commentaires_actifs' => 'nullable|boolean',
            'statut'            => 'nullable|in:publie,brouillon',
            'date_publication'   => 'nullable|date',
        ]);

        $ministereId = $this->getMinistereId($request);
        $slug = $this->generateUniqueSlug($validated['titre'], $ministereId);

        $article = Article::create([
            'ministere_id'      => $ministereId,
            'user_id'           => $request->user()->id,
            'titre'             => $validated['titre'],
            'slug'              => $slug,
            'type_contenu'      => $validated['type_contenu'],
            'resume'           => $validated['resume'] ?? null,
            'contenu'          => $validated['contenu'] ?? null,
            'image_une'        => $validated['image_une'] ?? null,
            'categorie'        => $validated['categorie'] ?? null,
            'url_externe'      => $validated['url_externe'] ?? null,
            'youtube_id'       => $validated['youtube_id'] ?? null,
            'duree'            => $validated['duree'] ?? null,
            'auteur_externe'   => $validated['auteur_externe'] ?? null,
            'en_avant'         => $validated['en_avant'] ?? false,
            'commentaires_actifs' => $validated['commentaires_actifs'] ?? true,
            'statut'           => $validated['statut'] ?? 'brouillon',
            'date_publication' => ($validated['statut'] ?? 'brouillon') === 'publie'
                ? ($validated['date_publication'] ?? now())
                : ($validated['date_publication'] ?? null),
        ]);

        $this->log($request, 'create_article', 'articles', "Création article: {$article->titre}");

        return response()->json([
            'success' => true,
            'message' => 'Article créé avec succès.',
            'data'    => $article->load('auteur:id,name,prenom'),
        ], 201);
    }

    // GET /api/ministry/articles/{id}
    public function show(Request $request, string $id)
    {
        $article = $this->findArticleForUser($request, $id);
        
        return response()->json(['success' => true, 'data' => $article->load('auteur:id,name,prenom')]);
    }

    // PUT /api/ministry/articles/{id}
    public function update(Request $request, string $id)
    {
        $article = $this->findArticleForUser($request, $id);
        
        $validated = $request->validate([
            'titre'              => 'sometimes|string|max:255',
            'type_contenu'      => 'sometimes|in:texte,lien_externe,video_youtube,audio,mixte',
            'resume'            => 'nullable|string|max:500',
            'contenu'           => 'nullable|string',
            'image_une'         => 'nullable|string',
            'categorie'         => 'nullable|string|max:100',
            'url_externe'       => 'nullable|url',
            'youtube_id'        => 'nullable|string|max:20',
            'duree'             => 'nullable|string|max:20',
            'auteur_externe'    => 'nullable|string|max:255',
            'en_avant'          => 'nullable|boolean',
            'commentaires_actifs' => 'nullable|boolean',
            'statut'            => 'nullable|in:publie,brouillon',
            'date_publication'   => 'nullable|date',
        ]);

        if ($request->has('titre') && $request->titre !== $article->titre) {
            $validated['slug'] = $this->generateUniqueSlug($request->titre, $article->ministere_id, $id);
        }

        if ($request->statut === 'publie' && !$article->date_publication) {
            $validated['date_publication'] = now();
        }

        $article->update(array_filter($validated));

        $this->log($request, 'update_article', 'articles', "Modification article: {$article->titre}");

        return response()->json([
            'success' => true,
            'message' => 'Article mis à jour.',
            'data'    => $article->fresh()->load('auteur:id,name,prenom'),
        ]);
    }

    // DELETE /api/ministry/articles/{id}
    public function destroy(Request $request, string $id)
    {
        $article = $this->findArticleForUser($request, $id);
        $titre = $article->titre;
        
        $article->delete();

        $this->log($request, 'delete_article', 'articles', "Suppression article: {$titre}");

        return response()->json(['success' => true, 'message' => 'Article supprimé.']);
    }

    // PATCH /api/ministry/articles/{id}/publish
    public function publish(Request $request, string $id)
    {
        if (!$request->user()->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'Accès refusé.'], 403);
        }

        $article = Article::findOrFail($id);

        if ($request->user()->isSuperAdmin() && $article->ministere_id !== $request->user()->ministere_id) {
            return response()->json(['success' => false, 'message' => 'Accès refusé.'], 403);
        }

        if ($article->statut === 'publie') {
            $article->update(['statut' => 'brouillon']);
            $message = 'Article dépublié.';
        } else {
            $article->update([
                'statut'            => 'publie',
                'date_publication' => $article->date_publication ?? now(),
            ]);
            $message = 'Article publié.';
        }

        $this->log($request, 'toggle_publish_article', 'articles', "{$message} : {$article->titre}");

        return response()->json([
            'success' => true,
            'message' => $message,
            'statut'  => $article->fresh()->statut,
            'data'    => $article->fresh(),
        ]);
    }

    // PATCH /api/ministry/articles/{id}/feature
    public function feature(Request $request, string $id)
    {
        $article = $this->findArticleForUser($request, $id);
        
        $article->update(['en_avant' => !$article->en_avant]);
        $etat = $article->fresh()->en_avant ? 'mis en avant' : 'retiré de la une';

        return response()->json([
            'success' => true, 
            'message' => "Article {$etat}.", 
            'data' => $article->fresh()
        ]);
    }

    // GET /api/public/articles/{slug}/rating
    public function getRating(Request $request, string $slug)
    {
        $subdomain = $request->query('subdomain', 'crc');

        $article = Article::whereHas(
            'ministere',
            fn($q) => $q->where('sous_domaine', $subdomain)->where('statut', 'actif')
        )->where('slug', $slug)->where('statut', 'publie')->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => [
                'average' => round($article->average_rating, 1),
                'count'  => $article->rating_count,
            ]
        ]);
    }

    // POST /api/public/articles/{slug}/rate
    public function rate(Request $request, string $slug)
    {
        $rateLimitKey = 'rate:' . $slug . ':' . ($request->ip() ?? 'unknown');
        
        if (RateLimiter::tooManyAttempts($rateLimitKey, self::RATE_LIMIT_MAX)) {
            return response()->json([
                'success' => false,
                'message' => 'Vous avez déjà noté cet article récemment.',
            ], 429);
        }

        $request->validate([
            'note' => 'required|integer|min:1|max:5',
        ]);

        $subdomain = $request->query('subdomain', 'crc');

        $article = Article::whereHas(
            'ministere',
            fn($q) => $q->where('sous_domaine', $subdomain)->where('statut', 'actif')
        )->where('slug', $slug)->where('statut', 'publie')->firstOrFail();

        $ip = $request->getClientIp();
        $sessionId = $request->header('X-Session-Id') ?? null;

        if ($article->hasUserRated($ip, $sessionId)) {
            RateLimiter::hit($rateLimitKey, self::RATE_LIMIT_DECAY);
            return response()->json([
                'success' => false,
                'message' => 'Vous avez déjà noté cet article.',
            ], 429);
        }

        $article->addRating($request->note, $ip, $sessionId);
        RateLimiter::hit($rateLimitKey, self::RATE_LIMIT_DECAY);

        return response()->json([
            'success' => true,
            'message' => 'Merci pour votre note !',
            'data'    => [
                'average' => round($article->fresh()->average_rating, 1),
                'count'   => $article->fresh()->rating_count,
            ]
        ]);
    }

    // GET /api/public/articles
    public function publicIndex(Request $request)
    {
        $subdomain = $request->header('X-Subdomain') ?? $request->query('subdomain') ?? 'crc';

        $query = Article::whereHas(
            'ministere',
            fn($q) => $q->where('sous_domaine', $subdomain)->where('statut', 'actif')
        )
            ->where('statut', 'publie')
            ->with('auteur:id,name,prenom')
            ->withAvg('notes', 'note')
            ->withCount('notes')
            ->withCount(['commentaires as commentaires_count' => function ($q) {
                $q->where('statut', 'approuve');
            }]);

        if ($request->has('categories')) {
            $categories = explode(',', $request->categories);
            $query->whereIn('categorie', $categories);
        } elseif ($request->has('categorie')) {
            $query->where('categorie', $request->categorie);
        }

        if ($request->has('type_contenu')) {
            $query->where('type_contenu', $request->type_contenu);
        }

        if ($request->has('en_avant')) {
            $query->where('en_avant', true);
        }

        if ($request->has('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('titre', 'like', "%{$request->search}%")
                    ->orWhere('contenu', 'like', "%{$request->search}%");
            });
        }

        $articles = $query->orderBy('date_publication', 'desc')->paginate($request->per_page ?? 12);

        return response()->json(['success' => true, 'data' => $articles]);
    }

    // GET /api/public/articles/{slug}
    public function publicShow(Request $request, string $slug)
    {
        $subdomain = $request->header('X-Subdomain') ?? $request->query('subdomain') ?? 'crc';

        $article = Article::whereHas(
            'ministere',
            fn($q) => $q->where('sous_domaine', $subdomain)->where('statut', 'actif')
        )
            ->where('slug', $slug)
            ->where('statut', 'publie')
            ->with('auteur:id,name,prenom')
            ->withAvg('notes', 'note')
            ->withCount('notes')
            ->firstOrFail();

        $article->increment('vues');

        return response()->json(['success' => true, 'data' => $article->load('auteur:id,name,prenom')]);
    }

    protected function getMinistereId(Request $request): ?int
    {
        if ($request->user()->isSuperAdmin()) {
            if ($request->has('ministere_id')) {
                return (int) $request->ministere_id;
            }
            return null;
        }

        return $request->user()->ministere_id;
    }

    protected function findArticleForUser(Request $request, string $id): Article
    {
        $article = Article::findOrFail($id);

        if (!$request->user()->isSuperAdmin()) {
            if ($article->ministere_id !== $request->user()->ministere_id) {
                abort(403, 'Accès refusé à cet article.');
            }
        }

        return $article;
    }

    protected function generateUniqueSlug(string $titre, int $ministereId, ?string $exceptId = null): string
    {
        $slug = Str::slug($titre);
        $original = $slug;
        $count = 1;

        while (
            Article::where('ministere_id', $ministereId)
                ->where('slug', $slug)
                ->when($exceptId, fn($q) => $q->where('id', '!=', $exceptId))
                ->exists()
        ) {
            $slug = $original . '-' . $count++;
        }

        return $slug;
    }
}