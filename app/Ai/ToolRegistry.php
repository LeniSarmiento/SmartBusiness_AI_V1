<?php

namespace App\Ai;

use App\Ai\Contracts\ToolInterface;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class ToolRegistry
{
    /** @var array<string, ToolInterface> */
    private array $tools = [];

    /**
     * Registers a new tool in the registry.
     */
    public function register(ToolInterface $tool): self
    {
        $this->tools[$tool->getName()] = $tool;

        return $this;
    }

    /**
     * Retrieves a tool by its unique name.
     */
    public function get(string $name): ?ToolInterface
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * Returns all registered tools.
     *
     * @return array<ToolInterface>
     */
    public function all(): array
    {
        return array_values($this->tools);
    }

    /**
     * Returns only the tools that the user is authorized to execute in the given organization.
     *
     * @return array<ToolInterface>
     */
    public function forUser(Organization $organization, User $user): array
    {
        return array_values(array_filter($this->tools, function (ToolInterface $tool) use ($organization, $user) {
            return Gate::forUser($user)->allows('permission', [$organization, $tool->getRequiredPermission()]);
        }));
    }

    /**
     * Exports tools formatted as OpenAI/Gemini function declarations.
     * If organization and user are supplied, only authorized tools are exported.
     *
     * @return array<array<string, mixed>>
     */
    public function toFunctionDeclarations(?Organization $organization = null, ?User $user = null): array
    {
        $tools = ($organization && $user)
            ? $this->forUser($organization, $user)
            : $this->all();

        return array_map(fn (ToolInterface $tool) => $tool->toSchema(), $tools);
    }
}
