<?php

namespace App\Ai\Tools;

use App\Ai\Contracts\ToolInterface;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

abstract class BaseTool implements ToolInterface
{
    /**
     * Authorizes the user for this tool's required permission within the organization.
     *
     * @throws AuthorizationException
     */
    public function authorize(Organization $organization, User $user): void
    {
        Gate::forUser($user)->authorize('permission', [$organization, $this->getRequiredPermission()]);
    }

    /**
     * Formats tool as standard OpenAI/Gemini function declaration.
     */
    public function toSchema(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->getName(),
                'description' => $this->getDescription(),
                'parameters' => $this->getParameters(),
            ],
        ];
    }

    /**
     * Validates arguments using Laravel's Validator.
     *
     * @throws ValidationException
     */
    protected function validate(array $arguments, array $rules, array $messages = []): array
    {
        $validator = Validator::make($arguments, $rules, $messages);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }
}
