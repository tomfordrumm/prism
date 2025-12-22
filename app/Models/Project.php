<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Project extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'uuid' => 'string',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Project $project): void {
            if (! $project->uuid) {
                $project->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function promptTemplates(): HasMany
    {
        return $this->hasMany(PromptTemplate::class);
    }

    public function chains(): HasMany
    {
        return $this->hasMany(Chain::class);
    }

    public function datasets(): HasMany
    {
        return $this->hasMany(Dataset::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(Run::class);
    }
}
