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
        description: 'AI model is not ready yet (e.g. still being downloaded)',
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
                ['error' => 'Model AI nie jest jeszcze gotowy (prawdopodobnie wciąż się pobiera). Spróbuj ponownie za chwilę.'],
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

            return $this->json([
                'response' => 'Nie udało się jednoznacznie sklasyfikować zgłoszenia — przekazano do other@example.com.',
            ]);
        }

        return $this->json([
            'response' => $result->getContent()
        ]);
    }
}
