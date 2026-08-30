<?php

declare(strict_types=1);

namespace App\Controller;

use Fopost\Sdk\Client;
use Fopost\Sdk\Exception\FopostException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/** Type hint the client and the container hands you the configured instance. */
final class PublishController extends AbstractController
{
    #[Route('/publish', name: 'app_publish', methods: ['POST'])]
    public function __invoke(Client $fopost): JsonResponse
    {
        $workspace = $fopost->workspaces()->list()[0];
        $accounts = $fopost->accounts()->list($workspace->id);

        try {
            $post = $fopost->posts()->create(
                workspaceId: $workspace->id,
                content: 'Shipping today: scheduled posting straight from Symfony.',
                accounts: $accounts,
            );

            $fopost->posts()->publish($post->id);
        } catch (FopostException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 502);
        }

        return new JsonResponse(['id' => $post->id, 'status' => 'queued']);
    }
}
