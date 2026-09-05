<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectContext extends Model
{
    use HasFactory;

    protected $fillable = [
        'problem',
        'target_users',
        'product_concept',
        'core_features',
        'platform',
        'constraints',
        'goals',
        'mvp_scope',
        'ai_extracted_at',
    ];

    protected function casts(): array
    {
        return [
            'core_features' => 'array',
            'goals' => 'array',
            'ai_extracted_at' => 'datetime',
        ];
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
