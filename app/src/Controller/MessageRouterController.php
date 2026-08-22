<?php

namespace App\Controller;

use App\Agent\ToolInvocationTracker;
use App\Dto\RouteMessageRequest;
use App\Tool\SendDepartmentEmailTool;
use OpenApi\Attributes as OA;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Exception\ExceptionInterface as AgentExceptionInterface;
use Symfony\AI\Platform\Exception\ExceptionInterface as PlatformExceptionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

class MessageRouterController extends AbstractController
{
    public function __construct(
        private readonly AgentInterface $agent,
        private readonly ToolInvocationTracker $tracker,
        private readonly SendDepartmentEmailTool $tool,
    ) {}

    #[Route('/api/v1/messages', name: 'route_message', methods: ['POST'])]
    #[OA\Response(
        response: 200,
        description: 'Message classified and routed to the appropriate department',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'response', type: 'string'),
            ]
        )
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation failed (invalid email or missing message)'
    )]
    #[OA\Response(
        response: 503,
        description: 'AI model is unavailable (e.g. still downloading, or Ollama container not running)',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string'),
            ]
        )
    )]
    #[OA\Response(
        response: 502,
        description: 'Message was classified but could not actually be sent (mail transport unavailable)',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string'),
            ]
        )
    )]
    public function index(#[MapRequestPayload] RouteMessageRequest $payload): JsonResponse
    {
        try {
            $result = $this->agent->call($payload->message);
        } catch (AgentExceptionInterface | PlatformExceptionInterface) {
            return $this->json(
                ['error' => 'Model AI jest niedostępny (np. wciąż się pobiera albo kontener Ollama nie działa). Spróbuj ponownie za chwilę.'],
                Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }

        // Deterministic fallback: the LLM does not reliably call the tool for
        // every input despite explicit prompt instructions (observed
        // empirically, see README) — if it didn't, route to other@ directly
        // instead of silently sending nothing. The model's own text in this
        // case describes an action it didn't take, so it's replaced here
        // rather than passed through, to avoid misleading the API caller.
        if (!$this->tracker->invoked) {
            ($this->tool)('other@example.com');

            if (!$this->tracker->sent) {
                return $this->json(
                    ['error' => 'Nie udało się wysłać maila — sprawdź czy serwer pocztowy działa.'],
                    Response::HTTP_BAD_GATEWAY,
                );
            }

            return $this->json([
                'response' => 'Nie udało się jednoznacznie sklasyfikować zgłoszenia — przekazano do other@example.com.',
            ]);
        }

        // The tool was invoked (by the model), but the actual send may still
        // have failed (e.g. mail transport down) — don't trust the model's
        // own text in that case, it doesn't know the send failed either.
        if (!$this->tracker->sent) {
            return $this->json(
                ['error' => 'Nie udało się wysłać maila — sprawdź czy serwer pocztowy działa.'],
                Response::HTTP_BAD_GATEWAY,
            );
        }

        return $this->json([
            'response' => $result->getContent()
        ]);
    }
}
