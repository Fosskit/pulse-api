<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Concept extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'system_id',
        'concept_category_id',
        'name',
        'parent_id',
        'sort_order',
        'is_active',
        'description',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    // Relationships
    public function category(): BelongsTo
    {
        return $this->belongsTo(ConceptCategory::class, 'concept_category_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Concept::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Concept::class, 'parent_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('concept_category_id', $categoryId);
    }

    public function scopeByCode($query, $code)
    {
        return $query->where('code', $code);
    }
}