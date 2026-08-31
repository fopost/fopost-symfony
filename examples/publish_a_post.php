<?php

/**
 * Create a post and queue it for delivery, with no kernel in the way.
 *
 *     FOPOST_API_KEY=fp_... php examples/publish_a_post.php
 *
 * Inside an application you would inject Fopost\Sdk\Client instead of building
 * one, and the bundle configuration would supply the key.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Fopost\Sdk\Client;
use Fopost\Sdk\Exception\FopostException;

$client = new Client(getenv('FOPOST_API_KEY') ?: null);

try {
    $workspace = $client->workspaces()->list()[0] ?? null;
    if ($workspace === null) {
        exit("No workspace on this key. Create one at https://fopost.com/dashboard\n");
    }

    $accounts = $client->accounts()->list($workspace->id);
    if ($accounts === []) {
        exit("No connected accounts. Connect one at https://fopost.com/dashboard\n");
    }

    $post = $client->posts()->create(
        workspaceId: $workspace->id,
        content: 'Shipping today: scheduled posting straight from Symfony.',
        accounts: $accounts,
    );

    $client->posts()->publish($post->id);

    echo "Queued post {$post->id} for delivery.\n";
} catch (FopostException $e) {
    exit("FoPost said no: {$e->getMessage()}\n");
}
