<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agent extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'model_params' => 'array',
        'tool_config' => 'array',
        'last_used_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function providerCredential(): BelongsTo
    {
        return $this->belongsTo(ProviderCredential::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(PromptConversation::class)->where('type', 'agent_chat');
    }

    public function analytics(): HasMany
    {
        return $this->hasMany(AgentAnalytics::class);
    }

    /**
     * Get the effective system prompt content
     */
    public function getSystemPrompt(): string
    {
        return $this->system_prompt_content ?? '';
    }

    /**
     * Update analytics after each conversation interaction
     */
    public function recordUsage(int $messages, int $tokensIn, int $tokensOut): void
    {
        $this->increment('total_conversations');
        $this->increment('total_messages', $messages);
        $this->increment('total_tokens_in', $tokensIn);
        $this->increment('total_tokens_out', $tokensOut);
        $this->update(['last_used_at' => now()]);

        // Update or create daily analytics
        $today = now()->toDateString();

        // First, try to find existing record
        $analytics = $this->analytics()->where('date', $today)->first();

        if ($analytics) {
            // Update existing record
            $analytics->increment('conversations_count');
            $analytics->increment('messages_count', $messages);
            $analytics->increment('tokens_in', $tokensIn);
            $analytics->increment('tokens_out', $tokensOut);
        } else {
            // Create new record with initial values
            try {
                $this->analytics()->create([
                    'date' => $today,
                    'conversations_count' => 1,
                    'messages_count' => $messages,
                    'tokens_in' => $tokensIn,
                    'tokens_out' => $tokensOut,
                ]);
            } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                // Race condition: record was created between our check and insert
                // Try to update the existing record
                $analytics = $this->analytics()->where('date', $today)->first();
                if ($analytics) {
                    $analytics->increment('conversations_count');
                    $analytics->increment('messages_count', $messages);
                    $analytics->increment('tokens_in', $tokensIn);
                    $analytics->increment('tokens_out', $tokensOut);
                }
            }
        }
    }

    /**
     * Get analytics for the last N days
     */
    public function getAnalyticsForPeriod(int $days = 30): array
    {
        $analytics = $this->analytics()
            ->where('date', '>=', now()->subDays($days))
            ->orderBy('date')
            ->get();

        return [
            'labels' => $analytics->pluck('date')->map(fn ($date) => $date->format('M d'))->toArray(),
            'conversations' => $analytics->pluck('conversations_count')->toArray(),
            'messages' => $analytics->pluck('messages_count')->toArray(),
            'tokens_in' => $analytics->pluck('tokens_in')->toArray(),
            'tokens_out' => $analytics->pluck('tokens_out')->toArray(),
        ];
    }
}
