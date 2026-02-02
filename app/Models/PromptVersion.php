<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromptVersion extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [];
    }

    public function promptTemplate(): BelongsTo
    {
        return $this->belongsTo(PromptTemplate::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function promptConversations(): HasMany
    {
        return $this->hasMany(PromptConversation::class, 'target_prompt_version_id');
    }
}
