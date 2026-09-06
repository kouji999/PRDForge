<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiCombo extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Members ordered by priority (1 = primary). */
    public function members()
    {
        return $this->hasMany(AiComboMember::class)->orderBy('priority');
    }

    /** Providers in fallback order. */
    public function providers()
    {
        return $this->belongsToMany(AiProvider::class, 'ai_combo_members')
            ->withPivot('priority')
            ->orderByPivot('priority')
            ->withTimestamps();
    }
}
