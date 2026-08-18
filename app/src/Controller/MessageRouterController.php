<?php

namespace App\Controller;

use App\Dto\RouteMessageRequest;
use Symfony\AI\Agent\AgentInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

class MessageRouterController extends AbstractController
{
    public function __construct(
        private readonly AgentInterface $agent,
    ) {}

    #[Route('/api/v1/messages', name: 'route_message', methods: ['POST'])]
    public function index(#[MapRequestPayload] RouteMessageRequest $payload): JsonResponse
    {
        $result = $this->agent->call($payload->message);

        return $this->json([
            'response' => $result->getContent()
        ]);
    }
}
