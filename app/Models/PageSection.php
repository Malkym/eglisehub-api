<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PageSection extends Model
{
    protected $table = 'page_sections';

    protected $fillable = [
        'page_id', 'titre_section', 'type', 'ordre', 'actif',
    ];

    protected $casts = [
        'actif' => 'boolean',
    ];

    public function page()
    {
        return $this->belongsTo(Page::class);
    }

    public function contenus()
    {
        return $this->hasMany(PageContent::class, 'section_id')->orderBy('ordre');
    }
}