<?php

namespace App\Http\Controllers\Api;

/**
 * @OA\Info(
 *     title="EgliseHub API",
 *     version="1.0.0",
 *     description="Plateforme web multi-ministères chrétiens — République centrafricaine",
 *     @OA\Contact(email="admin@eglisehub.org", name="EgliseHub Support")
 * )
 *
 * @OA\Server(
 *     url="http://127.0.0.1:8000/api",
 *     description="Local développement"
 * )
 * @OA\Server(
 *     url="https://api.eglisehub.org/api",
 *     description="Production"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="Sanctum",
 *     description="Obtenez votre token via POST /login puis entrez-le ici"
 * )
 *
 * @OA\Tag(name="Auth",          description="Authentification")
 * @OA\Tag(name="Public",        description="Routes publiques sans auth")
 * @OA\Tag(name="Public - Commentaires", description="Commentaires sur les articles")
 * @OA\Tag(name="Public - Articles", description="Articles publics")
 * @OA\Tag(name="Ministères",    description="Gestion des ministères (Super Admin)")
 * @OA\Tag(name="Utilisateurs",  description="Gestion des utilisateurs")
 * @OA\Tag(name="Pages",         description="Gestion des pages")
 * @OA\Tag(name="Articles",      description="Articles et prédications")
 * @OA\Tag(name="Événements",    description="Événements et cultes")
 * @OA\Tag(name="Médias",        description="Upload et médias")
 * @OA\Tag(name="Messages",      description="Messages de contact")
 * @OA\Tag(name="Dashboard",     description="Statistiques")
 * @OA\Tag(name="Paramètres",    description="Thème, SEO, réseaux sociaux")
 * @OA\Tag(name="FAQ",           description="Questions fréquentes")
 * @OA\Tag(name="Sliders",       description="Slides bannière")
 * @OA\Tag(name="Tags",          description="Tags")
 * @OA\Tag(name="Logs",          description="Logs activité")
 * @OA\Tag(name="Notifications", description="Notifications")
 * @OA\Tag(name="Admin - Commentaires", description="Modération des commentaires")
 * @OA\Tag(name="Worship Schedules", description="Gestion des horaires de cultes")
 *
 * @OA\Schema(schema="Success",
 *     @OA\Property(property="success", type="boolean", example=true),
 *     @OA\Property(property="message", type="string",  example="Opération réussie.")
 * )
 * 
 * @OA\Schema(schema="Paginated",
 *     @OA\Property(property="success", type="boolean", example=true),
 *     @OA\Property(property="data", type="object",
 *         @OA\Property(property="current_page", type="integer", example=1),
 *         @OA\Property(property="per_page",     type="integer", example=15),
 *         @OA\Property(property="total",        type="integer", example=42),
 *         @OA\Property(property="data",         type="array", @OA\Items(type="object"))
 *     )
 * )
 * 
 * @OA\Schema(schema="Ministere",
 *     @OA\Property(property="id",                 type="integer", example=1),
 *     @OA\Property(property="nom",                type="string",  example="Centre Révélation du Christ"),
 *     @OA\Property(property="type",               type="string",  enum={"eglise","ministere","organisation","para_ecclesial","mission"}),
 *     @OA\Property(property="slug",               type="string",  example="centre-revelation-du-christ"),
 *     @OA\Property(property="sous_domaine",       type="string",  example="crc"),
 *     @OA\Property(property="couleur_primaire",   type="string",  example="#1E3A8A"),
 *     @OA\Property(property="couleur_secondaire", type="string",  example="#FFFFFF"),
 *     @OA\Property(property="statut",             type="string",  enum={"actif","inactif","suspendu"}),
 *     @OA\Property(property="pages_count",        type="integer", example=3),
 *     @OA\Property(property="articles_count",     type="integer", example=5),
 *     @OA\Property(property="evenements_count",   type="integer", example=2)
 * )
 * 
 * @OA\Schema(
 *     schema="WorshipSchedule",
 *     required={"id", "ministere_id", "jour", "heure_debut", "heure_fin"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="ministere_id", type="integer", example=1),
 *     @OA\Property(property="jour", type="string", enum={"monday","tuesday","wednesday","thursday","friday","saturday","sunday"}, example="sunday"),
 *     @OA\Property(property="heure_debut", type="string", format="time", example="09:00"),
 *     @OA\Property(property="heure_fin", type="string", format="time", example="12:00"),
 *     @OA\Property(property="is_highlight", type="boolean", example=false),
 *     @OA\Property(property="note", type="string", nullable=true, example="Culte de louange"),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="ordre", type="integer", example=0),
 *     @OA\Property(property="created_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="updated_at", type="string", format="date-time", nullable=true)
 * )
 * 
 * @OA\Schema(schema="Article",
 *     @OA\Property(property="id",           type="integer", example=1),
 *     @OA\Property(property="titre",        type="string",  example="Message du pasteur"),
 *     @OA\Property(property="slug",         type="string",  example="message-du-pasteur"),
 *     @OA\Property(property="type_contenu", type="string",  enum={"texte","lien_externe","video_youtube","audio","mixte"}),
 *     @OA\Property(property="youtube_id",   type="string",  nullable=true, example="dQw4w9WgXcQ"),
 *     @OA\Property(property="url_externe",  type="string",  nullable=true),
 *     @OA\Property(property="statut",       type="string",  enum={"publie","brouillon"}),
 *     @OA\Property(property="vues",         type="integer", example=0),
 *     @OA\Property(property="en_avant",     type="boolean", example=false),
 *     @OA\Property(property="average_rating", type="number", format="float", example=4.5),
 *     @OA\Property(property="rating_count",   type="integer", example=12)
 * )
 * 
 * @OA\Schema(schema="Evenement",
 *     @OA\Property(property="id",           type="integer", example=1),
 *     @OA\Property(property="titre",        type="string",  example="Culte du dimanche"),
 *     @OA\Property(property="categorie",    type="string",  enum={"ponctuel","recurrent","permanent","saison"}),
 *     @OA\Property(property="frequence",    type="string",  enum={"aucune","quotidien","hebdomadaire","bimensuel","mensuel","annuel"}),
 *     @OA\Property(property="heure_debut",  type="string",  example="09:00"),
 *     @OA\Property(property="mode",         type="string",  enum={"presentiel","en_ligne","hybride"}),
 *     @OA\Property(property="est_gratuit",  type="boolean", example=true)
 * )
 * 

 * 
 * @OA\Schema(schema="Page",
 *     @OA\Property(property="id",          type="integer", example=1),
 *     @OA\Property(property="titre",       type="string",  example="Accueil"),
 *     @OA\Property(property="slug",        type="string",  example="accueil"),
 *     @OA\Property(property="statut",      type="string",  enum={"publie","brouillon"}),
 *     @OA\Property(property="dans_menu",   type="boolean", example=true),
 *     @OA\Property(property="ordre_menu",  type="integer", example=0)
 * )
 * 
 * @OA\Schema(schema="Media",
 *     @OA\Property(property="id",           type="integer", example=1),
 *     @OA\Property(property="nom_original", type="string",  example="photo.jpg"),
 *     @OA\Property(property="url",          type="string",  example="/storage/ministeres/1/images/uuid.jpg"),
 *     @OA\Property(property="type",         type="string",  enum={"image","video","audio","document"}),
 *     @OA\Property(property="taille",       type="integer", example=204800)
 * )
 * 
 * @OA\Schema(schema="ArticleCommentaire",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="article_id", type="integer", example=8),
 *     @OA\Property(property="parent_id", type="integer", nullable=true),
 *     @OA\Property(property="nom_auteur", type="string", example="Jean"),
 *     @OA\Property(property="email", type="string", format="email", example="jean@example.com"),
 *     @OA\Property(property="contenu", type="string", example="Très bon article !"),
 *     @OA\Property(property="statut", type="string", enum={"en_attente","approuve","rejete"}, example="en_attente"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="reponses", type="array", @OA\Items(ref="#/components/schemas/ArticleCommentaire"))
 * )
 * 
 * @OA\Schema(schema="ArticleNote",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="article_id", type="integer", example=8),
 *     @OA\Property(property="note", type="integer", minimum=1, maximum=5, example=5),
 *     @OA\Property(property="ip", type="string", example="127.0.0.1"),
 *     @OA\Property(property="created_at", type="string", format="date-time")
 * )
 * 
 * @OA\Schema(schema="Tag",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="ministere_id", type="integer", example=1),
 *     @OA\Property(property="nom", type="string", example="Enseignements"),
 *     @OA\Property(property="slug", type="string", example="enseignements"),
 *     @OA\Property(property="couleur", type="string", example="#1E3A8A"),
 *     @OA\Property(property="articles_count", type="integer", example=5),
 *     @OA\Property(property="pages_count", type="integer", example=2)
 * )
 * 
 * @OA\Schema(schema="User",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Mologbama"),
 *     @OA\Property(property="prenom", type="string", nullable=true, example="Abishadai"),
 *     @OA\Property(property="email", type="string", format="email", example="admin@eglisehub.org"),
 *     @OA\Property(property="role", type="string", enum={"super_admin","admin_ministere","createur_contenu","moderateur"}, example="super_admin"),
 *     @OA\Property(property="ministere_id", type="integer", nullable=true, example=1),
 *     @OA\Property(property="actif", type="boolean", example=true),
 *     @OA\Property(property="ministere", type="object", nullable=true)
 * )
 * 
 * @OA\Schema(schema="LoginRequest",
 *     @OA\Property(property="email", type="string", format="email", example="admin@eglisehub.org"),
 *     @OA\Property(property="password", type="string", format="password", example="Password123")
 * )
 * 
 * @OA\Schema(schema="LoginResponse",
 *     @OA\Property(property="success", type="boolean", example=true),
 *     @OA\Property(property="token", type="string", description="Token d'authentification Sanctum"),
 *     @OA\Property(property="user", ref="#/components/schemas/User")
 * )
 * 
 * @OA\Schema(schema="RegisterRequest",
 *     @OA\Property(property="name", type="string", example="Admin"),
 *     @OA\Property(property="prenom", type="string", nullable=true, example="Jean"),
 *     @OA\Property(property="email", type="string", format="email", example="admin@crc.org"),
 *     @OA\Property(property="password", type="string", format="password", example="Password123"),
 *     @OA\Property(property="password_confirmation", type="string", example="Password123"),
 *     @OA\Property(property="ministere_nom", type="string", example="CRC Bangui"),
 *     @OA\Property(property="ministere_type", type="string", enum={"eglise","ministere","organisation","para_ecclesial","mission"}, example="eglise"),
 *     @OA\Property(property="sous_domaine", type="string", example="crc"),
 *     @OA\Property(property="ville", type="string", nullable=true, example="Bangui"),
 *     @OA\Property(property="pays", type="string", nullable=true, example="République centrafricaine"),
 *     @OA\Property(property="email_contact", type="string", format="email", nullable=true, example="contact@crc.org")
 * )
 * 
 * @OA\Schema(schema="ChangePasswordRequest",
 *     @OA\Property(property="ancien_mot_de_passe", type="string", format="password", example="OldPassword123"),
 *     @OA\Property(property="nouveau_mot_de_passe", type="string", format="password", example="NewPassword123"),
 *     @OA\Property(property="nouveau_mot_de_passe_confirmation", type="string", example="NewPassword123")
 * )
 * 
 * @OA\Schema(schema="Faq",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="question", type="string", example="Quelle est la horário des cultes?"),
 *     @OA\Property(property="reponse", type="string", example="Les cultes sont à 9h le dimanche."),
 *     @OA\Property(property="categorie", type="string", nullable=true),
 *     @OA\Property(property="actif", type="boolean", example=true),
 *     @OA\Property(property="ordre", type="integer", example=0)
 * )
 * 
 * @OA\Schema(schema="Slider",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="titre", type="string", example="Bienvenue"),
 *     @OA\Property(property="sous_titre", type="string", nullable=true),
 *     @OA\Property(property="type_media", type="string", enum={"image","video"}, example="image"),
 *     @OA\Property(property="image", type="string", nullable=true),
 *     @OA\Property(property="bouton_texte", type="string", nullable=true),
 *     @OA\Property(property="bouton_lien", type="string", nullable=true),
 *     @OA\Property(property="actif", type="boolean", example=true),
 *     @OA\Property(property="ordre", type="integer", example=0)
 * )
 * 
 * @OA\Schema(schema="Don",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="nom_donateur", type="string", example="Jean Dupont"),
 *     @OA\Property(property="email_donateur", type="string", nullable=true),
 *     @OA\Property(property="telephone", type="string", example="+236 74 02 67 55"),
 *     @OA\Property(property="montant", type="number", example=5000),
 *     @OA\Property(property="type_don", type="string", enum={"don","dime","offrande"}, example="don"),
 *     @OA\Property(property="operateur", type="string", enum={"orange","moov","airtel"}, example="orange"),
 *     @OA\Property(property="statut", type="string", enum={"en_attente","confirme","echoue"}, example="en_attente")
 * )
 */
class SwaggerController {}