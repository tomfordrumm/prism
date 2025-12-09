<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TestCase extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'input_variables' => 'array',
            'expected_output' => 'array',
            'tags' => 'array',
        ];
    }

    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(Run::class);
    }
}
