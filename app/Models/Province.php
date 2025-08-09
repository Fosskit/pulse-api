<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Venturecraft\Revisionable\RevisionableTrait;

class Province extends Model
{
    use HasFactory, SoftDeletes, RevisionableTrait;

    protected $fillable = ['code', 'name', 'name_kh'];

    protected $revisionEnabled = true;
    protected $revisionCreationsEnabled = true;
    protected $revisionCleanup = true;
    protected $historyLimit = 500;
    protected $keepRevisionOf = ['code', 'name', 'name_kh', 'deleted_at'];

    public function districts()
    {
        return $this->hasMany(District::class);
    }
}
