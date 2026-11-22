<?php

declare(strict_types=1);

namespace App\Payments\Application;

use App\SharedKernel\Http\ApiException;
use Symfony\Component\HttpFoundation\Request;

/**
 * Adaptateur simulé. Un retour navigateur n’appelle jamais confirm().
 */
final class SimulatedPaymentAdapter
{
    public function __construct(private readonly string $appSecret)
    {
    }

    public function token(): string
    {
        return hash('sha256', $this->appSecret.':simulated-adapter');
    }

    public function assertServerConfirmation(Request $request): void
    {
        $flag = $request->headers->get('X-Adapter-Confirmation');
        $given = (string) $request->headers->get('X-Adapter-Token', '');
        if ($flag !== 'simulated' || $given === '' || !hash_equals($this->token(), $given)) {
            throw new ApiException(403, 'FORBIDDEN', 'Confirmation adaptateur refusée.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function nextAction(string $attemptId): array
    {
        return [
            'type' => 'simulated_provider',
            'attempt_id' => $attemptId,
            'status' => 'pending',
            'note' => 'Seul l’adaptateur serveur peut confirmer ce paiement.',
        ];
    }
}
