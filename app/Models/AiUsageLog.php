<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiUsageLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_id',
        'user_id',
        'provider_id',
        'provider_name',
        'model',
        'operation',
        'status',
        'error_category',
        'input_tokens',
        'output_tokens',
        'latency_ms',
        'context',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'latency_ms' => 'integer',
        ];
    }
}
