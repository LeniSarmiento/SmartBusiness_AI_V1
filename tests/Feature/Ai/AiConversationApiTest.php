<?php

namespace Tests\Feature\Ai;

use App\Ai\Agents\BusinessAssistantAgent;
use App\Models\AiConversation;
use App\Models\Organization;
use Illuminate\Support\Str;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Laravel\Ai\Responses\Data\ToolCall;
use Tests\Feature\BusinessTestCase;

class AiConversationApiTest extends BusinessTestCase
{
    public function test_admin_can_create_conversation_send_message_and_read_persisted_history(): void
    {
        BusinessAssistantAgent::fake([
            new ToolCall((string) Str::uuid(), 'get_dashboard_report', []),
            'Este mes no hay ventas registradas.',
        ]);

        $this->actingAs($this->admin);

        $conversationId = $this->postJson($this->endpoint('ai/conversations'), [])
            ->assertCreated()
            ->json('data.id');

        $this->postJson($this->endpoint("ai/conversations/{$conversationId}/messages"), [
            'content' => '¿Cuánto vendimos este mes?',
        ])->assertCreated()
            ->assertJsonPath('data.role', 'assistant')
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.content', 'Este mes no hay ventas registradas.')
            ->assertJsonPath('data.metadata.tool_calls.0.name', 'get_dashboard_report');

        $this->getJson($this->endpoint("ai/conversations/{$conversationId}"))
            ->assertOk()
            ->assertJsonPath('data.title', '¿Cuánto vendimos este mes?')
            ->assertJsonCount(2, 'data.messages')
            ->assertJsonPath('data.messages.0.role', 'user')
            ->assertJsonPath('data.messages.1.role', 'assistant');

        $this->assertDatabaseHas('ai_conversations', [
            'id' => $conversationId,
            'organization_id' => $this->organization->id,
            'user_id' => $this->admin->id,
        ]);
        $this->assertDatabaseCount('ai_messages', 2);
        $this->assertDatabaseHas('ai_usage_records', [
            'conversation_id' => $conversationId,
            'status' => 'completed',
        ]);
    }

    public function test_conversations_are_private_to_their_owner_and_organization(): void
    {
        $conversation = AiConversation::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->admin->id,
            'title' => 'Privada',
        ]);
        $otherUser = $this->member('admin');

        $this->actingAs($otherUser)
            ->getJson($this->endpoint("ai/conversations/{$conversation->id}"))
            ->assertForbidden();

        $this->actingAs($otherUser)
            ->getJson($this->endpoint('ai/conversations'))
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $otherOrganization = Organization::create(['name' => 'Otra empresa']);
        $otherAdmin = $this->member('admin', $otherOrganization);

        $this->actingAs($otherAdmin)
            ->getJson('/api/v1/organizations/'.$otherOrganization->id.'/ai/conversations/'.$conversation->id)
            ->assertNotFound();
    }

    public function test_seller_without_reports_permission_cannot_use_chat(): void
    {
        $seller = $this->member('seller');

        $this->actingAs($seller)
            ->postJson($this->endpoint('ai/conversations'), [])
            ->assertForbidden();
    }

    public function test_provider_failure_is_safe_and_recorded_without_message_details(): void
    {
        BusinessAssistantAgent::fake([
            fn () => throw ProviderConnectionException::forProvider('gemini'),
        ]);
        $this->actingAs($this->admin);
        $conversationId = $this->postJson($this->endpoint('ai/conversations'), [
            'title' => 'Fallo controlado',
        ])->assertCreated()->json('data.id');

        $this->postJson($this->endpoint("ai/conversations/{$conversationId}/messages"), [
            'content' => 'Consulta temporal',
        ])->assertStatus(503)
            ->assertJsonPath('message', 'El asistente no está disponible en este momento. Inténtalo nuevamente.');

        $this->assertDatabaseHas('ai_messages', [
            'conversation_id' => $conversationId,
            'role' => 'user',
            'status' => 'failed',
        ]);
        $this->assertDatabaseHas('ai_usage_records', [
            'conversation_id' => $conversationId,
            'status' => 'failed',
            'error_class' => ProviderConnectionException::class,
        ]);
        $this->assertDatabaseMissing('ai_usage_records', ['error_class' => 'gemini']);
    }

    public function test_archived_conversation_is_hidden_and_rejects_new_messages(): void
    {
        BusinessAssistantAgent::fake(['Respuesta']);
        $this->actingAs($this->admin);
        $conversationId = $this->postJson($this->endpoint('ai/conversations'), [])
            ->assertCreated()
            ->json('data.id');

        $this->deleteJson($this->endpoint("ai/conversations/{$conversationId}"))->assertNoContent();
        $this->getJson($this->endpoint('ai/conversations'))->assertOk()->assertJsonCount(0, 'data');
        $this->postJson($this->endpoint("ai/conversations/{$conversationId}/messages"), [
            'content' => 'No debe enviarse',
        ])->assertStatus(409);
    }

    public function test_chat_validates_message_size(): void
    {
        config(['ai.assistant.max_input_chars' => 20]);
        $this->actingAs($this->admin);
        $conversationId = $this->postJson($this->endpoint('ai/conversations'), [])
            ->assertCreated()
            ->json('data.id');

        $this->postJson($this->endpoint("ai/conversations/{$conversationId}/messages"), [
            'content' => str_repeat('x', 21),
        ])->assertUnprocessable();
    }

    public function test_chat_has_an_independent_rate_limit(): void
    {
        $this->actingAs($this->admin);

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->postJson($this->endpoint('ai/conversations'), [
                'title' => "Conversación {$attempt}",
            ])->assertCreated();
        }

        $this->postJson($this->endpoint('ai/conversations'), [
            'title' => 'Excede límite',
        ])->assertTooManyRequests();
    }
}
