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

    private function getMinistereId(Request $request): int
    {
        if ($request->user()->isSuperAdmin() && $request->has('ministere_id')) {
            return (int) $request->ministere_id;
        }
        return $request->user()->ministere_id;
    }

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

    private function log(Request $request, string $action, string $details): void
    {
        LogAction::create([
            'user_id'      => $request->user()->id,
            'ministere_id' => $request->user()->ministere_id,
            'action'       => $action,
            'module'       => 'settings',
            'details'      => $details,
            'ip'           => $request->ip(),
            'date_action'  => now(),
        ]);
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
}
