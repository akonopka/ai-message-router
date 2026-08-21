<?php

namespace App\Controller;

use App\Dto\RouteMessageRequest;
use OpenApi\Attributes as OA;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Exception\ExceptionInterface as AgentExceptionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

class MessageRouterController extends AbstractController
{
    public function __construct(
        private readonly AgentInterface $agent,
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
        description: 'AI model is not ready yet (e.g. still being downloaded)'
    )]
    public function index(#[MapRequestPayload] RouteMessageRequest $payload): JsonResponse
    {
        try {
            $result = $this->agent->call($payload->message);
        } catch (AgentExceptionInterface $e) {
            return $this->json(
                ['error' => 'Model AI nie jest jeszcze gotowy (prawdopodobnie wciąż się pobiera). Spróbuj ponownie za chwilę.'],
                Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }

        return $this->json([
            'response' => $result->getContent()
        ]);
    }
}
