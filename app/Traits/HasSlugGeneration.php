<?php

namespace App\Traits;

use Illuminate\Support\Str;

trait HasSlugGeneration
{
    protected function generateUniqueSlug(string $titre, int $ministereId, ?string $exceptId = null, string $modelClass = null, string $column = 'slug'): string
    {
        $modelClass = $modelClass ?? static::class;
        $slug = Str::slug($titre);
        $original = $slug;
        $count = 1;

        while (
            $modelClass::where('ministere_id', $ministereId)
                ->where($column, $slug)
                ->when($exceptId, fn($q) => $q->where('id', '!=', $exceptId))
                ->exists()
        ) {
            $slug = $original . '-' . $count++;
        }

        return $slug;
    }
}
