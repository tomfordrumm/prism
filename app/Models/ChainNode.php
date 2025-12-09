<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property array<int, array<string, mixed>|string>|null $messages_config
 * @property array|null $model_params
 * @property array|null $output_schema
 * @property string|null $output_schema_definition
 */
class ChainNode extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'messages_config' => 'array',
            'output_schema' => 'array',
            'output_schema_definition' => 'string',
            'stop_on_validation_error' => 'boolean',
            'model_params' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Chain, $this>
     */
    public function chain(): BelongsTo
    {
        return $this->belongsTo(Chain::class);
    }

    /**
     * @return BelongsTo<ProviderCredential, $this>
     */
    public function providerCredential(): BelongsTo
    {
        return $this->belongsTo(ProviderCredential::class);
    }

    /**
     * @return HasMany<RunStep, $this>
     */
    public function runSteps(): HasMany
    {
        return $this->hasMany(RunStep::class);
    }
}
