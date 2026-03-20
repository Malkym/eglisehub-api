<?php

namespace App\Models;

use App\Models\Article;
use App\Models\Ministere;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Slider extends Model
{
    use HasFactory;

    protected $fillable = [
        'ministere_id', 'titre', 'sous_titre', 'image', 'url_image',
        'bouton_texte', 'bouton_lien', 'position_texte',
        'couleur_texte', 'couleur_fond', 'ordre', 'actif',
        'type_media', 'video_path', 'video_thumbnail', 'video_size', 'video_mime_type',
    ];

    protected $casts = [
        'actif' => 'boolean',
        'ordre' => 'integer',
        'type_media' => 'string',
    ];

    protected function urlImage(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                $path = $value ?: $this->getRawOriginal('image');
                return Article::buildStorageUrl($path);
            },
        );
    }

    public function ministere()
    {
        return $this->belongsTo(Ministere::class);
    }

    // Accesseur pour l'URL de la vidéo
    public function getVideoUrlAttribute()
    {
        return $this->video_path ? Storage::url($this->video_path) : null;
    }

    // Accesseur pour l'URL de la miniature vidéo
    public function getVideoThumbnailUrlAttribute()
    {
        return $this->video_thumbnail ? Storage::url($this->video_thumbnail) : null;
    }
}