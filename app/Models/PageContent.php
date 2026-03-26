<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PageContent extends Model
{
    protected $table = 'page_contents';

    protected $fillable = [
        'section_id', 'type_contenu', 'contenu',
        'url_media', 'options', 'ordre',
    ];

    protected $casts = [
        'options' => 'array',
    ];

    public function section()
    {
        return $this->belongsTo(PageSection::class, 'section_id');
    }
}