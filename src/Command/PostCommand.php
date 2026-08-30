<?php

declare(strict_types=1);

namespace Fopost\Symfony\Command;

use Fopost\Sdk\Client;
use Fopost\Sdk\Exception\FopostException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'fopost:post',
    description: 'Create a post, then schedule it or queue it for delivery',
)]
final class PostCommand extends Command
{
    public function __construct(
        private readonly Client $client,
        private readonly ?string $defaultWorkspaceId = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('content', InputArgument::REQUIRED, 'The text of the post')
            ->addOption(
                'workspace',
                'w',
                InputOption::VALUE_REQUIRED,
                'Workspace id, defaults to the configured default_workspace_id',
            )
            ->addOption(
                'account',
                'a',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Id of an account to post to, repeat for more than one',
            )
            ->addOption('schedule-at', null, InputOption::VALUE_REQUIRED, 'ISO 8601 time to schedule the post for')
            ->addOption('publish', null, InputOption::VALUE_NONE, 'Queue the post for delivery straight away')
            ->setHelp(<<<'HELP'
              Create a draft:

                <info>php %command.full_name% "Shipping today" --account acc_1</info>

              Schedule it:

                <info>php %command.full_name% "Shipping today" -a acc_1 --schedule-at 2026-09-01T09:00:00Z</info>

              Send it now:

                <info>php %command.full_name% "Shipping today" -a acc_1 --publish</info>
              HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $workspace = $input->getOption('workspace') ?? $this->defaultWorkspaceId;
        if (!is_string($workspace) || $workspace === '') {
            $io->error('No workspace given. Pass --workspace, or set fopost.default_workspace_id.');

            return Command::INVALID;
        }

        $scheduleAt = $input->getOption('schedule-at');
        $scheduleAt = is_string($scheduleAt) && $scheduleAt !== '' ? $scheduleAt : null;
        $publish = (bool) $input->getOption('publish');

        if ($scheduleAt !== null && $publish) {
            $io->error('Pass either --schedule-at or --publish, not both.');

            return Command::INVALID;
        }

        /** @var array<int, string> $accounts */
        $accounts = (array) $input->getOption('account');

        try {
            $post = $this->client->posts()->create(
                workspaceId: $workspace,
                content: (string) $input->getArgument('content'),
                accounts: $accounts,
                status: $scheduleAt !== null ? 'scheduled' : 'draft',
                scheduleAt: $scheduleAt,
            );

            if ($publish) {
                $this->client->posts()->publish($post->id);
            }
        } catch (FopostException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success(match (true) {
            $publish => "Post {$post->id} queued for delivery.",
            $scheduleAt !== null => "Post {$post->id} scheduled for {$scheduleAt}.",
            default => "Draft {$post->id} created.",
        });

        return Command::SUCCESS;
    }
}
