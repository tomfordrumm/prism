<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dataset extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function testCases(): HasMany
    {
        return $this->hasMany(TestCase::class);
    }
}
