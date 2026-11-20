<?php

namespace App\Ai\Contracts;

use App\Models\Organization;
use App\Models\User;

interface ToolInterface
{
    /**
     * Unique identifier for the tool (e.g. 'adjust_inventory').
     */
    public function getName(): string;

    /**
     * Detailed description of what the tool does, intended for LLMs and documentation.
     */
    public function getDescription(): string;

    /**
     * JSON Schema describing the input parameters expected by the tool.
     */
    public function getParameters(): array;

    /**
     * The permission name required by the OrganizationPolicy (e.g. 'inventory.write').
     */
    public function getRequiredPermission(): string;

    /**
     * Executes the tool within the given organization on behalf of the user.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function execute(Organization $organization, User $user, array $arguments): array;

    /**
     * Returns the tool schema formatted for OpenAI/Gemini function declaration.
     */
    public function toSchema(): array;
}
