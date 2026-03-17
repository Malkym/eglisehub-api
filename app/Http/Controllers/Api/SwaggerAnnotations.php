<?php

namespace App\Http\Controllers\Api;

/**
 * =====================================================
 * PAGES
 * =====================================================
 *
 * @OA\Get(path="/ministry/pages", tags={"Pages"}, summary="Liste les pages du ministère",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="statut",      in="query", @OA\Schema(type="string", enum={"publie","brouillon"})),
 *     @OA\Parameter(name="ministere_id",in="query", @OA\Schema(type="integer"), description="Super admin uniquement"),
 *     @OA\Response(response=200, description="Liste des pages", @OA\JsonContent(ref="#/components/schemas/Paginated"))
 * )
 *
 * @OA\Post(path="/ministry/pages", tags={"Pages"}, summary="Créer une page",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(required=true, @OA\JsonContent(
 *         required={"titre"},
 *         @OA\Property(property="titre",       type="string",  example="À propos"),
 *         @OA\Property(property="contenu",     type="string",  nullable=true),
 *         @OA\Property(property="dans_menu",   type="boolean", example=true),
 *         @OA\Property(property="ordre_menu",  type="integer", example=1),
 *         @OA\Property(property="statut",      type="string",  enum={"publie","brouillon"}, example="brouillon"),
 *         @OA\Property(property="meta_titre",  type="string",  nullable=true),
 *         @OA\Property(property="ministere_id",type="integer", example=1, description="Super admin uniquement")
 *     )),
 *     @OA\Response(response=201, description="Page créée", @OA\JsonContent(
 *         @OA\Property(property="success", type="boolean", example=true),
 *         @OA\Property(property="data",    ref="#/components/schemas/Page")
 *     ))
 * )
 *
 * @OA\Put(path="/ministry/pages/{id}", tags={"Pages"}, summary="Modifier une page",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(@OA\JsonContent(
 *         @OA\Property(property="titre",   type="string"),
 *         @OA\Property(property="contenu", type="string"),
 *         @OA\Property(property="statut",  type="string", enum={"publie","brouillon"})
 *     )),
 *     @OA\Response(response=200, description="Page modifiée")
 * )
 *
 * @OA\Delete(path="/ministry/pages/{id}", tags={"Pages"}, summary="Supprimer une page",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Page supprimée", @OA\JsonContent(ref="#/components/schemas/Success"))
 * )
 *
 * @OA\Patch(path="/ministry/pages/{id}/publish", tags={"Pages"}, summary="Publier une page",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Page publiée")
 * )
 *
 * @OA\Patch(path="/ministry/pages/{id}/unpublish", tags={"Pages"}, summary="Dépublier une page",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Page dépubliée")
 * )
 *
 * @OA\Post(path="/ministry/pages/reorder", tags={"Pages"}, summary="Réordonner les pages du menu",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(required=true, @OA\JsonContent(
 *         @OA\Property(property="pages", type="array", @OA\Items(
 *             @OA\Property(property="id",    type="integer", example=1),
 *             @OA\Property(property="ordre", type="integer", example=0)
 *         ))
 *     )),
 *     @OA\Response(response=200, description="Ordre mis à jour")
 * )
 *
 * @OA\Get(path="/public/pages", tags={"Public"}, summary="Pages publiques d'un ministère",
 *     @OA\Parameter(name="subdomain", in="query", @OA\Schema(type="string", example="crc")),
 *     @OA\Response(response=200, description="Pages du menu public")
 * )
 *
 * @OA\Get(path="/public/pages/{slug}", tags={"Public"}, summary="Détail d'une page publique",
 *     @OA\Parameter(name="slug",      in="path",  required=true, @OA\Schema(type="string")),
 *     @OA\Parameter(name="subdomain", in="query", @OA\Schema(type="string", example="crc")),
 *     @OA\Response(response=200, description="Page trouvée"),
 *     @OA\Response(response=404, description="Page non trouvée")
 * )
 *
 * =====================================================
 * ARTICLES
 * =====================================================
 *
 * @OA\Get(path="/ministry/articles", tags={"Articles"}, summary="Liste les articles",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="statut",       in="query", @OA\Schema(type="string", enum={"publie","brouillon"})),
 *     @OA\Parameter(name="type_contenu", in="query", @OA\Schema(type="string", enum={"texte","lien_externe","video_youtube","audio","mixte"})),
 *     @OA\Parameter(name="en_avant",     in="query", @OA\Schema(type="boolean")),
 *     @OA\Parameter(name="ministere_id", in="query", @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Liste paginée", @OA\JsonContent(ref="#/components/schemas/Paginated"))
 * )
 *
 * @OA\Post(path="/ministry/articles", tags={"Articles"}, summary="Créer un article",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(required=true, @OA\JsonContent(
 *         required={"titre","type_contenu"},
 *         @OA\Property(property="titre",          type="string",  example="Prédication du dimanche"),
 *         @OA\Property(property="type_contenu",   type="string",  enum={"texte","lien_externe","video_youtube","audio","mixte"}),
 *         @OA\Property(property="resume",         type="string",  nullable=true),
 *         @OA\Property(property="contenu",        type="string",  nullable=true),
 *         @OA\Property(property="youtube_id",     type="string",  nullable=true, example="dQw4w9WgXcQ"),
 *         @OA\Property(property="url_externe",    type="string",  nullable=true),
 *         @OA\Property(property="categorie",      type="string",  nullable=true, example="Enseignements"),
 *         @OA\Property(property="duree",          type="string",  nullable=true, example="45 min"),
 *         @OA\Property(property="en_avant",       type="boolean", example=false),
 *         @OA\Property(property="statut",         type="string",  enum={"publie","brouillon"}, example="brouillon"),
 *         @OA\Property(property="ministere_id",   type="integer", example=1)
 *     )),
 *     @OA\Response(response=201, description="Article créé", @OA\JsonContent(
 *         @OA\Property(property="success", type="boolean", example=true),
 *         @OA\Property(property="data",    ref="#/components/schemas/Article")
 *     ))
 * )
 *
 * @OA\Put(path="/ministry/articles/{id}", tags={"Articles"}, summary="Modifier un article",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(@OA\JsonContent(
 *         @OA\Property(property="titre",        type="string"),
 *         @OA\Property(property="type_contenu", type="string"),
 *         @OA\Property(property="statut",       type="string", enum={"publie","brouillon"})
 *     )),
 *     @OA\Response(response=200, description="Article modifié")
 * )
 *
 * @OA\Delete(path="/ministry/articles/{id}", tags={"Articles"}, summary="Supprimer un article",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Article supprimé")
 * )
 *
 * @OA\Patch(path="/ministry/articles/{id}/publish", tags={"Articles"}, summary="Publier un article",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Article publié")
 * )
 *
 * @OA\Patch(path="/ministry/articles/{id}/feature", tags={"Articles"}, summary="Mettre en avant / retirer",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Statut mis en avant modifié")
 * )
 *
 * @OA\Get(path="/public/articles", tags={"Public"}, summary="Articles publics d'un ministère",
 *     @OA\Parameter(name="subdomain",    in="query", @OA\Schema(type="string", example="crc")),
 *     @OA\Parameter(name="type_contenu", in="query", @OA\Schema(type="string")),
 *     @OA\Parameter(name="en_avant",     in="query", @OA\Schema(type="boolean")),
 *     @OA\Response(response=200, description="Articles paginés")
 * )
 *
 * @OA\Get(path="/public/articles/{slug}", tags={"Public"}, summary="Détail article public (incrémente les vues)",
 *     @OA\Parameter(name="slug",      in="path",  required=true, @OA\Schema(type="string")),
 *     @OA\Parameter(name="subdomain", in="query", @OA\Schema(type="string", example="crc")),
 *     @OA\Response(response=200, description="Article trouvé")
 * )
 *
 * =====================================================
 * ÉVÉNEMENTS
 * =====================================================
 *
 * @OA\Get(path="/ministry/events", tags={"Événements"}, summary="Liste les événements",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="statut",       in="query", @OA\Schema(type="string", enum={"a_venir","en_cours","termine","annule"})),
 *     @OA\Parameter(name="categorie",    in="query", @OA\Schema(type="string", enum={"ponctuel","recurrent","permanent","saison"})),
 *     @OA\Parameter(name="ministere_id", in="query", @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Liste paginée")
 * )
 *
 * @OA\Post(path="/ministry/events", tags={"Événements"}, summary="Créer un événement",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(required=true, @OA\JsonContent(
 *         required={"titre","categorie"},
 *         @OA\Property(property="titre",               type="string",  example="Culte du dimanche"),
 *         @OA\Property(property="description",         type="string",  nullable=true),
 *         @OA\Property(property="categorie",           type="string",  enum={"ponctuel","recurrent","permanent","saison"}),
 *         @OA\Property(property="frequence",           type="string",  enum={"aucune","quotidien","hebdomadaire","bimensuel","mensuel","annuel"}, example="hebdomadaire"),
 *         @OA\Property(property="jours_semaine",       type="array",   nullable=true, @OA\Items(type="string"), example={"dimanche"}),
 *         @OA\Property(property="heure_debut",         type="string",  example="09:00"),
 *         @OA\Property(property="heure_fin",           type="string",  example="12:00"),
 *         @OA\Property(property="lieu",                type="string",  example="Temple Central CRC"),
 *         @OA\Property(property="mode",                type="string",  enum={"presentiel","en_ligne","hybride"}, example="hybride"),
 *         @OA\Property(property="lien_streaming",      type="string",  nullable=true, example="https://youtube.com/live/crc"),
 *         @OA\Property(property="est_gratuit",         type="boolean", example=true),
 *         @OA\Property(property="inscription_requise", type="boolean", example=false),
 *         @OA\Property(property="ministere_id",        type="integer", example=1)
 *     )),
 *     @OA\Response(response=201, description="Événement créé", @OA\JsonContent(
 *         @OA\Property(property="success", type="boolean", example=true),
 *         @OA\Property(property="data",    ref="#/components/schemas/Evenement")
 *     ))
 * )
 *
 * @OA\Put(path="/ministry/events/{id}", tags={"Événements"}, summary="Modifier un événement",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Événement modifié")
 * )
 *
 * @OA\Delete(path="/ministry/events/{id}", tags={"Événements"}, summary="Supprimer un événement",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Événement supprimé")
 * )
 *
 * @OA\Patch(path="/ministry/events/{id}/cancel", tags={"Événements"}, summary="Annuler un événement",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Événement annulé")
 * )
 *
 * @OA\Get(path="/public/events", tags={"Public"}, summary="Événements publics",
 *     @OA\Parameter(name="subdomain", in="query", @OA\Schema(type="string", example="crc")),
 *     @OA\Response(response=200, description="Événements paginés")
 * )
 *
 * =====================================================
 * MÉDIAS
 * =====================================================
 *
 * @OA\Get(path="/ministry/media", tags={"Médias"}, summary="Liste les médias",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="type",         in="query", @OA\Schema(type="string", enum={"image","video","audio","document"})),
 *     @OA\Parameter(name="ministere_id", in="query", @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Médias paginés")
 * )
 *
 * @OA\Post(path="/ministry/media/upload", tags={"Médias"}, summary="Uploader un fichier",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(required=true,
 *         @OA\MediaType(mediaType="multipart/form-data",
 *             @OA\Schema(
 *                 required={"fichier"},
 *                 @OA\Property(property="fichier",      type="string", format="binary", description="Fichier à uploader (max 10MB)"),
 *                 @OA\Property(property="categorie",    type="string", nullable=true),
 *                 @OA\Property(property="alt_text",     type="string", nullable=true),
 *                 @OA\Property(property="ministere_id", type="integer", example=1)
 *             )
 *         )
 *     ),
 *     @OA\Response(response=201, description="Fichier uploadé", @OA\JsonContent(
 *         @OA\Property(property="success", type="boolean", example=true),
 *         @OA\Property(property="data",    ref="#/components/schemas/Media")
 *     ))
 * )
 *
 * @OA\Delete(path="/ministry/media/{id}", tags={"Médias"}, summary="Supprimer un média",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Média supprimé")
 * )
 *
 * @OA\Post(path="/ministry/media/bulk-delete", tags={"Médias"}, summary="Suppression multiple",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(required=true, @OA\JsonContent(
 *         @OA\Property(property="ids", type="array", @OA\Items(type="integer"), example={1,2,3})
 *     )),
 *     @OA\Response(response=200, description="Médias supprimés")
 * )
 *
 * =====================================================
 * MESSAGES CONTACT
 * =====================================================
 *
 * @OA\Post(path="/public/contact", tags={"Public"}, summary="Envoyer un message de contact",
 *     @OA\RequestBody(required=true, @OA\JsonContent(
 *         required={"subdomain","nom_expediteur","email","message"},
 *         @OA\Property(property="subdomain",       type="string", example="crc"),
 *         @OA\Property(property="nom_expediteur",  type="string", example="Jean Dupont"),
 *         @OA\Property(property="email",           type="string", format="email", example="jean@example.com"),
 *         @OA\Property(property="telephone",       type="string", nullable=true),
 *         @OA\Property(property="sujet",           type="string", nullable=true, example="Demande d'information"),
 *         @OA\Property(property="message",         type="string", example="Bonjour, je voudrais...")
 *     )),
 *     @OA\Response(response=201, description="Message envoyé", @OA\JsonContent(ref="#/components/schemas/Success")),
 *     @OA\Response(response=404, description="Ministère non trouvé")
 * )
 *
 * @OA\Get(path="/ministry/contact-messages", tags={"Messages"}, summary="Liste les messages reçus",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="statut",       in="query", @OA\Schema(type="string", enum={"non_lu","lu","repondu"})),
 *     @OA\Parameter(name="ministere_id", in="query", @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Messages paginés")
 * )
 *
 * @OA\Patch(path="/ministry/contact-messages/{id}/read", tags={"Messages"}, summary="Marquer comme lu",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Marqué comme lu")
 * )
 *
 * @OA\Post(path="/ministry/contact-messages/{id}/reply", tags={"Messages"}, summary="Répondre à un message",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(required=true, @OA\JsonContent(
 *         required={"reponse"},
 *         @OA\Property(property="reponse", type="string", example="Bonjour Jean, merci pour votre message...")
 *     )),
 *     @OA\Response(response=200, description="Réponse enregistrée")
 * )
 *
 * =====================================================
 * DASHBOARD & STATS
 * =====================================================
 *
 * @OA\Get(path="/admin/dashboard", tags={"Dashboard"}, summary="Dashboard super admin",
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(response=200, description="Stats globales de la plateforme")
 * )
 *
 * @OA\Get(path="/ministry/dashboard", tags={"Dashboard"}, summary="Dashboard ministère",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="ministere_id", in="query", @OA\Schema(type="integer"), description="Super admin uniquement"),
 *     @OA\Response(response=200, description="Stats du ministère")
 * )
 *
 * @OA\Get(path="/ministry/stats/content", tags={"Dashboard"}, summary="Stats contenus détaillées",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="ministere_id", in="query", @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Stats par catégorie, type, vues")
 * )
 *
 * @OA\Get(path="/ministry/stats/engagement", tags={"Dashboard"}, summary="Stats engagement",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="ministere_id", in="query", @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Messages, vues, top articles")
 * )
 *
 * =====================================================
 * PARAMÈTRES
 * =====================================================
 *
 * @OA\Get(path="/ministry/settings", tags={"Paramètres"}, summary="Tous les paramètres du ministère",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="ministere_id", in="query", @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Paramètres général, thème, SEO, réseaux sociaux")
 * )
 *
 * @OA\Put(path="/ministry/settings/theme", tags={"Paramètres"}, summary="Mettre à jour le thème",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(@OA\MediaType(mediaType="multipart/form-data",
 *         @OA\Schema(
 *             @OA\Property(property="couleur_primaire",   type="string", example="#1E3A8A"),
 *             @OA\Property(property="couleur_secondaire", type="string", example="#FFFFFF"),
 *             @OA\Property(property="couleur_menu",       type="string", example="#1E3A8A"),
 *             @OA\Property(property="police_titre",       type="string", example="Poppins"),
 *             @OA\Property(property="style_boutons",      type="string", enum={"rounded","square","pill"}),
 *             @OA\Property(property="logo",               type="string", format="binary"),
 *             @OA\Property(property="ministere_id",       type="integer", example=1)
 *         )
 *     )),
 *     @OA\Response(response=200, description="Thème mis à jour")
 * )
 *
 * @OA\Put(path="/ministry/settings/seo", tags={"Paramètres"}, summary="Mettre à jour le SEO",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(@OA\JsonContent(
 *         @OA\Property(property="meta_titre",       type="string", example="CRC Bangui"),
 *         @OA\Property(property="meta_description", type="string", example="Ministère chrétien..."),
 *         @OA\Property(property="meta_keywords",    type="array",  @OA\Items(type="string")),
 *         @OA\Property(property="google_analytics", type="string", nullable=true),
 *         @OA\Property(property="indexable",        type="boolean", example=true),
 *         @OA\Property(property="ministere_id",     type="integer", example=1)
 *     )),
 *     @OA\Response(response=200, description="SEO mis à jour")
 * )
 *
 * @OA\Put(path="/ministry/settings/social", tags={"Paramètres"}, summary="Mettre à jour les réseaux sociaux",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(@OA\JsonContent(
 *         @OA\Property(property="facebook",     type="string", nullable=true),
 *         @OA\Property(property="youtube",      type="string", nullable=true),
 *         @OA\Property(property="whatsapp",     type="string", nullable=true),
 *         @OA\Property(property="instagram",    type="string", nullable=true),
 *         @OA\Property(property="ministere_id", type="integer", example=1)
 *     )),
 *     @OA\Response(response=200, description="Réseaux sociaux mis à jour")
 * )
 *
 * =====================================================
 * UTILISATEURS
 * =====================================================
 *
 * @OA\Get(path="/admin/users", tags={"Utilisateurs"}, summary="Liste tous les utilisateurs (Super Admin)",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="role",         in="query", @OA\Schema(type="string", enum={"super_admin","admin_ministere"})),
 *     @OA\Parameter(name="ministere_id", in="query", @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Utilisateurs paginés")
 * )
 *
 * @OA\Post(path="/admin/users", tags={"Utilisateurs"}, summary="Créer un utilisateur (Super Admin)",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(required=true, @OA\JsonContent(
 *         required={"name","email","password","role"},
 *         @OA\Property(property="name",          type="string",  example="Mologbama"),
 *         @OA\Property(property="prenom",        type="string",  example="Abishadai"),
 *         @OA\Property(property="email",         type="string",  format="email", example="admin@crc.org"),
 *         @OA\Property(property="password",      type="string",  example="password123"),
 *         @OA\Property(property="role",          type="string",  enum={"super_admin","admin_ministere"}),
 *         @OA\Property(property="ministere_id",  type="integer", nullable=true, example=1)
 *     )),
 *     @OA\Response(response=201, description="Utilisateur créé", @OA\JsonContent(
 *         @OA\Property(property="success", type="boolean", example=true),
 *         @OA\Property(property="data",    ref="#/components/schemas/User")
 *     ))
 * )
 *
 * @OA\Post(path="/admin/users/{id}/impersonate", tags={"Utilisateurs"}, summary="Se connecter en tant qu'un utilisateur",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Token de l'utilisateur impersonné retourné")
 * )
 *
 * =====================================================
 * FAQ
 * =====================================================
 *
 * @OA\Get(path="/ministry/faq", tags={"FAQ"}, summary="Liste les FAQs",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="ministere_id", in="query", @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="FAQs listées")
 * )
 *
 * @OA\Post(path="/ministry/faq", tags={"FAQ"}, summary="Créer une FAQ",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(required=true, @OA\JsonContent(
 *         required={"question","reponse"},
 *         @OA\Property(property="question",     type="string",  example="Où vous situez-vous ?"),
 *         @OA\Property(property="reponse",      type="string",  example="Fatima Sandoumbé, Bangui."),
 *         @OA\Property(property="categorie",    type="string",  nullable=true, example="Localisation"),
 *         @OA\Property(property="ordre",        type="integer", example=1),
 *         @OA\Property(property="ministere_id", type="integer", example=1)
 *     )),
 *     @OA\Response(response=201, description="FAQ créée")
 * )
 *
 * @OA\Patch(path="/ministry/faq/{id}/toggle", tags={"FAQ"}, summary="Activer/désactiver une FAQ",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Statut modifié")
 * )
 *
 * @OA\Get(path="/public/faq", tags={"Public"}, summary="FAQs publiques d'un ministère",
 *     @OA\Parameter(name="subdomain", in="query", @OA\Schema(type="string", example="crc")),
 *     @OA\Response(response=200, description="FAQs actives")
 * )
 *
 * =====================================================
 * NOTIFICATIONS
 * =====================================================
 *
 * @OA\Get(path="/notifications", tags={"Notifications"}, summary="Liste mes notifications",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="lu", in="query", @OA\Schema(type="string", enum={"true","false"})),
 *     @OA\Response(response=200, description="Notifications paginées")
 * )
 *
 * @OA\Get(path="/notifications/unread-count", tags={"Notifications"}, summary="Nombre de notifications non lues",
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(response=200, description="Compteur retourné",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="data",    type="object",
 *                 @OA\Property(property="count", type="integer", example=3)
 *             )
 *         )
 *     )
 * )
 *
 * @OA\Post(path="/notifications/mark-all-read", tags={"Notifications"}, summary="Tout marquer comme lu",
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(response=200, description="Toutes les notifications marquées lues")
 * )
 *
 * =====================================================
 * LOGS
 * =====================================================
 *
 * @OA\Get(path="/admin/logs", tags={"Logs"}, summary="Liste les logs d'activité (Super Admin)",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="module",       in="query", @OA\Schema(type="string")),
 *     @OA\Parameter(name="user_id",      in="query", @OA\Schema(type="integer")),
 *     @OA\Parameter(name="ministere_id", in="query", @OA\Schema(type="integer")),
 *     @OA\Parameter(name="date_debut",   in="query", @OA\Schema(type="string", format="date")),
 *     @OA\Parameter(name="date_fin",     in="query", @OA\Schema(type="string", format="date")),
 *     @OA\Response(response=200, description="Logs paginés")
 * )
 *
 * @OA\Get(path="/admin/logs/export", tags={"Logs"}, summary="Exporter les logs en CSV",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="date_debut", in="query", @OA\Schema(type="string", format="date")),
 *     @OA\Parameter(name="date_fin",   in="query", @OA\Schema(type="string", format="date")),
 *     @OA\Response(response=200, description="Fichier CSV téléchargé",
 *         @OA\MediaType(mediaType="text/csv")
 *     )
 * )
 *
 * @OA\Post(path="/admin/logs/clean", tags={"Logs"}, summary="Nettoyer les vieux logs",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(required=true, @OA\JsonContent(
 *         required={"jours"},
 *         @OA\Property(property="jours", type="integer", example=30, description="Supprimer les logs plus vieux que N jours")
 *     )),
 *     @OA\Response(response=200, description="Logs supprimés")
 * )
 */
class SwaggerAnnotations {}