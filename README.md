# FoPost for Symfony

[![Packagist](https://img.shields.io/packagist/v/fopost/symfony-bundle.svg)](https://packagist.org/packages/fopost/symfony-bundle)
[![Downloads](https://img.shields.io/packagist/dt/fopost/symfony-bundle.svg)](https://packagist.org/packages/fopost/symfony-bundle)
[![CI](https://img.shields.io/github/actions/workflow/status/fopost/fopost-symfony/ci.yml?branch=main&label=ci)](https://github.com/fopost/fopost-symfony/actions)
[![License](https://img.shields.io/packagist/l/fopost/symfony-bundle.svg)](https://github.com/fopost/fopost-symfony/blob/main/LICENSE)

Official Symfony bundle for the FoPost API. Schedule and publish to +30 social platforms from your code.

This bundle is a thin wrapper around [`fopost/sdk`](https://github.com/fopost/fopost-php). Every request,
retry, model, and error type lives there; the bundle wires it into Symfony — one configured client for
autowiring, two console commands, and a webhook endpoint that turns deliveries into Symfony events.

## Requirements

- PHP 8.1 or newer
- Symfony 6.4 LTS or 7.x
- A FoPost API key from [app.fopost.com/api-keys](https://app.fopost.com/api-keys)

## Installation

```bash
composer require fopost/symfony-bundle
```

With Symfony Flex the bundle registers itself. Without it, add it to `config/bundles.php`:

```php
return [
    // ...
    Fopost\Symfony\FopostBundle::class => ['all' => true],
];
```

## Configuration

`config/packages/fopost.yaml`:

```yaml
fopost:
    api_key: '%env(FOPOST_API_KEY)%'
    base_url: '%env(FOPOST_API_URL)%'
    timeout: '%env(float:FOPOST_API_TIMEOUT)%'
    max_retries: '%env(int:FOPOST_API_MAX_RETRIES)%'
    # Optional: an empty fallback leaves them null.
    default_workspace_id: '%env(default::FOPOST_WORKSPACE_ID)%'
    webhook_secret: '%env(default::FOPOST_WEBHOOK_SECRET)%'
```

`.env`:

```dotenv
FOPOST_API_KEY=fp_your_key_here
FOPOST_API_URL=https://api.fopost.com
FOPOST_API_TIMEOUT=30
FOPOST_API_MAX_RETRIES=3
```

Every key takes a literal too, so `api_key: 'fp_...'` works while you are trying things out. Only
`api_key` is required.

| Key | Default | What it does |
| --- | --- | --- |
| `api_key` | none, required | Your API key. Sent as `X-API-Key` |
| `base_url` | `https://api.fopost.com/v1` | API root. A host with no path gets the versioned path appended |
| `timeout` | `30.0` | Seconds to wait for one request |
| `max_retries` | `3` | Attempts a rate limited request gets |
| `default_workspace_id` | `null` | Workspace the console commands use when `--workspace` is left off |
| `webhook_secret` | `null` | Secret shown once when you created the webhook. Without it every delivery is rejected |

## Injecting the client

The bundle registers `Fopost\Sdk\Client` for autowiring, so type hint it anywhere:

```php
use Fopost\Sdk\Client;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class PublishController extends AbstractController
{
    #[Route('/publish', methods: ['POST'])]
    public function __invoke(Client $fopost): JsonResponse
    {
        $workspace = $fopost->workspaces()->list()[0];
        $accounts = $fopost->accounts()->list($workspace->id);

        $post = $fopost->posts()->create(
            workspaceId: $workspace->id,
            content: 'Shipping today: scheduled posting straight from Symfony.',
            accounts: $accounts,
            status: 'scheduled',
            scheduleAt: new \DateTimeImmutable('+1 hour'),
        );

        return new JsonResponse(['id' => $post->id]);
    }
}
```

`$fopost->posts()`, `accounts()`, `workspaces()`, `labels()`, `ai()`, and the `request()` escape hatch
are all documented in [`fopost/sdk`](https://github.com/fopost/fopost-php) — that is the full API
surface, and the bundle adds nothing to it.

## Console commands

```bash
# List the accounts connected to a workspace
php bin/console fopost:accounts --workspace ws_123

# Create a draft
php bin/console fopost:post "Shipping today" -a acc_1 -a acc_2

# Schedule it
php bin/console fopost:post "Shipping today" -a acc_1 --schedule-at 2026-09-01T09:00:00Z

# Send it now
php bin/console fopost:post "Shipping today" -a acc_1 --publish
```

Both commands fall back to `default_workspace_id` when `--workspace` is left off.

## Receiving webhooks

Import the route the bundle ships. `config/routes/fopost.yaml`:

```yaml
fopost:
    resource: '@FopostBundle/config/routes/webhooks.yaml'
```

That mounts `POST /fopost/webhook`. Create a webhook in FoPost pointing at it, copy the secret it shows
once into `FOPOST_WEBHOOK_SECRET`, and you are done: the controller verifies the HMAC-SHA256 signature
over the raw request body, answers `401` when it does not match, and dispatches a Symfony event when it
does.

```php
use Fopost\Symfony\Event\FopostPostPublishedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final class PostPublishedListener
{
    #[AsEventListener]
    public function __invoke(FopostPostPublishedEvent $event): void
    {
        // $event->event      'post.published'
        // $event->data       the event body
        // $event->timestamp  when the API sent it
        // $event->deliveryId the X-FoPost-Delivery header
    }
}
```

One class per event, all extending `FopostWebhookEvent`:

| Event | Class |
| --- | --- |
| `post.published` | `FopostPostPublishedEvent` |
| `post.failed` | `FopostPostFailedEvent` |
| `post.partially_failed` | `FopostPostPartiallyFailedEvent` |
| `delivery.published` | `FopostDeliveryPublishedEvent` |
| `delivery.failed` | `FopostDeliveryFailedEvent` |
| `delivery.delayed` | `FopostDeliveryDelayedEvent` |
| `account.health_changed` | `FopostAccountHealthChangedEvent` |

Every delivery is dispatched twice — once under its own class, once under `FopostWebhookEvent` — so
listening on the base class catches everything, including an event this bundle does not know yet.

Listeners run inside the request, so keep them quick or hand the work to Messenger.

## Errors

Every failure is a `Fopost\Sdk\Exception\FopostException` subclass, so one catch covers the lot:

```php
use Fopost\Sdk\Exception\FopostException;
use Fopost\Sdk\Exception\RateLimitException;
use Fopost\Sdk\Exception\ValidationException;

try {
    $fopost->posts()->publish($postId);
} catch (ValidationException $e) {
    // 400 and 422
} catch (RateLimitException $e) {
    // retry after $e->retryAfter seconds
} catch (FopostException $e) {
    // everything else
}
```

Rate limited requests are retried for you, honouring `Retry-After`.

## Testing your app

Swap the transport and nothing touches the network:

```php
use Fopost\Sdk\Http\Transport;

// In config/services_test.yaml, or a compiler pass:
$container->getDefinition('fopost.client')->setArgument('$transport', new Reference(MyFakeTransport::class));
```

`tests/TestKernel.php` in this repository does exactly that.

## Looking for the free self-hosted toolkit?

This bundle talks to the FoPost Cloud API with a FoPost API key. If you want to publish straight to the
social platforms using your own app credentials, with no FoPost account involved, use
[`fopost/social-core`](https://github.com/fopost/fopost-social-core) instead. The two families are
separate on purpose and never depend on each other.

## Links

- Documentation: [fopost.com/docs](https://fopost.com/docs)
- API keys: [app.fopost.com/api-keys](https://app.fopost.com/api-keys)
- Issues: [github.com/fopost/fopost-symfony/issues](https://github.com/fopost/fopost-symfony/issues)
- Support: [fopost.com/contact](https://fopost.com/contact)

## License

MIT. Copyright Porter Bridge, LLC. See [LICENSE](LICENSE).
