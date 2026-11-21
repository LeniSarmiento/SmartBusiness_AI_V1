<?php

namespace App\Services;

use App\Ai\Agents\BusinessAssistantAgent;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\AiUsageRecord;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

class BusinessAssistantService
{
    public function ask(AiConversation $conversation, Organization $organization, User $user, string $content): AiMessage
    {
        $userMessage = $conversation->messages()->create([
            'role' => 'user',
            'status' => 'pending',
            'content' => trim($content),
        ]);
        $startedAt = hrtime(true);

        try {
            $response = BusinessAssistantAgent::make(
                organization: $organization,
                user: $user,
                conversation: $conversation,
            )->prompt($userMessage->content);

            return DB::transaction(function () use ($conversation, $organization, $user, $userMessage, $response, $startedAt) {
                $userMessage->update(['status' => 'completed']);
                $assistantMessage = $conversation->messages()->create([
                    'role' => 'assistant',
                    'status' => 'completed',
                    'content' => $response->text,
                    'metadata' => [
                        'tool_calls' => $response->toolCalls
                            ->map(fn ($toolCall) => ['id' => $toolCall->id, 'name' => $toolCall->name])
                            ->values()
                            ->all(),
                    ],
                ]);

                $this->recordUsage(
                    conversation: $conversation,
                    organization: $organization,
                    user: $user,
                    message: $assistantMessage,
                    durationMs: $this->durationMs($startedAt),
                    status: 'completed',
                    provider: $response->meta->provider,
                    model: $response->meta->model,
                    inputTokens: $response->usage->inputTokens,
                    outputTokens: $response->usage->outputTokens,
                    cachedInputTokens: $response->usage->cacheReadInputTokens,
                    reasoningTokens: $response->usage->reasoningTokens,
                );

                $conversation->update([
                    'title' => $conversation->title ?: mb_substr($userMessage->content, 0, 120),
                    'last_message_at' => now(),
                ]);

                return $assistantMessage;
            });
        } catch (Throwable $exception) {
            $userMessage->update(['status' => 'failed']);
            $this->recordUsage(
                conversation: $conversation,
                organization: $organization,
                user: $user,
                message: null,
                durationMs: $this->durationMs($startedAt),
                status: 'failed',
                errorClass: $exception::class,
            );

            throw $exception;
        }
    }

    private function recordUsage(
        AiConversation $conversation,
        Organization $organization,
        User $user,
        ?AiMessage $message,
        int $durationMs,
        string $status,
        ?string $provider = null,
        ?string $model = null,
        int $inputTokens = 0,
        int $outputTokens = 0,
        ?int $cachedInputTokens = null,
        ?int $reasoningTokens = null,
        ?string $errorClass = null,
    ): void {
        AiUsageRecord::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'conversation_id' => $conversation->id,
            'message_id' => $message?->id,
            'provider' => $provider,
            'model' => $model,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cached_input_tokens' => $cachedInputTokens,
            'reasoning_tokens' => $reasoningTokens,
            'duration_ms' => $durationMs,
            'status' => $status,
            'error_class' => $errorClass,
        ]);
    }

    private function durationMs(int $startedAt): int
    {
        return max(0, (int) round((hrtime(true) - $startedAt) / 1_000_000));
    }
}
