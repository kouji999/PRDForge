<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiGeneration extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'project_id',
        'provider_id',
        'type',
        'status',
        'error_category',
        'latency_ms',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'latency_ms' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function provider()
    {
        return $this->belongsTo(AiProvider::class);
    }
}
