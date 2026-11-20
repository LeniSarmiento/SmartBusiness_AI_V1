<?php

namespace App\Ai;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class ToolExecutor
{
    public function __construct(private ToolRegistry $registry) {}

    /**
     * Executes a tool by name with arguments on behalf of the user in an organization.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function execute(Organization $organization, User $user, string $toolName, array $arguments = []): array
    {
        $tool = $this->registry->get($toolName);

        if (! $tool) {
            return [
                'success' => false,
                'tool' => $toolName,
                'error' => "Herramienta desconocida: '{$toolName}'.",
                'code' => 'TOOL_NOT_FOUND',
            ];
        }

        // Enforce membership verification at runtime
        $isMember = $user->memberships()->where('organization_id', $organization->id)->where('active', true)->exists();
        if (! $isMember) {
            return [
                'success' => false,
                'tool' => $toolName,
                'error' => 'El usuario no pertenece a la organización especificada.',
                'code' => 'TENANT_MISMATCH',
            ];
        }

        try {
            $result = $tool->execute($organization, $user, $arguments);

            return [
                'success' => true,
                'tool' => $toolName,
                'data' => $result,
            ];
        } catch (AuthorizationException $e) {
            return [
                'success' => false,
                'tool' => $toolName,
                'error' => 'No tienes permisos para ejecutar esta acción en la organización.',
                'code' => 'FORBIDDEN',
            ];
        } catch (ValidationException $e) {
            return [
                'success' => false,
                'tool' => $toolName,
                'error' => 'Error de validación en los argumentos suministrados a la herramienta.',
                'validation_errors' => $e->errors(),
                'code' => 'VALIDATION_ERROR',
            ];
        } catch (ModelNotFoundException $e) {
            return [
                'success' => false,
                'tool' => $toolName,
                'error' => 'Uno o más recursos solicitados no existen o no pertenecen a tu organización.',
                'code' => 'NOT_FOUND',
            ];
        } catch (HttpException $e) {
            return [
                'success' => false,
                'tool' => $toolName,
                'error' => $e->getMessage() ?: 'Error HTTP en la ejecución.',
                'code' => 'HTTP_'.$e->getStatusCode(),
            ];
        } catch (Throwable $e) {
            report($e);

            return [
                'success' => false,
                'tool' => $toolName,
                'error' => 'No fue posible ejecutar la herramienta. Consulta los registros con el identificador de la solicitud.',
                'code' => 'EXECUTION_ERROR',
            ];
        }
    }
}
