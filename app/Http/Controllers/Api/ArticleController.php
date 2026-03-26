<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\LogAction;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ArticleController extends Controller
{
    // GET /api/ministry/articles
    public function index(Request $request)
    {
        $ministereId = $this->getMinistereId($request);
        $articles = Article::when($ministereId, fn($q) => $q->where('ministere_id', $ministereId))
            ->with('auteur:id,name,prenom')
            ->when($request->statut,       fn($q) => $q->where('statut', $request->statut))
            ->when($request->categorie,    fn($q) => $q->where('categorie', $request->categorie))
            ->when($request->type_contenu, fn($q) => $q->where('type_contenu', $request->type_contenu))
            ->when($request->en_avant,     fn($q) => $q->where('en_avant', true))
            ->when($request->search,       fn($q) => $q->where('titre', 'like', "%{$request->search}%"))
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json(['success' => true, 'data' => $articles]);
    }

    // POST /api/ministry/articles
    public function store(Request $request)
    {
        $request->validate([
            'titre'          => 'required|string|max:255',
            'type_contenu'   => 'required|in:texte,lien_externe,video_youtube,audio,mixte',
            'resume'         => 'nullable|string|max:500',
            'contenu'        => 'nullable|string',
            'image_une'      => 'nullable|string',
            'categorie'      => 'nullable|string|max:100',
            'url_externe'    => 'required_if:type_contenu,lien_externe|nullable|url',
            'youtube_id'     => 'required_if:type_contenu,video_youtube|nullable|string|max:20',
            'duree'          => 'nullable|string|max:20',
            'auteur_externe' => 'nullable|string|max:255',
            'en_avant'       => 'boolean',
            'commentaires_actifs' => 'boolean', // AJOUTER
            'statut'         => 'in:publie,brouillon',
            'date_publication' => 'nullable|date',
        ]);

        $ministereId = $this->getMinistereId($request);
        $slug = $this->generateUniqueSlug($request->titre, $ministereId);

        $article = Article::create([
            'ministere_id'     => $ministereId,
            'user_id'          => $request->user()->id,
            'titre'            => $request->titre,
            'slug'             => $slug,
            'type_contenu'     => $request->type_contenu,
            'resume'           => $request->resume,
            'contenu'          => $request->contenu,
            'image_une'        => $request->image_une,
            'categorie'        => $request->categorie,
            'url_externe'      => $request->url_externe,
            'youtube_id'       => $request->youtube_id,
            'duree'            => $request->duree,
            'auteur_externe'   => $request->auteur_externe,
            'en_avant'         => $request->en_avant ?? false,
            'commentaires_actifs' => $request->commentaires_actifs ?? true, // AJOUTER
            'statut'           => $request->statut ?? 'brouillon',
            'date_publication' => $request->statut === 'publie'
                ? ($request->date_publication ?? now())
                : $request->date_publication,
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
        $request->validate([
            'titre'          => 'required|string|max:255',
            'type_contenu'   => 'required|in:texte,lien_externe,video_youtube,audio,mixte',
            'resume'         => 'nullable|string|max:500',
            'contenu'        => 'nullable|string',
            'image_une'      => 'nullable|string',
            'categorie'      => 'nullable|string|max:100',
            'url_externe'    => 'required_if:type_contenu,lien_externe|nullable|url',
            'youtube_id'     => 'required_if:type_contenu,video_youtube|nullable|string|max:20',
            'duree'          => 'nullable|string|max:20',
            'auteur_externe' => 'nullable|string|max:255',
            'en_avant'       => 'boolean',
            'commentaires_actifs' => 'boolean', // AJOUTER
            'statut'         => 'in:publie,brouillon',
            'date_publication' => 'nullable|date',
        ]);

        if ($request->has('titre') && $request->titre !== $article->titre) {
            $request->merge([
                'slug' => $this->generateUniqueSlug($request->titre, $article->ministere_id, $id)
            ]);
        }

        // Si on publie maintenant, on fixe la date
        if ($request->statut === 'publie' && ! $article->date_publication) {
            $request->merge(['date_publication' => now()]);
        }

        $article->update($request->only([
            'titre',
            'slug',
            'type_contenu',
            'resume',
            'contenu',
            'image_une',
            'categorie',
            'url_externe',
            'youtube_id',
            'duree',
            'auteur_externe',
            'en_avant',
            'commentaires_actifs',
            'statut',
            'date_publication',
        ]));

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
// APRÈS — toggle publication/dépublication
    /**
     * @OA\Patch(
     *     path="/ministry/articles/{id}/publish",
     *     tags={"Articles"},
     *     summary="Publier ou dépublier un article (toggle)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Statut modifié",
     *         @OA\JsonContent(
     *             @OA\Property(property="success",  type="boolean", example=true),
     *             @OA\Property(property="message",  type="string",  example="Article publié."),
     *             @OA\Property(property="statut",   type="string",  enum={"publie","brouillon"}),
     *             @OA\Property(property="data",     type="object")
     *         )
     *     )
     * )
     */
    public function publish(Request $request, string $id)
    {
        $article = Article::findOrFail($id);

        // Vérifier ownership
        if (! $request->user()->isSuperAdmin()) {
            if ($article->ministere_id !== $request->user()->ministere_id) {
                return response()->json(['success' => false, 'message' => 'Accès refusé.'], 403);
            }
        }

        // Toggle
        if ($article->statut === 'publie') {
            $article->update(['statut' => 'brouillon']);
            $message = 'Article dépublié.';
        } else {
            $article->update([
                'statut'           => 'publie',
                'date_publication' => $article->date_publication ?? now(),
            ]);
            $message = 'Article publié.';
        }

        $this->log(
            $request,
            'toggle_publish_article',
            'articles',
            "{$message} : {$article->titre}"
        );

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
        $article->update(['en_avant' => ! $article->en_avant]);
        $etat = $article->fresh()->en_avant ? 'mis en avant' : 'retiré de la une';

        return response()->json(['success' => true, 'message' => "Article {$etat}.", 'data' => $article->fresh()]);
    }


    // Helpers
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

    private function findArticleForUser(Request $request, string $id): Article
    {
        $article = Article::findOrFail($id);

        if (! $request->user()->isSuperAdmin()) {
            if ($article->ministere_id !== $request->user()->ministere_id) {
                abort(403, 'Accès refusé à cet article.');
            }
        }

        return $article;
    }

    private function generateUniqueSlug(string $titre, int $ministereId, ?string $exceptId = null): string
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

    /**
     * @OA\Get(
     *     path="/public/articles/{slug}/rating",
     *     tags={"Public - Articles"},
     *     summary="Récupérer la note moyenne d'un article",
     *     @OA\Parameter(name="slug", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="subdomain", in="query", @OA\Schema(type="string", example="crc")),
     *     @OA\Response(response=200, description="Note moyenne",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="average", type="number", format="float", example=4.5),
     *                 @OA\Property(property="count", type="integer", example=12)
     *             )
     *         )
     *     )
     * )
     */

    /**
     * GET /api/public/articles/{slug}/rating
     * Récupérer la note moyenne d'un article
     */
    public function getRating(Request $request, string $slug)
    {
        $subdomain = $request->query('subdomain') ?? 'crc';

        $article = Article::whereHas(
            'ministere',
            fn($q) =>
            $q->where('sous_domaine', $subdomain)->where('statut', 'actif')
        )->where('slug', $slug)->where('statut', 'publie')->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => [
                'average' => round($article->average_rating, 1),
                'count' => $article->rating_count,
            ]
        ]);
    }

    /**
     * @OA\Post(
     *     path="/public/articles/{slug}/rate",
     *     tags={"Public - Articles"},
     *     summary="Noter un article",
     *     @OA\Parameter(name="slug", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="subdomain", in="query", @OA\Schema(type="string", example="crc")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="note", type="integer", minimum=1, maximum=5, example=5)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Note enregistrée",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Merci pour votre note !"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="average", type="number", format="float", example=4.5),
     *                 @OA\Property(property="count", type="integer", example=13)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=429, description="Déjà noté",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Vous avez déjà noté cet article.")
     *         )
     *     )
     * )
     */

    /**
     * POST /api/public/articles/{slug}/rate
     * Noter un article
     */
    public function rate(Request $request, string $slug)
    {
        $request->validate([
            'note' => 'required|integer|min:1|max:5',
        ]);

        $subdomain = $request->query('subdomain') ?? 'crc';

        $article = Article::whereHas(
            'ministere',
            fn($q) =>
            $q->where('sous_domaine', $subdomain)->where('statut', 'actif')
        )->where('slug', $slug)->where('statut', 'publie')->firstOrFail();

        // Récupérer l'IP et la session
        $ip = $request->ip();
        $sessionId = $request->header('X-Session-Id') ?? session()->getId();

        // Vérifier si l'utilisateur a déjà voté
        if ($article->hasUserRated($ip, $sessionId)) {
            return response()->json([
                'success' => false,
                'message' => 'Vous avez déjà noté cet article.'
            ], 429);
        }

        // Ajouter la note
        $article->addRating($request->note, $ip, $sessionId);

        return response()->json([
            'success' => true,
            'message' => 'Merci pour votre note !',
            'data' => [
                'average' => round($article->fresh()->average_rating, 1),
                'count' => $article->fresh()->rating_count,
            ]
        ]);
    }

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
            ->withCount(['tousCommentaires as commentaires_count' => function ($q) {
                $q->where('statut', 'approuve');
            }]);

        // Support pour plusieurs catégories (séparées par des virgules)
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

    // Mettre à jour la méthode publicShow pour inclure les notes
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

        // Incrémenter les vues
        $article->increment('vues');

        return response()->json(['success' => true, 'data' => $article->load('auteur:id,name,prenom')]);
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
