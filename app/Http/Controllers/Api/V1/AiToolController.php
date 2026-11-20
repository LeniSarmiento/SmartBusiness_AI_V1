<?php

namespace App\Http\Controllers\Api\V1;

use App\Ai\ToolExecutor;
use App\Ai\ToolRegistry;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiToolController extends Controller
{
    /**
     * List tools available to the current user in this organization.
     */
    public function index(Request $request, Organization $organization, ToolRegistry $registry): JsonResponse
    {
        $declarations = $registry->toFunctionDeclarations($organization, $request->user());

        return response()->json([
            'data' => [
                'organization_id' => $organization->id,
                'tools' => $declarations,
            ],
        ]);
    }

    /**
     * Execute a tool call requested by the AI.
     */
    public function execute(Request $request, Organization $organization, ToolExecutor $executor): JsonResponse
    {
        $validated = $request->validate([
            'tool' => ['required', 'string'],
            'arguments' => ['nullable', 'array'],
        ]);

        $result = $executor->execute(
            $organization,
            $request->user(),
            $validated['tool'],
            $validated['arguments'] ?? []
        );

        $status = $result['success'] ? 200 : match ($result['code'] ?? null) {
            'FORBIDDEN' => 403,
            'NOT_FOUND', 'TOOL_NOT_FOUND', 'TENANT_MISMATCH' => 404,
            'VALIDATION_ERROR' => 422,
            'HTTP_409' => 409,
            default => 500,
        };

        return response()->json($result, $status);
    }
}
