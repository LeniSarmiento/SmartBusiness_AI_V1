<?php

namespace App\Ai\Agents;

use App\Ai\Tools\GetDashboardReportTool;
use App\Models\Organization;
use App\Models\User;
use App\Services\ReportingService;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Laravel\Ai\Approvals\Decisions;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\AgentInput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Exceptions\AiException;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Promptable;
use Laravel\Ai\Providers\Provider;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Stringable;

class BusinessAssistantAgent implements Agent, HasTools
{
    use Promptable {
        prompt as protected promptUsingSdk;
        stream as protected streamUsingSdk;
    }

    public readonly Organization $organization;

    public readonly User $user;

    private GetDashboardReportTool $dashboardTool;

    public function __construct(
        Organization|User $organization,
        Organization|User $user,
        ?ReportingService $reportingService = null,
    ) {
        if ($organization instanceof Organization && $user instanceof User) {
            $this->organization = $organization;
            $this->user = $user;
        } elseif ($organization instanceof User && $user instanceof Organization) {
            $this->user = $organization;
            $this->organization = $user;
        } else {
            throw new InvalidArgumentException('BusinessAssistantAgent requiere un usuario y una organización.');
        }

        $service = $reportingService ?? app(ReportingService::class);
        $this->dashboardTool = new GetDashboardReportTool($service, $this->organization, $this->user);
    }

    public function instructions(): Stringable|string
    {
        return implode(' ', [
            "Eres el asistente empresarial de SmartBusiness para la organización \"{$this->organization->name}\" (ID: {$this->organization->id}).",
            "La moneda configurada es {$this->organization->currency} y la zona horaria es {$this->organization->timezone}.",
            'Responde únicamente con base en los indicadores devueltos por las herramientas autorizadas de la organización.',
            'No inventes cifras ni modifiques datos. Responde siempre en español de forma clara y concisa.',
        ]);
    }

    /**
     * @return list<Tool>
     */
    public function tools(): iterable
    {
        if (! Gate::forUser($this->user)->allows('permission', [$this->organization, $this->dashboardTool->getRequiredPermission()])) {
            return [];
        }

        return [$this->dashboardTool];
    }

    public function provider(): string
    {
        $provider = config('ai.assistant.provider') ?: config('ai.default');

        if ($provider instanceof Lab) {
            $provider = $provider->value;
        }

        if (! is_string($provider) || trim($provider) === '') {
            throw new AiException('No se ha configurado un proveedor de IA para el asistente.');
        }

        return trim($provider);
    }

    public function model(): ?string
    {
        $model = config('ai.assistant.model') ?: config('ai.model');

        return is_string($model) && trim($model) !== '' ? trim($model) : null;
    }

    public function timeout(): int
    {
        return max(1, (int) config('ai.assistant.timeout', 60));
    }

    public function prompt(
        AgentInput|UserMessage|Decisions|string $prompt,
        array $attachments = [],
        Lab|array|string|null $provider = null,
        ?string $model = null,
        ?int $timeout = null,
    ): AgentResponse {
        $this->dashboardTool->authorize($this->organization, $this->user);
        $this->ensureProviderConfigured($provider);

        return $this->promptUsingSdk($prompt, $attachments, $provider, $model, $timeout);
    }

    public function stream(
        AgentInput|UserMessage|Decisions|string $prompt,
        array $attachments = [],
        Lab|array|string|null $provider = null,
        ?string $model = null,
        ?int $timeout = null,
    ): StreamableAgentResponse {
        $this->dashboardTool->authorize($this->organization, $this->user);
        $this->ensureProviderConfigured($provider);

        return $this->streamUsingSdk($prompt, $attachments, $provider, $model, $timeout);
    }

    private function ensureProviderConfigured(Lab|array|string|null $provider = null): void
    {
        $resolved = $provider ?? $this->provider();
        $names = is_array($resolved)
            ? array_keys(Provider::formatProviderAndModelList($resolved))
            : [$resolved instanceof Lab ? $resolved->value : (string) $resolved];

        foreach ($names as $name) {
            $config = config("ai.providers.{$name}");

            if (! is_array($config) || empty($config['driver'])) {
                throw new AiException("El proveedor de IA [{$name}] no está configurado.");
            }

            if (! static::isFaked() && $config['driver'] !== 'ollama' && blank($config['key'] ?? null)) {
                throw new AiException("El proveedor de IA [{$name}] no tiene una clave API configurada.");
            }
        }
    }
}
