<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendAiMessageRequest;
use App\Http\Requests\StoreAiConversationRequest;
use App\Http\Resources\AiConversationResource;
use App\Http\Resources\AiMessageResource;
use App\Models\AiConversation;
use App\Models\Organization;
use App\Services\BusinessAssistantService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Exceptions\AiException;

class AiConversationController extends Controller
{
    public function index(Request $request, Organization $organization)
    {
        Gate::authorize('permission', [$organization, 'reports.read']);

        $conversations = AiConversation::forOrganization($organization)
            ->where('user_id', $request->user()->id)
            ->whereNull('archived_at')
            ->latest('updated_at')
            ->paginate(20);

        return AiConversationResource::collection($conversations);
    }

    public function store(StoreAiConversationRequest $request, Organization $organization)
    {
        Gate::authorize('permission', [$organization, 'reports.read']);

        $conversation = AiConversation::create([
            'organization_id' => $organization->id,
            'user_id' => $request->user()->id,
            'title' => $request->validated('title'),
        ]);

        return (new AiConversationResource($conversation))->response()->setStatusCode(201);
    }

    public function show(Request $request, Organization $organization, AiConversation $conversation)
    {
        $this->authorizeConversation($organization, $conversation, 'view');

        return new AiConversationResource($conversation->load([
            'messages' => fn ($query) => $query->where('status', 'completed')->oldest('id'),
        ]));
    }

    public function message(
        SendAiMessageRequest $request,
        Organization $organization,
        AiConversation $conversation,
        BusinessAssistantService $assistant,
    ) {
        $this->authorizeConversation($organization, $conversation, 'update');
        abort_if($conversation->archived_at !== null, 409, 'La conversación está archivada.');

        try {
            $message = $assistant->ask(
                $conversation,
                $organization,
                $request->user(),
                $request->validated('content'),
            );
        } catch (AiException) {
            return response()->json([
                'message' => 'El asistente no está disponible en este momento. Inténtalo nuevamente.',
                'request_id' => $request->attributes->get('request_id'),
            ], 503);
        }

        return (new AiMessageResource($message))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, Organization $organization, AiConversation $conversation)
    {
        $this->authorizeConversation($organization, $conversation, 'update');
        $conversation->update(['archived_at' => now()]);

        return response()->noContent();
    }

    private function authorizeConversation(Organization $organization, AiConversation $conversation, string $ability): void
    {
        abort_unless($conversation->organization_id === $organization->id, 404);
        Gate::authorize($ability, $conversation);
    }
}
