<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Ministere;
use App\Models\LogAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    // =========================================================
    // HELPERS PRIVÉS
    // =========================================================

    // Récupérer une valeur de setting
    private function get(int $ministereId, string $cle, mixed $defaut = null): mixed
    {
        $setting = Setting::where('ministere_id', $ministereId)
            ->where('cle', $cle)
            ->first();

        if (! $setting) return $defaut;

        // Tenter de décoder le JSON, sinon retourner la valeur brute
        $decoded = json_decode($setting->valeur, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $setting->valeur;
    }

    // Sauvegarder une valeur de setting
    private function set(int $ministereId, string $cle, mixed $valeur): void
    {
        $valeurStr = is_array($valeur) || is_object($valeur)
            ? json_encode($valeur, JSON_UNESCAPED_UNICODE)
            : (string) $valeur;

        Setting::updateOrCreate(
            ['ministere_id' => $ministereId, 'cle' => $cle],
            ['valeur' => $valeurStr]
        );
    }

    // =========================================================
    // PARAMÈTRES GÉNÉRAUX
    // =========================================================

    // GET /api/ministry/settings
    public function index(Request $request)
    {
        $ministereId = $this->getMinistereId($request);
        $ministere   = Ministere::findOrFail($ministereId);

        $settings = [
            'general' => [
                'nom'           => $ministere->nom,
                'type'          => $ministere->type,
                'description'   => $ministere->description,
                'email_contact' => $ministere->email_contact,
                'telephone'     => $ministere->telephone,
                'adresse'       => $ministere->adresse,
                'ville'         => $ministere->ville,
                'pays'          => $ministere->pays,
            ],
            'theme'   => $this->getThemeData($ministereId, $ministere),
            'seo'     => $this->getSeoData($ministereId, $ministere),
            'social'  => $this->getSocialData($ministereId, $ministere),
            'content' => $this->getContentData($ministereId, $ministere), // AJOUTÉ
        ];

        return response()->json(['success' => true, 'data' => $settings]);
    }

    // PUT /api/ministry/settings
    public function update(Request $request)
    {
        $request->validate([
            'nom'           => 'sometimes|string|max:255',
            'description'   => 'nullable|string',
            'email_contact' => 'nullable|email',
            'telephone'     => 'nullable|string|max:20',
            'adresse'       => 'nullable|string',
            'ville'         => 'nullable|string|max:100',
            'pays'          => 'nullable|string|max:100',
        ]);

        $ministereId = $this->getMinistereId($request);
        $ministere   = Ministere::findOrFail($ministereId);

        $ministere->update($request->only([
            'nom',
            'description',
            'email_contact',
            'telephone',
            'adresse',
            'ville',
            'pays',
        ]));

        $settingsToSave = [
            'slogan',
            'vision',
            'mission',
            'valeur',
            'annee_fondation',
            'qui_sommes_nous',
            'live_youtube_id',
            'live_actif',
            'live_prochain',
            'orange_money_numero',
            'don_actif',
            'fondateur',
            'fondateur_titre',
            'facebook',
            'youtube',
            'whatsapp',
            'instagram',
        ];

        foreach ($settingsToSave as $cle) {
            if ($request->has($cle)) {
                // Gérer les booléens
                $value = $request->$cle;
                if ($cle === 'live_actif' || $cle === 'don_actif') {
                    $value = $request->boolean($cle) ? '1' : '0';
                }

                Setting::updateOrCreate(
                    ['ministere_id' => $ministereId, 'cle' => $cle],
                    ['valeur' => $value]
                );
            }
        }

        $this->log($request, 'update_settings', 'Mise à jour paramètres généraux');

        return response()->json([
            'success' => true,
            'message' => 'Paramètres mis à jour.',
            'data'    => $ministere->fresh(),
        ]);
    }

    // =========================================================
    // THÈME
    // =========================================================

    private function getThemeData(int $ministereId, Ministere $ministere): array
    {
        return [
            'couleur_primaire'   => $ministere->couleur_primaire,
            'couleur_secondaire' => $ministere->couleur_secondaire,
            'logo'               => $ministere->logo,
            'logo_url'           => $ministere->logo ? Storage::url($ministere->logo) : null,
            'favicon'            => $this->get($ministereId, 'favicon'),
            'police_titre'       => $this->get($ministereId, 'police_titre', 'Inter'),
            'police_corps'       => $this->get($ministereId, 'police_corps', 'Inter'),
            'style_boutons'      => $this->get($ministereId, 'style_boutons', 'rounded'),
            'mode_sombre'        => $this->get($ministereId, 'mode_sombre', false),
            'banniere_accueil'   => $this->get($ministereId, 'banniere_accueil'),
            'couleur_menu'       => $this->get($ministereId, 'couleur_menu', '#1E3A8A'),
            'couleur_pied'       => $this->get($ministereId, 'couleur_pied', '#111827'),
        ];
    }

    // GET /api/ministry/settings/theme
    public function getTheme(Request $request)
    {
        $ministereId = $this->getMinistereId($request);
        $ministere   = Ministere::findOrFail($ministereId);

        return response()->json([
            'success' => true,
            'data'    => $this->getThemeData($ministereId, $ministere),
        ]);
    }

    // PUT /api/ministry/settings/theme
    public function updateTheme(Request $request)
    {
        $request->validate([
            'couleur_primaire'   => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'couleur_secondaire' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'couleur_menu'       => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'couleur_pied'       => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'police_titre'       => 'nullable|string|max:100',
            'police_corps'       => 'nullable|string|max:100',
            'style_boutons'      => 'nullable|in:rounded,square,pill',
            'mode_sombre'        => 'boolean',
            'logo'               => 'nullable|file|image|max:2048',
            'favicon'            => 'nullable|file|image|max:512',
            'banniere_accueil'   => 'nullable|file|image|max:5120',
        ]);

        $ministereId = $this->getMinistereId($request);
        $ministere   = Ministere::findOrFail($ministereId);

        // Couleurs principales → directement dans la table ministeres
        $champsMinistere = [];
        if ($request->has('couleur_primaire'))   $champsMinistere['couleur_primaire']   = $request->couleur_primaire;
        if ($request->has('couleur_secondaire')) $champsMinistere['couleur_secondaire'] = $request->couleur_secondaire;

        // Upload logo
        if ($request->hasFile('logo')) {
            if ($ministere->logo) Storage::disk('public')->delete($ministere->logo);
            $champsMinistere['logo'] = $request->file('logo')
                ->storeAs("ministeres/{$ministereId}", 'logo.' . $request->file('logo')->extension(), 'public');
        }

        if (! empty($champsMinistere)) {
            $ministere->update($champsMinistere);
        }

        // Upload favicon
        if ($request->hasFile('favicon')) {
            $chemin = $request->file('favicon')
                ->storeAs("ministeres/{$ministereId}", 'favicon.' . $request->file('favicon')->extension(), 'public');
            $this->set($ministereId, 'favicon', $chemin);
        }

        // Upload bannière
        if ($request->hasFile('banniere_accueil')) {
            $chemin = $request->file('banniere_accueil')
                ->storeAs("ministeres/{$ministereId}", 'banniere.' . $request->file('banniere_accueil')->extension(), 'public');
            $this->set($ministereId, 'banniere_accueil', $chemin);
        }

        // Autres settings thème
        $settingsTheme = ['couleur_menu', 'couleur_pied', 'police_titre', 'police_corps', 'style_boutons', 'mode_sombre'];
        foreach ($settingsTheme as $cle) {
            if ($request->has($cle)) {
                $this->set($ministereId, $cle, $request->$cle);
            }
        }

        $this->log($request, 'update_theme', 'Mise à jour du thème');

        return response()->json([
            'success' => true,
            'message' => 'Thème mis à jour.',
            'data'    => $this->getThemeData($ministereId, $ministere->fresh()),
        ]);
    }

    // =========================================================
    // CONTENU (TEXTES)
    // =========================================================

    private function getContentData(int $ministereId, Ministere $ministere): array
    {
        return [
            'slogan'             => $this->get($ministereId, 'slogan', ''),
            'vision'             => $this->get($ministereId, 'vision', ''),
            'mission'            => $this->get($ministereId, 'mission', ''),
            'valeur'            => $this->get($ministereId, 'valeur', ''),
            'annee_fondation'    => $this->get($ministereId, 'annee_fondation', ''),
            'qui_sommes_nous'    => $this->get($ministereId, 'qui_sommes_nous', $ministere->description),
            'live_youtube_id'    => $this->get($ministereId, 'live_youtube_id', ''),
            'live_actif'         => $this->get($ministereId, 'live_actif', false),
            'live_prochain'      => $this->get($ministereId, 'live_prochain', ''),
            'orange_money_numero' => $this->get($ministereId, 'orange_money_numero', ''),
            'don_actif'          => $this->get($ministereId, 'don_actif', false),
            'fondateur'          => $this->get($ministereId, 'fondateur', ''),
            'fondateur_titre'    => $this->get($ministereId, 'fondateur_titre', ''),
            'fondateur_photo'    => $this->get($ministereId, 'fondateur_photo'),
        ];
    }

    /**
     * @OA\Put(
     *     path="/ministry/settings/content",
     *     tags={"Paramètres"},
     *     summary="Mettre à jour le contenu textuel du site",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         @OA\MediaType(mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="slogan", type="string"),
     *                 @OA\Property(property="vision", type="string"),
     *                 @OA\Property(property="mission", type="string"),
     *                 @OA\Property(property="valeur", type="string"),
     *                 @OA\Property(property="annee_fondation", type="year"),
     *                 @OA\Property(property="qui_sommes_nous", type="string"),
     *                 @OA\Property(property="live_youtube_id", type="string"),
     *                 @OA\Property(property="live_actif", type="boolean"),
     *                 @OA\Property(property="live_prochain", type="string"),
     *                 @OA\Property(property="orange_money_numero", type="string"),
     *                 @OA\Property(property="don_actif", type="boolean"),
     *                 @OA\Property(property="fondateur", type="string"),
     *                 @OA\Property(property="fondateur_titre", type="string"),
     *                 @OA\Property(property="fondateur_photo", type="string", format="binary"),
     *                 @OA\Property(property="ministere_id", type="integer", example=1)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Contenu mis à jour",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Contenu enregistré."),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */


    // PUT /api/ministry/settings/content
    public function updateContent(Request $request)
    {
        $ministereId = $this->getMinistereId($request);

        $fields = [
            'slogan',
            'vision',
            'mission',
            'valeur',
            'annee_fondation',
            'qui_sommes_nous',
            'live_youtube_id',
            'live_actif',
            'live_prochain',
            'orange_money_numero',
            'don_actif',
            'fondateur',
            'fondateur_titre',
            'fondateur_photo',
        ];

        foreach ($fields as $field) {
            if ($request->has($field)) {
                // Gérer les booléens
                if ($field === 'live_actif' || $field === 'don_actif') {
                    $value = $request->boolean($field) ? '1' : '0';
                } else {
                    $value = $request->$field;
                }

                Setting::updateOrCreate(
                    ['ministere_id' => $ministereId, 'cle' => $field],
                    ['valeur' => $value]
                );
            }
        }

        // Gérer l'upload de la photo du fondateur
        if ($request->hasFile('fondateur_photo')) {
            $file = $request->file('fondateur_photo');
            $nomFichier = 'fondateur.' . $file->extension();
            $chemin = $file->storeAs("ministeres/{$ministereId}", $nomFichier, 'public');

            Setting::updateOrCreate(
                ['ministere_id' => $ministereId, 'cle' => 'fondateur_photo'],
                ['valeur' => $chemin]
            );
        }

        $this->log($request, 'update_content', 'Mise à jour du contenu');

        return response()->json([
            'success' => true,
            'message' => 'Contenu enregistré.',
            'data'    => $this->getContentData($ministereId, Ministere::find($ministereId))
        ]);
    }

    // =========================================================
    // SEO
    // =========================================================

    private function getSeoData(int $ministereId, Ministere $ministere): array
    {
        return [
            'meta_titre'       => $this->get($ministereId, 'seo_titre',       $ministere->nom),
            'meta_description' => $this->get($ministereId, 'seo_description', $ministere->description),
            'meta_keywords'    => $this->get($ministereId, 'seo_keywords',    []),
            'og_image'         => $this->get($ministereId, 'seo_og_image'),
            'og_image_url'     => $this->get($ministereId, 'seo_og_image')
                ? Storage::url($this->get($ministereId, 'seo_og_image'))
                : null,
            'google_analytics' => $this->get($ministereId, 'seo_google_analytics'),
            'indexable'        => $this->get($ministereId, 'seo_indexable', true),
            'sitemap_actif'    => $this->get($ministereId, 'seo_sitemap', true),
        ];
    }

    // GET /api/ministry/settings/seo
    public function getSeo(Request $request)
    {
        $ministereId = $this->getMinistereId($request);
        $ministere   = Ministere::findOrFail($ministereId);

        return response()->json([
            'success' => true,
            'data'    => $this->getSeoData($ministereId, $ministere),
        ]);
    }

    // PUT /api/ministry/settings/seo
    public function updateSeo(Request $request)
    {
        $request->validate([
            'meta_titre'       => 'nullable|string|max:70',
            'meta_description' => 'nullable|string|max:160',
            'meta_keywords'    => 'nullable|array',
            'meta_keywords.*'  => 'string|max:50',
            'og_image'         => 'nullable|file|image|max:2048',
            'google_analytics' => 'nullable|string|max:50',
            'indexable'        => 'boolean',
            'sitemap_actif'    => 'boolean',
        ]);

        $ministereId = $this->getMinistereId($request);

        $mappings = [
            'meta_titre'       => 'seo_titre',
            'meta_description' => 'seo_description',
            'meta_keywords'    => 'seo_keywords',
            'google_analytics' => 'seo_google_analytics',
            'indexable'        => 'seo_indexable',
            'sitemap_actif'    => 'seo_sitemap',
        ];

        foreach ($mappings as $champ => $cle) {
            if ($request->has($champ)) {
                $this->set($ministereId, $cle, $request->$champ);
            }
        }

        if ($request->hasFile('og_image')) {
            $chemin = $request->file('og_image')
                ->storeAs("ministeres/{$ministereId}/seo", 'og-image.' . $request->file('og_image')->extension(), 'public');
            $this->set($ministereId, 'seo_og_image', $chemin);
        }

        $this->log($request, 'update_seo', 'Mise à jour SEO');

        return response()->json([
            'success' => true,
            'message' => 'Paramètres SEO mis à jour.',
            'data'    => $this->getSeoData($ministereId, Ministere::findOrFail($ministereId)),
        ]);
    }

    // =========================================================
    // RÉSEAUX SOCIAUX
    // =========================================================

    private function getSocialData(int $ministereId, Ministere $ministere): array
    {
        return [
            'facebook'  => $ministere->facebook_url,
            'youtube'   => $ministere->youtube_url,
            'whatsapp'  => $ministere->whatsapp,
            'instagram' => $this->get($ministereId, 'social_instagram'),
            'twitter'   => $this->get($ministereId, 'social_twitter'),
            'tiktok'    => $this->get($ministereId, 'social_tiktok'),
            'telegram'  => $this->get($ministereId, 'social_telegram'),
        ];
    }

    // GET /api/ministry/settings/social
    public function getSocial(Request $request)
    {
        $ministereId = $this->getMinistereId($request);
        $ministere   = Ministere::findOrFail($ministereId);

        return response()->json([
            'success' => true,
            'data'    => $this->getSocialData($ministereId, $ministere),
        ]);
    }

    // PUT /api/ministry/settings/social
    public function updateSocial(Request $request)
    {
        $request->validate([
            'facebook'  => 'nullable|url',
            'youtube'   => 'nullable|url',
            'whatsapp'  => 'nullable|string|max:20',
            'instagram' => 'nullable|url',
            'twitter'   => 'nullable|url',
            'tiktok'    => 'nullable|url',
            'telegram'  => 'nullable|string|max:100',
        ]);

        $ministereId = $this->getMinistereId($request);
        $ministere   = Ministere::findOrFail($ministereId);

        // Facebook, YouTube, WhatsApp → table ministeres
        $champsMinistere = [];
        if ($request->has('facebook')) $champsMinistere['facebook_url'] = $request->facebook;
        if ($request->has('youtube'))  $champsMinistere['youtube_url']  = $request->youtube;
        if ($request->has('whatsapp')) $champsMinistere['whatsapp']     = $request->whatsapp;
        if (! empty($champsMinistere)) $ministere->update($champsMinistere);

        // Instagram, Twitter, TikTok, Telegram → table settings
        $autresReseaux = ['instagram', 'twitter', 'tiktok', 'telegram'];
        foreach ($autresReseaux as $reseau) {
            if ($request->has($reseau)) {
                $this->set($ministereId, "social_{$reseau}", $request->$reseau);
            }
        }

        $this->log($request, 'update_social', 'Mise à jour réseaux sociaux');

        return response()->json([
            'success' => true,
            'message' => 'Réseaux sociaux mis à jour.',
            'data'    => $this->getSocialData($ministereId, $ministere->fresh()),
        ]);
    }

    // =========================================================
    // ROUTES PUBLIQUES
    // =========================================================

    // GET /api/public/settings
    public function publicSettings(Request $request)
    {
        $subdomain = $request->query('subdomain', 'crc');

        $ministere = Ministere::where('sous_domaine', $subdomain)
            ->where('statut', 'actif')
            ->first();

        if (!$ministere) {
            return response()->json(['success' => false, 'message' => 'Introuvable'], 404);
        }

        $settings = Setting::where('ministere_id', $ministere->id)
            ->get()
            ->keyBy('cle')
            ->map(function ($item) {
                // Convertir les booléens
                if ($item->cle === 'live_actif' || $item->cle === 'don_actif') {
                    return $item->valeur === '1' ? true : false;
                }
                return $item->valeur;
            });

        // Retourner aussi les données du ministère
        return response()->json([
            'success' => true,
            'data' => array_merge([
                'nom' => $ministere->nom,
                'couleur_primaire' => $ministere->couleur_primaire,
                'couleur_secondaire' => $ministere->couleur_secondaire,
                'logo' => $ministere->logo ? Storage::url($ministere->logo) : null,
                'description' => $ministere->description,
                'adresse' => $ministere->adresse,
                'telephone' => $ministere->telephone,
                'email_contact' => $ministere->email_contact,
                'ville' => $ministere->ville,
                'pays' => $ministere->pays,
                'facebook_url' => $ministere->facebook_url,
                'youtube_url' => $ministere->youtube_url,
                'whatsapp' => $ministere->whatsapp,
            ], $settings->toArray())
        ]);
    }

    // Helper pour récupérer tous les settings (pour usage interne)
    private function getSettings(int $ministereId)
    {
        $settings = Setting::where('ministere_id', $ministereId)
            ->get()
            ->keyBy('cle')
            ->map(function ($item) {
                if ($item->cle === 'live_actif' || $item->cle === 'don_actif') {
                    return $item->valeur === '1' ? true : false;
                }
                return $item->valeur;
            });

        return $settings;
    }

    /**
     * @OA\Get(
     *     path="/ministry/settings/content",
     *     tags={"Paramètres"},
     *     summary="Récupérer le contenu textuel du site",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="ministere_id", in="query", @OA\Schema(type="integer"), description="Super admin uniquement"),
     *     @OA\Response(
     *         response=200,
     *         description="Contenu textuel",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="slogan", type="string", example="Pillons l'enfer pour peupler le Royaume"),
     *                 @OA\Property(property="vision", type="string", example="Révéler Christ au monde entier..."),
     *                 @OA\Property(property="mission", type="string", example="Diffuser l'Évangile..."),
     *                 @OA\Property(property="valeur", type="string", example="Aimer Dieu et le prochain..."),
     *                 @OA\Property(property="qui_sommes_nous", type="string"),
     *                 @OA\Property(property="live_youtube_id", type="string", nullable=true),
     *                 @OA\Property(property="live_actif", type="boolean"),
     *                 @OA\Property(property="live_prochain", type="string", nullable=true),
     *                 @OA\Property(property="orange_money_numero", type="string", nullable=true),
     *                 @OA\Property(property="don_actif", type="boolean"),
     *                 @OA\Property(property="fondateur", type="string", nullable=true),
     *                 @OA\Property(property="fondateur_titre", type="string", nullable=true),
     *                 @OA\Property(property="fondateur_photo", type="string", nullable=true)
     *             )
     *         )
     *     )
     * )
     */
    public function getContent(Request $request)
    {
        $ministereId = $this->getMinistereId($request);
        $ministere   = Ministere::findOrFail($ministereId);

        return response()->json([
            'success' => true,
            'data' => $this->getContentData($ministereId, $ministere)
        ]);
    }
}
