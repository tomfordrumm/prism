<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromptConversation extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $guarded = ['id'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(Run::class);
    }

    public function runStep(): BelongsTo
    {
        return $this->belongsTo(RunStep::class);
    }

    public function targetPromptVersion(): BelongsTo
    {
        return $this->belongsTo(PromptVersion::class, 'target_prompt_version_id');
    }

    /** @return HasMany<PromptMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(PromptMessage::class, 'conversation_id');
    }
}
