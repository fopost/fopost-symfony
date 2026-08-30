# CLAUDE.md

Guidance for Claude Code (claude.ai/code) when working in this repository.

## What This Is

`fopost/symfony-bundle` on Packagist, namespace `Fopost\Symfony\`. A **thin** Symfony bundle around
[`fopost/sdk`](https://github.com/fopost/fopost-php), the official PHP SDK for the FoPost Cloud API.

The SDK owns everything about the API: HTTP, auth headers, retries, the `{"data": ...}` envelope, error
types, and the models. This repository owns only the Symfony wiring — a configuration tree, one client
service, two console commands, and a webhook endpoint that becomes Symfony events.

**Never reimplement API logic here.** If a change needs a new endpoint, a new model, a different retry
rule, or better error mapping, it belongs in `fopost-php`, not in this bundle. A pull request here that
adds a `curl` call, a JSON envelope, or an exception class is in the wrong repository.

## Brand Rules

- The product is **FoPost** (`fopost.com`). Never write "OwlStack" — retired Aug 2026.
- Never write an email address. Support is https://fopost.com/contact and GitHub issues.
- Never name AI providers/models, infrastructure vendors, or any person. The author is Porter Bridge, LLC.

## Parent dependency

`fopost/sdk` is **not on Packagist yet**. `composer.json` still declares the normal released coordinate
(`"fopost/sdk": "^0.1"`) because that is what ships. To make a build resolve today, point Composer at
the parent's repository first:

```bash
composer config repositories.parent vcs https://github.com/fopost/fopost-php
composer install
```

Both workflows in `.github/workflows/` run that step before installing. **Do not commit a `repositories`
entry into `composer.json`** — the committed manifest stays clean, and the shim lives in CI only.

For local work you may instead point at the sibling checkout, which is faster and picks up unreleased
parent changes:

```bash
composer config repositories.parent path ../fopost-php
```

Either way, revert `composer.json` before committing (`git checkout composer.json`). A tidier option
that never touches the manifest is a throwaway `COMPOSER_HOME` holding a `config.json` with the same
`repositories` block — global repositories are merged into every project.

**Delete both CI shim steps once `fopost/sdk` is published to Packagist.**

## Architecture

```
src/
  FopostBundle.php            AbstractBundle: the config tree + all service wiring
  Command/
    PostCommand.php           fopost:post — create, schedule, publish
    AccountsCommand.php       fopost:accounts — list connected accounts
  Controller/
    WebhookController.php     POST endpoint, verifies then dispatches
  Event/
    FopostWebhookEvent.php    base event, plus one final subclass per API event
  Webhook/
    SignatureVerifier.php     HMAC-SHA256 over the raw body
    WebhookEvents.php         event name -> event class
config/routes/webhooks.yaml   the route users import
tests/                        PHPUnit, KernelTestCase against tests/TestKernel.php
examples/                     a Symfony-flavoured example app fragment
```

`FopostBundle` uses the modern single-file bundle style (`AbstractBundle`, Symfony 6.1+): `configure()`
builds the config tree, `loadExtension()` registers services. There is no `DependencyInjection/` folder
and no XML/YAML service file, on purpose — do not add one.

Service wiring, all private, with autowiring aliases:

| Id | Notes |
| --- | --- |
| `fopost.client` | `Fopost\Sdk\Client`, built from the config keys. `autowire(false)`, named arguments |
| `Fopost\Sdk\Client` | alias to `fopost.client` — this is what makes type hinting work |
| `Fopost\Symfony\Webhook\SignatureVerifier` | `$secret` from `%fopost.webhook_secret%` |
| `Fopost\Symfony\Controller\WebhookController` | tagged `controller.service_arguments` (the tag also makes it public) |
| `Fopost\Symfony\Command\*` | `console.command` comes from `#[AsCommand]` through autoconfiguration |

Every configuration key also becomes a container parameter: `fopost.api_key`, `fopost.base_url`,
`fopost.timeout`, `fopost.max_retries`, `fopost.default_workspace_id`, `fopost.webhook_secret`.

**Env placeholders must keep working.** Values go into the container untouched (`param('fopost.timeout')`,
never `(float) $config['timeout']`), so `%env(float:FOPOST_API_TIMEOUT)%` resolves at runtime. Casting in
`loadExtension()` breaks that and is the easiest mistake to make here.

## API Contract

Handled entirely by `fopost/sdk`, repeated here only so a change is recognisable as belonging upstream:

- Base URL `https://api.fopost.com/v1`; a host with no path gets `/v1` appended
- Auth header `X-API-Key`, not `Bearer`
- Retries on 429, honouring `Retry-After`, capped at 60s
- Success envelope `{"data": ...}`; errors `{"error": "<code>", "message": "<text>"}`

## Webhook signature scheme

Determined from the API source (`apps/api/src/services/webhook-dispatcher.ts` and
`apps/api/src/workers/webhook.worker.ts`), not guessed:

- Body: `{"event": "<name>", "data": {...}, "timestamp": "<ISO 8601>"}`, sent as `POST`
- `X-FoPost-Signature: sha256=<hex>` where `<hex>` is `HMAC-SHA256(raw body, webhook secret)`
- `X-FoPost-Event: <event name>` and `X-FoPost-Delivery: <delivery id>`
- The secret is 32 random bytes, hex encoded, shown once when the webhook is created
- Delivery retries 5 times with exponential backoff; 10 consecutive failures disable the webhook

`SignatureVerifier` compares with `hash_equals` and **fails closed**: no `webhook_secret` configured
means every delivery is rejected. It signs the bytes `Request::getContent()` returns, so nothing may
re-encode the body before verification.

Events the API sends are listed in `WebhookEvents`. An unknown event name falls back to the base
`FopostWebhookEvent`, so a new API event still reaches listeners without a release here.

## Commands

```bash
composer install            # after the parent-dependency shim above
composer test               # or ./vendor/bin/phpunit
composer lint               # or ./vendor/bin/phpcs
./vendor/bin/phpcbf         # fix what phpcs can fix
```

Tests are fully offline. `tests/TestKernel.php` boots FrameworkBundle plus FopostBundle and replaces the
SDK's `$transport` argument with `tests/FakeTransport.php` through a compiler pass, so nothing reaches
the network. Anything new that talks to the API gets covered the same way.

## Conventions

- PSR-12, 120 character lines, enforced by `phpcs.xml` (the parent's ruleset)
- `declare(strict_types=1)` in every file; `final` unless a class is meant to be extended
- Short comments, only where a "why" is not obvious. No narrated docblocks over obvious code
- Config keys are snake_case (`max_retries`), the SDK's constructor arguments are camelCase (`$maxRetries`)

## Releasing

The first release is `v0.1.0`. There is no `version` field in `composer.json` on purpose — Packagist
reads git tags, and a hardcoded version drifts.

Tag `v<version>`; `.github/workflows/release.yml` validates the manifest, runs the suite, and creates the
GitHub Release. **Packagist publishes from the tag through its GitHub webhook, so there is no publish step
and no repository secret to configure** — someone connects the repository on Packagist once, and every
later tag is picked up automatically. `GITHUB_TOKEN` is the only credential, and Actions supplies it.

## Follow-ups

- **Symfony Flex recipe.** A recipe would add `config/packages/fopost.yaml`, `config/routes/fopost.yaml`,
  and the `.env` keys on `composer require`. Recipes live in the `symfony/recipes-contrib` repository,
  never in the package, so this repo only ships the sample YAML in the README and `examples/`.

## Git

Conventional Commits, atomic. Branch `feature/<description>`, merge to `main` via PR.
Never `gh pr create` — push the branch and hand over the compare link.
