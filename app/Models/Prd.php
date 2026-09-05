<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Prd extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'summary',
        'status',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function sections()
    {
        return $this->hasMany(PrdSection::class)->orderBy('order');
    }

    public function versions()
    {
        return $this->hasMany(PrdVersion::class)->orderByDesc('id');
    }
}
