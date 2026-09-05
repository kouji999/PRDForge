<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrdSection extends Model
{
    use HasFactory;

    protected $fillable = [
        'prd_id',
        'key',
        'title',
        'content',
        'data',
        'order',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'order' => 'integer',
        ];
    }

    public function prd()
    {
        return $this->belongsTo(Prd::class);
    }
}
