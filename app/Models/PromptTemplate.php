<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\PromptVariableParser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class PromptTemplate extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function promptVersions(): HasMany
    {
        return $this->hasMany(PromptVersion::class);
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(PromptVersion::class)->latestOfMany('version');
    }

    public function createNewVersion(array $attributes): PromptVersion
    {
        return DB::transaction(function () use ($attributes): PromptVersion {
            $nextVersion = ($this->promptVersions()->max('version') ?? 0) + 1;

            /** @var PromptVersion $version */
            $version = $this->promptVersions()->create([
                'tenant_id' => $this->tenant_id,
                'version' => $nextVersion,
                ...$attributes,
            ]);

            $this->syncVariablesFromContent($version->content);

            return $version;
        });
    }

    private function syncVariablesFromContent(string $content): void
    {
        $parsedNames = PromptVariableParser::extract($content);
        /** @var array $existing */
        $existing = $this->variables ?? [];

        /** @var array<string, array> $existingByName */
        $existingByName = [];
        foreach ($existing as $key => $variable) {
            if (is_array($variable)) {
                $name = $variable['name'] ?? (is_string($key) ? $key : null);
                if ($name) {
                    $existingByName[$name] = $variable + ['name' => $name];
                }
            } elseif (is_string($variable)) {
                $existingByName[$variable] = ['name' => $variable];
            }
        }

        $newVariables = array_map(function (string $name) use ($existingByName): array {
            $existing = $existingByName[$name] ?? null;

            return array_merge(['name' => $name], $existing ?: []);
        }, $parsedNames);

        $this->forceFill(['variables' => $newVariables])->save();
    }
}
