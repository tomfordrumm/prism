<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RunStep extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_raw' => 'array',
            'parsed_output' => 'array',
            'validation_errors' => 'array',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(Run::class);
    }

    public function chainNode(): BelongsTo
    {
        return $this->belongsTo(ChainNode::class);
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(Feedback::class);
    }
}
