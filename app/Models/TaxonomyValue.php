<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxonomyValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'taxonomy_id',
        'code',
        'name',
        'name_kh',
        'parent_id',
        'sort_order',
        'is_active',
        'description',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the taxonomy this value belongs to.
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Terminology::class, 'taxonomy_id');
    }

    /**
     * Get the parent value.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(TaxonomyValue::class, 'parent_id');
    }

    /**
     * Get the child values.
     */
    public function children(): HasMany
    {
        return $this->hasMany(TaxonomyValue::class, 'parent_id');
    }

    /**
     * Find a value by taxonomy code and value code.
     */
    public static function findByCode(string $taxonomyCode, string $code)
    {
        $taxonomy = Terminology::where('code', $taxonomyCode)->first();

        if (!$taxonomy) {
            return null;
        }

        return static::where('taxonomy_id', $taxonomy->id)
            ->where('code', $code)
            ->first();
    }

    /**
     * Get all options for a specific taxonomy.
     */
    public static function getOptions(string $taxonomyCode)
    {
        $taxonomy = Terminology::where('code', $taxonomyCode)->first();

        if (!$taxonomy) {
            return collect();
        }

        return static::where('taxonomy_id', $taxonomy->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Get a hierarchical tree of options for a taxonomy.
     */
    public static function getHierarchicalOptions(string $taxonomyCode)
    {
        $taxonomy = Terminology::where('code', $taxonomyCode)->first();

        if (!$taxonomy) {
            return collect();
        }

        // Get root level items
        $rootItems = static::where('taxonomy_id', $taxonomy->id)
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        // For each root item, recursively load children
        foreach ($rootItems as $item) {
            $item->loadMissing('children');
        }

        return $rootItems;
    }

    /**
     * Get all options for a specific taxonomy as an array suitable for select lists.
     */
    public static function getSelectOptions(string $taxonomyCode, bool $useKhmer = false)
    {
        $field = $useKhmer ? 'name_kh' : 'name';
        $taxonomy = Terminology::where('code', $taxonomyCode)->first();

        if (!$taxonomy) {
            return [];
        }

        return static::where('taxonomy_id', $taxonomy->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->pluck($field, 'code')
            ->toArray();
    }

    /**
     * Get the full name including taxonomy path.
     */
    public function getFullName($useKhmer = false)
    {
        $field = $useKhmer ? 'name_kh' : 'name';
        $taxonomyField = $useKhmer ? 'name_kh' : 'name';

        $taxonomyName = $this->taxonomy ? $this->taxonomy->$taxonomyField : '';
        return $taxonomyName . ' » ' . $this->$field;
    }
}
