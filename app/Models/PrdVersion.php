<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrdVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'prd_id',
        'version',
        'label',
        'snapshot',
        'section_count',
    ];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'section_count' => 'integer',
        ];
    }

    public function prd()
    {
        return $this->belongsTo(Prd::class);
    }
}
