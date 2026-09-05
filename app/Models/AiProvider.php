<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiProvider extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'base_url',
        'api_key',
        'model',
        'status',
        'last_error',
        'is_default',
        'last_tested_at',
    ];

    protected $hidden = [
        'api_key',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'is_default' => 'boolean',
            'last_tested_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function generations()
    {
        return $this->hasMany(AiGeneration::class);
    }
}
