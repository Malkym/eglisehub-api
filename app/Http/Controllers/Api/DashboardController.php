<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Evenement;
use App\Models\LogAction;
use App\Models\Media;
use App\Models\MessageContact;
use App\Models\Ministere;
use App\Models\Page;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function global(Request $request)
    {
        $ministereStats = Ministere::selectRaw('
            COUNT(*) as total,
            SUM(CASE WHEN statut = "actif" THEN 1 ELSE 0 END) as actifs,
            SUM(CASE WHEN statut = "inactif" THEN 1 ELSE 0 END) as inactifs
        ')->first();

        $parType = Ministere::select('type')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $userStats = User::selectRaw('
            COUNT(*) as total,
            SUM(CASE WHEN actif = 1 THEN 1 ELSE 0 END) as actifs,
            SUM(CASE WHEN role = "super_admin" THEN 1 ELSE 0 END) as super_admins,
            SUM(CASE WHEN role = "admin_ministere" THEN 1 ELSE 0 END) as admin_ministeres
        ')->first();

        $contenusStats = [
            'pages'      => Page::count(),
            'articles'   => Article::count(),
            'evenements' => Evenement::count(),
            'medias'     => Media::count(),
        ];

        $messageStats = MessageContact::selectRaw('
            COUNT(*) as total,
            SUM(CASE WHEN statut = "non_lu" THEN 1 ELSE 0 END) as non_lus
        ')->first();

        $topMinisteres = Ministere::withCount(['articles', 'pages', 'evenements'])
            ->where('statut', 'actif')
            ->orderByRaw('articles_count + pages_count + evenements_count DESC')
            ->take(5)
            ->get(['id', 'nom', 'type', 'sous_domaine']);

        $ministeresParMois = Ministere::selectRaw('DATE_FORMAT(created_at, "%Y-%m") as mois, COUNT(*) as total')
            ->where('created_at', '>=', now()->subMonths(12))
            ->groupBy('mois')
            ->orderBy('mois')
            ->get();

        $dernieresActions = LogAction::with('user:id,name,prenom,email')
            ->orderBy('date_action', 'desc')
            ->take(15)
            ->get();

        $nouveauxCeMois = Ministere::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        return $this->respondSuccess([
            'stats'              => [
                'ministeres'   => [
                    'total'    => $ministereStats->total,
                    'actifs'   => $ministereStats->actifs,
                    'inactifs' => $ministereStats->inactifs,
                    'par_type' => $parType,
                ],
                'utilisateurs' => [
                    'total'           => $userStats->total,
                    'actifs'          => $userStats->actifs,
                    'super_admins'    => $userStats->super_admins,
                    'admin_ministeres' => $userStats->admin_ministeres,
                ],
                'contenus'  => $contenusStats,
                'messages'  => [
                    'total'   => $messageStats->total,
                    'non_lus' => $messageStats->non_lus,
                ],
            ],
            'top_ministeres'      => $topMinisteres,
            'ministeres_par_mois' => $ministeresParMois,
            'dernières_actions'  => $dernieresActions,
            'nouveaux_ce_mois'   => $nouveauxCeMois,
        ]);
    }

    public function ministry(Request $request)
    {
        $ministereId = $this->getMinistereId($request);
        $ministere   = Ministere::findOrFail($ministereId);

        $pageStats = Page::where('ministere_id', $ministereId)
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN statut = "publie" THEN 1 ELSE 0 END) as publiees, SUM(CASE WHEN statut = "brouillon" THEN 1 ELSE 0 END) as brouillons')->first();

        $articleStats = Article::where('ministere_id', $ministereId)
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN statut = "publie" THEN 1 ELSE 0 END) as publies, SUM(CASE WHEN statut = "brouillon" THEN 1 ELSE 0 END) as brouillons, SUM(CASE WHEN en_avant = 1 THEN 1 ELSE 0 END) as en_avant, COALESCE(SUM(vues), 0) as vues_totales')->first();

        $articleParType = Article::where('ministere_id', $ministereId)
            ->select('type_contenu')->selectRaw('COUNT(*) as total')->groupBy('type_contenu')->pluck('total', 'type_contenu');

        $evenementStats = Evenement::where('ministere_id', $ministereId)
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN statut = "a_venir" THEN 1 ELSE 0 END) as a_venir, SUM(CASE WHEN statut = "en_cours" THEN 1 ELSE 0 END) as en_cours, SUM(CASE WHEN categorie = "recurrent" THEN 1 ELSE 0 END) as recurrents, SUM(CASE WHEN categorie = "permanent" THEN 1 ELSE 0 END) as permanents')->first();

        $mediaStats = Media::where('ministere_id', $ministereId)
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN type = "image" THEN 1 ELSE 0 END) as images, SUM(CASE WHEN type = "video" THEN 1 ELSE 0 END) as videos, SUM(CASE WHEN type = "document" THEN 1 ELSE 0 END) as documents, ROUND(SUM(taille) / 1024 / 1024, 2) as taille_totale_mb')->first();

        $messageStats = MessageContact::where('ministere_id', $ministereId)
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN statut = "non_lu" THEN 1 ELSE 0 END) as non_lus, SUM(CASE WHEN statut = "lu" THEN 1 ELSE 0 END) as lus, SUM(CASE WHEN statut = "repondu" THEN 1 ELSE 0 END) as respondues')->first();

        $topArticles = Article::where('ministere_id', $ministereId)
            ->where('statut', 'publie')->orderBy('vues', 'desc')->take(5)
            ->get(['id', 'titre', 'slug', 'vues', 'type_contenu', 'date_publication']);

        $prochainsEvenements = Evenement::where('ministere_id', $ministereId)
            ->where(function ($q) {
                $q->whereNotNull('date_debut')->where('date_debut', '>=', now());
            })
            ->orWhere(function ($q) use ($ministereId) {
                $q->where('ministere_id', $ministereId)->whereIn('categorie', ['recurrent', 'permanent']);
            })
            ->orderBy('date_debut', 'asc')->take(5)
            ->get(['id', 'titre', 'date_debut', 'heure_debut', 'lieu', 'categorie', 'type']);

        $derniersMessages = MessageContact::where('ministere_id', $ministereId)
            ->where('statut', 'non_lu')->orderBy('created_at', 'desc')->take(5)
            ->get(['id', 'nom_expediteur', 'email', 'sujet', 'created_at']);

        $articlesParMois = Article::where('ministere_id', $ministereId)
            ->where('statut', 'publie')->where('date_publication', '>=', now()->subMonths(6))
            ->selectRaw('DATE_FORMAT(date_publication, "%Y-%m") as mois, COUNT(*) as total')->groupBy('mois')->orderBy('mois')->get();

        $activiteRecente = LogAction::where('ministere_id', $ministereId)
            ->with('user:id,name,prenom')->orderBy('date_action', 'desc')->take(10)->get();

        return $this->respondSuccess([
            'ministere'            => [
                'id'           => $ministere->id,
                'nom'          => $ministere->nom,
                'type'         => $ministere->type,
                'sous_domaine' => $ministere->sous_domaine,
                'statut'       => $ministere->statut,
                'logo'         => $ministere->logo,
            ],
            'stats'                => [
                'pages'      => ['total' => $pageStats->total, 'publiees' => $pageStats->publiees, 'brouillons' => $pageStats->brouillons],
                'articles'   => ['total' => $articleStats->total, 'publies' => $articleStats->publies, 'brouillons' => $articleStats->brouillons, 'vues_totales' => $articleStats->vues_totales, 'en_avant' => $articleStats->en_avant, 'par_type' => $articleParType],
                'evenements' => ['total' => $evenementStats->total, 'a_venir' => $evenementStats->a_venir, 'en_cours' => $evenementStats->en_cours, 'recurrents' => $evenementStats->recurrents, 'permanents' => $evenementStats->permanents],
                'medias'     => ['total' => $mediaStats->total, 'images' => $mediaStats->images, 'videos' => $mediaStats->videos, 'documents' => $mediaStats->documents, 'taille_totale_mb' => $mediaStats->taille_totale_mb],
                'messages'   => ['total' => $messageStats->total, 'non_lus' => $messageStats->non_lus, 'lus' => $messageStats->lus, 'repondus' => $messageStats->respondues],
            ],
            'top_articles'         => $topArticles,
            'prochains_evenements' => $prochainsEvenements,
            'derniers_messages'    => $derniersMessages,
            'articles_par_mois'    => $articlesParMois,
            'activite_recente'     => $activiteRecente,
        ]);
    }

    public function statsContent(Request $request)
    {
        $ministereId = $this->getMinistereId($request);

        $articlesParCategorie = Article::where('ministere_id', $ministereId)->whereNotNull('categorie')
            ->selectRaw('categorie, COUNT(*) as total, SUM(vues) as vues')->groupBy('categorie')->orderBy('total', 'desc')->get();

        $articlesParType = Article::where('ministere_id', $ministereId)
            ->selectRaw('type_contenu, COUNT(*) as total, SUM(vues) as vues_totales')->groupBy('type_contenu')->get();

        $vuesParJour = Article::where('ministere_id', $ministereId)->where('date_publication', '>=', now()->subDays(30))
            ->selectRaw('DATE(date_publication) as jour, SUM(vues) as vues')->groupBy('jour')->orderBy('jour')->get();

        $evenementsParType = Evenement::where('ministere_id', $ministereId)
            ->selectRaw('type, categorie, COUNT(*) as total')->groupBy('type', 'categorie')->get();

        $pagesMenu = Page::where('ministere_id', $ministereId)
            ->selectRaw('dans_menu, COUNT(*) as total')->groupBy('dans_menu')->get();

        return $this->respondSuccess([
            'articles_par_categorie' => $articlesParCategorie,
            'articles_par_type'      => $articlesParType,
            'vues_par_jour'           => $vuesParJour,
            'evenements_par_type'     => $evenementsParType,
            'pages_menu'              => $pagesMenu,
        ]);
    }

    public function statsEngagement(Request $request)
    {
        $ministereId = $this->getMinistereId($request);

        $messagesParMois = MessageContact::where('ministere_id', $ministereId)->where('created_at', '>=', now()->subMonths(6))
            ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as mois, COUNT(*) as total')->groupBy('mois')->orderBy('mois')->get();

        $messageStats = MessageContact::where('ministere_id', $ministereId)
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN statut = "repondu" THEN 1 ELSE 0 END) as respondues')->first();

        $tauxReponse = $messageStats->total > 0 ? round(($messageStats->respondues / $messageStats->total) * 100, 1) : 0;

        $topArticlesAllTime = Article::where('ministere_id', $ministereId)->where('statut', 'publie')
            ->orderBy('vues', 'desc')->take(10)->get(['id', 'titre', 'slug', 'vues', 'type_contenu', 'categorie']);

        $vuesParMois = Article::where('ministere_id', $ministereId)->where('date_publication', '>=', now()->subMonths(6))
            ->selectRaw('DATE_FORMAT(date_publication, "%Y-%m") as mois, SUM(vues) as vues')->groupBy('mois')->orderBy('mois')->get();

        $totalVues = Article::where('ministere_id', $ministereId)->sum('vues');

        return $this->respondSuccess([
            'messages_par_mois'     => $messagesParMois,
            'taux_reponse'          => $tauxReponse,
            'top_articles_all_time' => $topArticlesAllTime,
            'vues_par_mois'         => $vuesParMois,
            'total_vues'            => $totalVues,
        ]);
    }
}