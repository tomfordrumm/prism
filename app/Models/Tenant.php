<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps()->withPivot('role');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function providerCredentials(): HasMany
    {
        return $this->hasMany(ProviderCredential::class);
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

    public function feedback(): HasMany
    {
        return $this->hasMany(Feedback::class);
    }
}
