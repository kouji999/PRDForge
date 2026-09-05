<?php

namespace App\Models;

use App\Domain\Project\Enums\ProjectStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'status',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function conversations()
    {
        return $this->hasMany(Conversation::class);
    }

    public function context()
    {
        return $this->hasOne(ProjectContext::class);
    }

    public function requirements()
    {
        return $this->hasMany(Requirement::class);
    }

    public function prd()
    {
        return $this->hasOne(Prd::class);
    }

    public function aiGenerations()
    {
        return $this->hasMany(AiGeneration::class);
    }

    public function activeConversation(): ?Conversation
    {
        return $this->conversations()
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->first();
    }

    public function ensureContext(): ProjectContext
    {
        return $this->context()->firstOrCreate([]);
    }
}
