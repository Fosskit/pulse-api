<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Terminology extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'name_kh',
        'parent_id',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the parent taxonomy.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Terminology::class, 'parent_id');
    }

    /**
     * Get the child taxonomies.
     */
    public function children(): HasMany
    {
        return $this->hasMany(Terminology::class, 'parent_id');
    }

    /**
     * Get the values for this taxonomy.
     */
    public function values(): HasMany
    {
        return $this->hasMany(TaxonomyValue::class, 'taxonomy_id');
    }

    /**
     * Get all active children taxonomies.
     */
    public function activeChildren()
    {
        return $this->children()->where('is_active', true)->orderBy('sort_order');
    }

    /**
     * Get a taxonomy by code.
     */
    public static function findByCode(string $code)
    {
        return static::where('code', $code)->first();
    }

    /**
     * Get the full hierarchical path of the taxonomy.
     */
    public function getPath()
    {
        $path = [$this];
        $current = $this;

        while ($current->parent) {
            $current = $current->parent;
            array_unshift($path, $current);
        }

        return $path;
    }
}
