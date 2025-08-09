<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Venturecraft\Revisionable\RevisionableTrait;

class Gazetteer extends Model
{
    use HasFactory, SoftDeletes, RevisionableTrait;

    protected $fillable = ['code', 'type', 'parent_id', 'name', 'name_kh'];

    protected $revisionEnabled = true;
    protected $revisionCreationsEnabled = true;
    protected $revisionCleanup = true;
    protected $historyLimit = 500;

}
