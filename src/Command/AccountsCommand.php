<?php

declare(strict_types=1);

namespace Fopost\Symfony\Command;

use Fopost\Sdk\Client;
use Fopost\Sdk\Exception\FopostException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'fopost:accounts',
    description: 'List the social accounts connected to a workspace',
)]
final class AccountsCommand extends Command
{
    public function __construct(
        private readonly Client $client,
        private readonly ?string $defaultWorkspaceId = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'workspace',
            'w',
            InputOption::VALUE_REQUIRED,
            'Workspace id, defaults to the configured default_workspace_id',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $workspace = $input->getOption('workspace') ?? $this->defaultWorkspaceId;
        $workspace = is_string($workspace) && $workspace !== '' ? $workspace : null;

        try {
            $accounts = $this->client->accounts()->list($workspace);
        } catch (FopostException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        if ($accounts === []) {
            $io->warning('No connected accounts. Connect one at https://fopost.com/dashboard.');

            return Command::SUCCESS;
        }

        $io->table(
            ['Id', 'Platform', 'Handle', 'Name', 'Health'],
            array_map(static fn ($account): array => [
                $account->id,
                $account->platform,
                $account->username ?? '-',
                $account->name ?? '-',
                $account->healthStatus ?? 'unknown',
            ], $accounts),
        );

        return Command::SUCCESS;
    }
}
