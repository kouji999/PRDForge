<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiComboMember extends Model
{
    use HasFactory;

    protected $table = 'ai_combo_members';

    protected $fillable = [
        'ai_combo_id',
        'ai_provider_id',
        'priority',
    ];

    public function combo()
    {
        return $this->belongsTo(AiCombo::class, 'ai_combo_id');
    }

    public function provider()
    {
        return $this->belongsTo(AiProvider::class, 'ai_provider_id');
    }
}
