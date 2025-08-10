<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Gazetteer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['code', 'type', 'parent_id', 'name', 'name_kh'];

    protected $revisionEnabled = true;
    protected $revisionCreationsEnabled = true;
    protected $revisionCleanup = true;
    protected $historyLimit = 500;

    /**
     * Get the parent gazetteer entry
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Gazetteer::class, 'parent_id');
    }

    /**
     * Get the child gazetteer entries
     */
    public function children(): HasMany
    {
        return $this->hasMany(Gazetteer::class, 'parent_id');
    }

    /**
     * Get patient addresses that reference this gazetteer as province
     */
    public function patientAddressesAsProvince(): HasMany
    {
        return $this->hasMany(PatientAddress::class, 'province_id');
    }

    /**
     * Get patient addresses that reference this gazetteer as district
     */
    public function patientAddressesAsDistrict(): HasMany
    {
        return $this->hasMany(PatientAddress::class, 'district_id');
    }

    /**
     * Get patient addresses that reference this gazetteer as commune
     */
    public function patientAddressesAsCommune(): HasMany
    {
        return $this->hasMany(PatientAddress::class, 'commune_id');
    }

    /**
     * Get patient addresses that reference this gazetteer as village
     */
    public function patientAddressesAsVillage(): HasMany
    {
        return $this->hasMany(PatientAddress::class, 'village_id');
    }

    /**
     * Scope to filter by type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to filter by parent
     */
    public function scopeChildrenOf($query, int $parentId)
    {
        return $query->where('parent_id', $parentId);
    }

    /**
     * Scope to search by name
     */
    public function scopeSearchByName($query, string $searchTerm)
    {
        return $query->where('name', 'LIKE', "%{$searchTerm}%");
    }

    /**
     * Get the full hierarchical path as a string
     */
    public function getFullPathAttribute(): string
    {
        $path = [];
        $current = $this;

        // Build path from current to root
        while ($current) {
            array_unshift($path, $current->name);
            $current = $current->parent;
        }

        return implode(' > ', $path);
    }

    /**
     * Check if this gazetteer is a province
     */
    public function isProvince(): bool
    {
        return $this->type === 'Province';
    }

    /**
     * Check if this gazetteer is a district
     */
    public function isDistrict(): bool
    {
        return $this->type === 'District';
    }

    /**
     * Check if this gazetteer is a commune
     */
    public function isCommune(): bool
    {
        return $this->type === 'Commune';
    }

    /**
     * Check if this gazetteer is a village
     */
    public function isVillage(): bool
    {
        return $this->type === 'Village';
    }
}
