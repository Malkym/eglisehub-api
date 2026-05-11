<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Ministere;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class SettingController extends Controller
{
    private function get(int $ministereId, string $cle, mixed $defaut = null): mixed
    {
        $setting = Setting::where('ministere_id', $ministereId)->where('cle', $cle)->first();
        if (!$setting) return $defaut;
        $decoded = json_decode($setting->valeur, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $setting->valeur;
    }

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

    public function index(Request $request)
    {
        $ministereId = $this->getMinistereId($request);
        $ministere   = Ministere::findOrFail($ministereId);

        return $this->respondSuccess([
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
            'content' => $this->getContentData($ministereId, $ministere),
        ]);
    }

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

        $ministere->update($request->only(['nom', 'description', 'email_contact', 'telephone', 'adresse', 'ville', 'pays']));

        $settingsToSave = ['slogan', 'vision', 'mission', 'valeur', 'annee_fondation', 'qui_sommes_nous', 'live_youtube_id', 'live_actif', 'live_prochain', 'orange_money_numero', 'don_actif', 'fondateur', 'fondateur_titre', 'facebook', 'youtube', 'whatsapp', 'instagram'];

        foreach ($settingsToSave as $cle) {
            if ($request->has($cle)) {
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

        return $this->respondSuccess($ministere->fresh(), 'Paramètres mis à jour.');
    }

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

    public function getTheme(Request $request)
    {
        $ministereId = $this->getMinistereId($request);
        $ministere   = Ministere::findOrFail($ministereId);

        return $this->respondSuccess($this->getThemeData($ministereId, $ministere));
    }

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

        $champsMinistere = [];
        if ($request->has('couleur_primaire')) $champsMinistere['couleur_primaire'] = $request->couleur_primaire;
        if ($request->has('couleur_secondaire')) $champsMinistere['couleur_secondaire'] = $request->couleur_secondaire;

        if ($request->hasFile('logo')) {
            if ($ministere->logo) Storage::disk('public')->delete($ministere->logo);
            $champsMinistere['logo'] = $request->file('logo')->storeAs("ministeres/{$ministereId}", 'logo.' . $request->file('logo')->extension(), 'public');
        }

        if (!empty($champsMinistere)) $ministere->update($champsMinistere);

        if ($request->hasFile('favicon')) {
            $chemin = $request->file('favicon')->storeAs("ministeres/{$ministereId}", 'favicon.' . $request->file('favicon')->extension(), 'public');
            $this->set($ministereId, 'favicon', $chemin);
        }

        if ($request->hasFile('banniere_accueil')) {
            $chemin = $request->file('banniere_accueil')->storeAs("ministeres/{$ministereId}", 'banniere.' . $request->file('banniere_accueil')->extension(), 'public');
            $this->set($ministereId, 'banniere_accueil', $chemin);
        }

        foreach (['couleur_menu', 'couleur_pied', 'police_titre', 'police_corps', 'style_boutons', 'mode_sombre'] as $cle) {
            if ($request->has($cle)) $this->set($ministereId, $cle, $request->$cle);
        }

        $this->log($request, 'update_theme', 'Mise à jour du thème');

        return $this->respondSuccess($this->getThemeData($ministereId, $ministere->fresh()), 'Thème mis à jour.');
    }

    private function getContentData(int $ministereId, Ministere $ministere): array
    {
        return [
            'slogan'              => $this->get($ministereId, 'slogan', ''),
            'vision'              => $this->get($ministereId, 'vision', ''),
            'mission'             => $this->get($ministereId, 'mission', ''),
            'valeur'              => $this->get($ministereId, 'valeur', ''),
            'annee_fondation'     => $this->get($ministereId, 'annee_fondation', ''),
            'qui_sommes_nous'     => $this->get($ministereId, 'qui_sommes_nous', $ministere->description),
            'live_youtube_id'     => $this->get($ministereId, 'live_youtube_id', ''),
            'live_actif'          => $this->get($ministereId, 'live_actif', false),
            'live_prochain'       => $this->get($ministereId, 'live_prochain', ''),
            'orange_money_numero' => $this->get($ministereId, 'orange_money_numero', ''),
            'don_actif'           => $this->get($ministereId, 'don_actif', false),
            'fondateur'           => $this->get($ministereId, 'fondateur', ''),
            'fondateur_titre'     => $this->get($ministereId, 'fondateur_titre', ''),
            'fondateur_photo'     => $this->get($ministereId, 'fondateur_photo'),
        ];
    }

    public function updateContent(Request $request)
    {
        $ministereId = $this->getMinistereId($request);

        $fields = ['slogan', 'vision', 'mission', 'valeur', 'annee_fondation', 'qui_sommes_nous', 'live_youtube_id', 'live_actif', 'live_prochain', 'orange_money_numero', 'don_actif', 'fondateur', 'fondateur_titre'];

        foreach ($fields as $field) {
            if ($request->has($field)) {
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

        return $this->respondSuccess($this->getContentData($ministereId, Ministere::find($ministereId)), 'Contenu enregistré.');
    }

    private function getSeoData(int $ministereId, Ministere $ministere): array
    {
        return [
            'meta_titre'       => $this->get($ministereId, 'seo_titre', $ministere->nom),
            'meta_description' => $this->get($ministereId, 'seo_description', $ministere->description),
            'meta_keywords'    => $this->get($ministereId, 'seo_keywords', []),
            'og_image'         => $this->get($ministereId, 'seo_og_image'),
            'og_image_url'     => $this->get($ministereId, 'seo_og_image') ? Storage::url($this->get($ministereId, 'seo_og_image')) : null,
            'google_analytics' => $this->get($ministereId, 'seo_google_analytics'),
            'indexable'        => $this->get($ministereId, 'seo_indexable', true),
            'sitemap_actif'    => $this->get($ministereId, 'seo_sitemap', true),
        ];
    }

    public function getSeo(Request $request)
    {
        $ministereId = $this->getMinistereId($request);
        $ministere   = Ministere::findOrFail($ministereId);

        return $this->respondSuccess($this->getSeoData($ministereId, $ministere));
    }

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
            if ($request->has($champ)) $this->set($ministereId, $cle, $request->$champ);
        }

        if ($request->hasFile('og_image')) {
            $chemin = $request->file('og_image')->storeAs("ministeres/{$ministereId}/seo", 'og-image.' . $request->file('og_image')->extension(), 'public');
            $this->set($ministereId, 'seo_og_image', $chemin);
        }

        $this->log($request, 'update_seo', 'Mise à jour SEO');

        return $this->respondSuccess($this->getSeoData($ministereId, Ministere::findOrFail($ministereId)), 'Paramètres SEO mis à jour.');
    }

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

    public function getSocial(Request $request)
    {
        $ministereId = $this->getMinistereId($request);
        $ministere   = Ministere::findOrFail($ministereId);

        return $this->respondSuccess($this->getSocialData($ministereId, $ministere));
    }

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

        $champsMinistere = [];
        if ($request->has('facebook')) $champsMinistere['facebook_url'] = $request->facebook;
        if ($request->has('youtube')) $champsMinistere['youtube_url'] = $request->youtube;
        if ($request->has('whatsapp')) $champsMinistere['whatsapp'] = $request->whatsapp;
        if (!empty($champsMinistere)) $ministere->update($champsMinistere);

        foreach (['instagram', 'twitter', 'tiktok', 'telegram'] as $reseau) {
            if ($request->has($reseau)) $this->set($ministereId, "social_{$reseau}", $request->$reseau);
        }

        $this->log($request, 'update_social', 'Mise à jour réseaux sociaux');

        return $this->respondSuccess($this->getSocialData($ministereId, $ministere->fresh()), 'Réseaux sociaux mis à jour.');
    }

    public function publicSettings(Request $request)
    {
        $ministere = $this->resolveMinistereFromSubdomain($request);

        $settings = Setting::where('ministere_id', $ministere->id)
            ->get()
            ->keyBy('cle')
            ->map(function ($item) {
                if ($item->cle === 'live_actif' || $item->cle === 'don_actif') {
                    return $item->valeur === '1';
                }
                return $item->valeur;
            });

        return $this->respondSuccess(array_merge([
            'nom'                => $ministere->nom,
            'couleur_primaire'   => $ministere->couleur_primaire,
            'couleur_secondaire' => $ministere->couleur_secondaire,
            'logo'               => $ministere->logo ? Storage::url($ministere->logo) : null,
            'description'        => $ministere->description,
            'adresse'            => $ministere->adresse,
            'telephone'          => $ministere->telephone,
            'email_contact'      => $ministere->email_contact,
            'ville'              => $ministere->ville,
            'pays'               => $ministere->pays,
            'facebook_url'       => $ministere->facebook_url,
            'youtube_url'        => $ministere->youtube_url,
            'whatsapp'           => $ministere->whatsapp,
        ], $settings->toArray()));
    }

    public function getContent(Request $request)
    {
        $ministereId = $this->getMinistereId($request);
        $ministere   = Ministere::findOrFail($ministereId);

        return $this->respondSuccess($this->getContentData($ministereId, $ministere));
    }
}