<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class ArticleController extends Controller
{
    private const RATE_LIMIT_MAX = 10;
    private const RATE_LIMIT_DECAY = 3600;

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

        return $this->respondPaginated($articles);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'titre'              => 'required|string|max:255',
            'type_contenu'       => 'required|in:texte,lien_externe,video_youtube,audio,mixte',
            'resume'             => 'nullable|string|max:500',
            'contenu'            => 'nullable|string',
            'image_une'          => 'nullable|string',
            'categorie'          => 'nullable|string|max:100',
            'url_externe'        => 'required_if:type_contenu,lien_externe|nullable|url',
            'youtube_id'         => 'required_if:type_contenu,video_youtube|nullable|string|max:20',
            'duree'              => 'nullable|string|max:20',
            'auteur_externe'     => 'nullable|string|max:255',
            'en_avant'           => 'nullable|boolean',
            'commentaires_actifs' => 'nullable|boolean',
            'statut'             => 'nullable|in:publie,brouillon',
            'date_publication'   => 'nullable|date',
        ]);

        $ministereId = $this->getMinistereId($request);
        $slug = $this->generateUniqueSlug($validated['titre'], $ministereId, Article::class);

        $article = Article::create([
            'ministere_id'       => $ministereId,
            'user_id'            => $request->user()->id,
            'titre'              => $validated['titre'],
            'slug'               => $slug,
            'type_contenu'       => $validated['type_contenu'],
            'resume'             => $validated['resume'] ?? null,
            'contenu'            => $validated['contenu'] ?? null,
            'image_une'          => $validated['image_une'] ?? null,
            'categorie'          => $validated['categorie'] ?? null,
            'url_externe'        => $validated['url_externe'] ?? null,
            'youtube_id'         => $validated['youtube_id'] ?? null,
            'duree'              => $validated['duree'] ?? null,
            'auteur_externe'     => $validated['auteur_externe'] ?? null,
            'en_avant'           => $validated['en_avant'] ?? false,
            'commentaires_actifs' => $validated['commentaires_actifs'] ?? true,
            'statut'             => $validated['statut'] ?? 'brouillon',
            'date_publication'   => ($validated['statut'] ?? 'brouillon') === 'publie'
                ? ($validated['date_publication'] ?? now())
                : ($validated['date_publication'] ?? null),
        ]);

        $this->log($request, 'create_article', 'articles', "Création article: {$article->titre}");

        return $this->respondSuccess(
            $article->load('auteur:id,name,prenom'),
            'Article créé avec succès.',
            201
        );
    }

    public function show(Request $request, string $id)
    {
        $article = $this->findForMinistere($request, Article::class, $id);

        return $this->respondSuccess($article->load('auteur:id,name,prenom'));
    }

    public function update(Request $request, string $id)
    {
        $article = $this->findForMinistere($request, Article::class, $id);

        $validated = $request->validate([
            'titre'              => 'sometimes|string|max:255',
            'type_contenu'       => 'sometimes|in:texte,lien_externe,video_youtube,audio,mixte',
            'resume'             => 'nullable|string|max:500',
            'contenu'            => 'nullable|string',
            'image_une'          => 'nullable|string',
            'categorie'          => 'nullable|string|max:100',
            'url_externe'        => 'nullable|url',
            'youtube_id'         => 'nullable|string|max:20',
            'duree'              => 'nullable|string|max:20',
            'auteur_externe'     => 'nullable|string|max:255',
            'en_avant'           => 'nullable|boolean',
            'commentaires_actifs' => 'nullable|boolean',
            'statut'             => 'nullable|in:publie,brouillon',
            'date_publication'   => 'nullable|date',
        ]);

        if ($request->has('titre') && $request->titre !== $article->titre) {
            $validated['slug'] = $this->generateUniqueSlug($request->titre, $article->ministere_id, Article::class, $id);
        }

        if ($request->statut === 'publie' && !$article->date_publication) {
            $validated['date_publication'] = now();
        }

        $article->update(array_filter($validated));

        $this->log($request, 'update_article', 'articles', "Modification article: {$article->titre}");

        return $this->respondSuccess(
            $article->fresh()->load('auteur:id,name,prenom'),
            'Article mis à jour.'
        );
    }

    public function destroy(Request $request, string $id)
    {
        $article = $this->findForMinistere($request, Article::class, $id);
        $titre = $article->titre;

        $article->delete();

        $this->log($request, 'delete_article', 'articles', "Suppression article: {$titre}");

        return $this->respondSuccess(null, 'Article supprimé.');
    }

    public function publish(Request $request, string $id)
    {
        $article = $this->findForMinistere($request, Article::class, $id);

        if ($article->statut === 'publie') {
            $article->update(['statut' => 'brouillon']);
            $message = 'Article dépublié.';
        } else {
            $article->update([
                'statut'          => 'publie',
                'date_publication' => $article->date_publication ?? now(),
            ]);
            $message = 'Article publié.';
        }

        $this->log($request, 'toggle_publish_article', 'articles', "{$message} : {$article->titre}");

        return $this->respondSuccess(
            ['statut' => $article->fresh()->statut, 'data' => $article->fresh()],
            $message
        );
    }

    public function feature(Request $request, string $id)
    {
        $article = $this->findForMinistere($request, Article::class, $id);

        $article->update(['en_avant' => !$article->en_avant]);
        $etat = $article->fresh()->en_avant ? 'mis en avant' : 'retiré de la une';

        return $this->respondSuccess($article->fresh(), "Article {$etat}.");
    }

    public function getRating(Request $request, string $slug)
    {
        $subdomain = $this->resolveSubdomain($request);

        $article = Article::whereHas(
            'ministere',
            fn($q) => $q->where('sous_domaine', $subdomain)->where('statut', 'actif')
        )->where('slug', $slug)->where('statut', 'publie')->firstOrFail();

        return $this->respondSuccess([
            'average' => round($article->average_rating, 1),
            'count'   => $article->rating_count,
        ]);
    }

    public function rate(Request $request, string $slug)
    {
        $rateLimitKey = 'rate:' . $slug . ':' . ($request->ip() ?? 'unknown');

        if (RateLimiter::tooManyAttempts($rateLimitKey, self::RATE_LIMIT_MAX)) {
            return $this->respondWithError('Vous avez déjà noté cet article récemment.', 429);
        }

        $request->validate([
            'note' => 'required|integer|min:1|max:5',
        ]);

        $subdomain = $this->resolveSubdomain($request);

        $article = Article::whereHas(
            'ministere',
            fn($q) => $q->where('sous_domaine', $subdomain)->where('statut', 'actif')
        )->where('slug', $slug)->where('statut', 'publie')->firstOrFail();

        $ip = $request->getClientIp();
        $sessionId = $request->header('X-Session-Id') ?? null;

        if ($article->hasUserRated($ip, $sessionId)) {
            RateLimiter::hit($rateLimitKey, self::RATE_LIMIT_DECAY);
            return $this->respondWithError('Vous avez déjà noté cet article.', 429);
        }

        $article->addRating($request->note, $ip, $sessionId);
        RateLimiter::hit($rateLimitKey, self::RATE_LIMIT_DECAY);

        return $this->respondSuccess([
            'average' => round($article->fresh()->average_rating, 1),
            'count'   => $article->fresh()->rating_count,
        ], 'Merci pour votre note !');
    }

    public function publicIndex(Request $request)
    {
        $subdomain = $this->resolveSubdomain($request);

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

        return $this->respondPaginated($query->orderBy('date_publication', 'desc')->paginate($request->per_page ?? 12));
    }

    public function publicShow(Request $request, string $slug)
    {
        $subdomain = $this->resolveSubdomain($request);

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

        return $this->respondSuccess($article->load('auteur:id,name,prenom'));
    }
}