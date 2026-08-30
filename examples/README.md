# Examples

Drop these into a Symfony application that already has the bundle installed and configured.

| File | What it shows |
| --- | --- |
| `config/packages/fopost.yaml` | The bundle configuration, reading everything from environment variables |
| `config/routes/fopost.yaml` | Importing the webhook route |
| `src/Controller/PublishController.php` | Injecting the client and publishing a post |
| `src/EventListener/PostPublishedListener.php` | Reacting to a webhook with `#[AsEventListener]` |
| `publish_a_post.php` | The same create-then-publish flow, without a kernel |
