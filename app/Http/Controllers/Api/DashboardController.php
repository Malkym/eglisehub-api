<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ministere;
use App\Models\User;
use App\Models\Article;
use App\Models\Page;
use App\Models\Evenement;
use App\Models\Media;
use App\Models\MessageContact;
use App\Models\LogAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    // =========================================================
    // GET /api/admin/dashboard — Super Admin
    // =========================================================
    public function global(Request $request)
    {
        // Stats globales
        $stats = [
            'ministeres' => [
                'total'     => Ministere::count(),
                'actifs'    => Ministere::where('statut', 'actif')->count(),
                'inactifs'  => Ministere::where('statut', 'inactif')->count(),
                'par_type'  => Ministere::selectRaw('type, count(*) as total')
                                        ->groupBy('type')
                                        ->pluck('total', 'type'),
            ],
            'utilisateurs' => [
                'total'          => User::count(),
                'actifs'         => User::where('actif', true)->count(),
                'super_admins'   => User::where('role', 'super_admin')->count(),
                'admin_ministeres' => User::where('role', 'admin_ministere')->count(),
            ],
            'contenus' => [
                'pages'      => Page::count(),
                'articles'   => Article::count(),
                'evenements' => Evenement::count(),
                'medias'     => Media::count(),
            ],
            'messages' => [
                'total'   => MessageContact::count(),
                'non_lus' => MessageContact::where('statut', 'non_lu')->count(),
            ],
        ];

        // Top 5 ministères les plus actifs (par nombre de contenus)
        $topMinisteres = Ministere::withCount(['articles', 'pages', 'evenements'])
            ->where('statut', 'actif')
            ->orderByRaw('articles_count + pages_count + evenements_count DESC')
            ->take(5)
            ->get(['id', 'nom', 'type', 'sous_domaine']);

        // Ministères créés par mois (12 derniers mois)
        $ministeresParMois = Ministere::selectRaw('
                DATE_FORMAT(created_at, "%Y-%m") as mois,
                count(*) as total
            ')
            ->where('created_at', '>=', now()->subMonths(12))
            ->groupBy('mois')
            ->orderBy('mois')
            ->get();

        // Dernières actions sur la plateforme
        $dernieresActions = LogAction::with('user:id,name,prenom,email')
            ->orderBy('date_action', 'desc')
            ->take(15)
            ->get();

        // Nouveaux ministères ce mois
        $nouveauxCeMois = Ministere::whereMonth('created_at', now()->month)
                                   ->whereYear('created_at', now()->year)
                                   ->count();

        return response()->json([
            'success' => true,
            'data'    => [
                'stats'              => $stats,
                'top_ministeres'     => $topMinisteres,
                'ministeres_par_mois'=> $ministeresParMois,
                'dernières_actions'  => $dernieresActions,
                'nouveaux_ce_mois'   => $nouveauxCeMois,
            ],
        ]);
    }

    // =========================================================
    // GET /api/ministry/dashboard — Admin Ministère
    // =========================================================
    public function ministry(Request $request)
    {
        $ministereId = $this->getMinistereId($request);
        $ministere   = Ministere::findOrFail($ministereId);

        // Stats du ministère
        $stats = [
            'pages' => [
                'total'      => Page::where('ministere_id', $ministereId)->count(),
                'publiees'   => Page::where('ministere_id', $ministereId)->where('statut', 'publie')->count(),
                'brouillons' => Page::where('ministere_id', $ministereId)->where('statut', 'brouillon')->count(),
            ],
            'articles' => [
                'total'        => Article::where('ministere_id', $ministereId)->count(),
                'publies'      => Article::where('ministere_id', $ministereId)->where('statut', 'publie')->count(),
                'brouillons'   => Article::where('ministere_id', $ministereId)->where('statut', 'brouillon')->count(),
                'vues_totales' => Article::where('ministere_id', $ministereId)->sum('vues'),
                'en_avant'     => Article::where('ministere_id', $ministereId)->where('en_avant', true)->count(),
                'par_type'     => Article::where('ministere_id', $ministereId)
                                         ->selectRaw('type_contenu, count(*) as total')
                                         ->groupBy('type_contenu')
                                         ->pluck('total', 'type_contenu'),
            ],
            'evenements' => [
                'total'      => Evenement::where('ministere_id', $ministereId)->count(),
                'a_venir'    => Evenement::where('ministere_id', $ministereId)->where('statut', 'a_venir')->count(),
                'en_cours'   => Evenement::where('ministere_id', $ministereId)->where('statut', 'en_cours')->count(),
                'recurrents' => Evenement::where('ministere_id', $ministereId)->where('categorie', 'recurrent')->count(),
                'permanents' => Evenement::where('ministere_id', $ministereId)->where('categorie', 'permanent')->count(),
            ],
            'medias' => [
                'total'     => Media::where('ministere_id', $ministereId)->count(),
                'images'    => Media::where('ministere_id', $ministereId)->where('type', 'image')->count(),
                'videos'    => Media::where('ministere_id', $ministereId)->where('type', 'video')->count(),
                'documents' => Media::where('ministere_id', $ministereId)->where('type', 'document')->count(),
                'taille_totale_mb' => round(
                    Media::where('ministere_id', $ministereId)->sum('taille') / 1024 / 1024,
                    2
                ),
            ],
            'messages' => [
                'total'    => MessageContact::where('ministere_id', $ministereId)->count(),
                'non_lus'  => MessageContact::where('ministere_id', $ministereId)->where('statut', 'non_lu')->count(),
                'lus'      => MessageContact::where('ministere_id', $ministereId)->where('statut', 'lu')->count(),
                'repondus' => MessageContact::where('ministere_id', $ministereId)->where('statut', 'repondu')->count(),
            ],
        ];

        // Articles les plus vus (top 5)
        $topArticles = Article::where('ministere_id', $ministereId)
            ->where('statut', 'publie')
            ->orderBy('vues', 'desc')
            ->take(5)
            ->get(['id', 'titre', 'slug', 'vues', 'type_contenu', 'date_publication']);

        // Prochains événements (5 prochains)
        $prochainsEvenements = Evenement::where('ministere_id', $ministereId)
            ->where('statut', 'a_venir')
            ->where(function($q) {
                $q->whereNotNull('date_debut')
                  ->where('date_debut', '>=', now());
            })
            ->orWhere(function($q) use ($ministereId) {
                $q->where('ministere_id', $ministereId)
                  ->whereIn('categorie', ['recurrent', 'permanent']);
            })
            ->orderBy('date_debut', 'asc')
            ->take(5)
            ->get(['id', 'titre', 'date_debut', 'heure_debut', 'lieu', 'categorie', 'type']);

        // Derniers messages non lus
        $derniersMessages = MessageContact::where('ministere_id', $ministereId)
            ->where('statut', 'non_lu')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get(['id', 'nom_expediteur', 'email', 'sujet', 'created_at']);

        // Articles publiés par mois (6 derniers mois)
        $articlesParMois = Article::where('ministere_id', $ministereId)
            ->where('statut', 'publie')
            ->where('date_publication', '>=', now()->subMonths(6))
            ->selectRaw('DATE_FORMAT(date_publication, "%Y-%m") as mois, count(*) as total')
            ->groupBy('mois')
            ->orderBy('mois')
            ->get();

        // Activité récente du ministère
        $activiteRecente = LogAction::where('ministere_id', $ministereId)
            ->with('user:id,name,prenom')
            ->orderBy('date_action', 'desc')
            ->take(10)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'ministere'          => [
                    'id'          => $ministere->id,
                    'nom'         => $ministere->nom,
                    'type'        => $ministere->type,
                    'sous_domaine'=> $ministere->sous_domaine,
                    'statut'      => $ministere->statut,
                    'logo'        => $ministere->logo,
                ],
                'stats'              => $stats,
                'top_articles'       => $topArticles,
                'prochains_evenements' => $prochainsEvenements,
                'derniers_messages'  => $derniersMessages,
                'articles_par_mois'  => $articlesParMois,
                'activite_recente'   => $activiteRecente,
            ],
        ]);
    }

    // =========================================================
    // GET /api/ministry/stats/content — Stats contenus détaillées
    // =========================================================
    public function statsContent(Request $request)
    {
        $ministereId = $this->getMinistereId($request);

        // Articles par catégorie
        $articlesParCategorie = Article::where('ministere_id', $ministereId)
            ->whereNotNull('categorie')
            ->selectRaw('categorie, count(*) as total, sum(vues) as vues')
            ->groupBy('categorie')
            ->orderBy('total', 'desc')
            ->get();

        // Articles par type de contenu
        $articlesParType = Article::where('ministere_id', $ministereId)
            ->selectRaw('type_contenu, count(*) as total, sum(vues) as vues_totales')
            ->groupBy('type_contenu')
            ->get();

        // Évolution des vues sur 30 jours
        $vuesParJour = Article::where('ministere_id', $ministereId)
            ->where('date_publication', '>=', now()->subDays(30))
            ->selectRaw('DATE(date_publication) as jour, sum(vues) as vues')
            ->groupBy('jour')
            ->orderBy('jour')
            ->get();

        // Événements par type et catégorie
        $evenementsParType = Evenement::where('ministere_id', $ministereId)
            ->selectRaw('type, categorie, count(*) as total')
            ->groupBy('type', 'categorie')
            ->get();

        // Pages dans le menu vs hors menu
        $pagesMenu = Page::where('ministere_id', $ministereId)
            ->selectRaw('dans_menu, count(*) as total')
            ->groupBy('dans_menu')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'articles_par_categorie' => $articlesParCategorie,
                'articles_par_type'      => $articlesParType,
                'vues_par_jour'          => $vuesParJour,
                'evenements_par_type'    => $evenementsParType,
                'pages_menu'             => $pagesMenu,
            ],
        ]);
    }

    // =========================================================
    // GET /api/ministry/stats/engagement — Stats engagement
    // =========================================================
    public function statsEngagement(Request $request)
    {
        $ministereId = $this->getMinistereId($request);

        // Messages par mois (6 derniers mois)
        $messagesParMois = MessageContact::where('ministere_id', $ministereId)
            ->where('created_at', '>=', now()->subMonths(6))
            ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as mois, count(*) as total')
            ->groupBy('mois')
            ->orderBy('mois')
            ->get();

        // Taux de réponse aux messages
        $totalMessages  = MessageContact::where('ministere_id', $ministereId)->count();
        $messagesRepondus = MessageContact::where('ministere_id', $ministereId)
                                          ->where('statut', 'repondu')->count();
        $tauxReponse = $totalMessages > 0
            ? round(($messagesRepondus / $totalMessages) * 100, 1)
            : 0;

        // Articles les plus vus de tous les temps
        $topArticlesAllTime = Article::where('ministere_id', $ministereId)
            ->where('statut', 'publie')
            ->orderBy('vues', 'desc')
            ->take(10)
            ->get(['id', 'titre', 'slug', 'vues', 'type_contenu', 'categorie']);

        // Vues totales par mois
        $vuesParMois = Article::where('ministere_id', $ministereId)
            ->where('date_publication', '>=', now()->subMonths(6))
            ->selectRaw('DATE_FORMAT(date_publication, "%Y-%m") as mois, sum(vues) as vues')
            ->groupBy('mois')
            ->orderBy('mois')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'messages_par_mois'    => $messagesParMois,
                'taux_reponse'         => $tauxReponse,
                'top_articles_all_time'=> $topArticlesAllTime,
                'vues_par_mois'        => $vuesParMois,
                'total_vues'           => Article::where('ministere_id', $ministereId)->sum('vues'),
            ],
        ]);
    }

    // =========================================================
    // Helpers
    // =========================================================
    private function getMinistereId(Request $request): int
    {
        if ($request->user()->isSuperAdmin() && $request->has('ministere_id')) {
            return (int) $request->ministere_id;
        }
        return $request->user()->ministere_id ?? 1;
    }
}