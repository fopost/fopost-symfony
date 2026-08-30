<?php

declare(strict_types=1);

namespace Fopost\Symfony\Tests;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class CommandTest extends TestCase
{
    public function testFopostPostCreatesADraftAgainstTheStubbedClient(): void
    {
        $tester = $this->tester('fopost:post');
        $this->transport()->push(201, ['data' => ['id' => 'p_1', 'status' => 'draft']]);

        $status = $tester->execute(['content' => 'Shipping today', '--account' => ['acc_1', 'acc_2']]);

        $this->assertSame(Command::SUCCESS, $status);
        $this->assertStringContainsString('Draft p_1 created', $tester->getDisplay());

        $sent = $this->transport()->last();
        $this->assertSame('POST', $sent['method']);
        $this->assertStringEndsWith('/api/v1/posts', $sent['url']);
        $this->assertSame([
            'workspace_id' => 'ws_default',
            'status' => 'draft',
            'content' => [['text' => 'Shipping today']],
            'accounts' => ['acc_1', 'acc_2'],
        ], $this->transport()->lastJson());
    }

    public function testFopostPostPublishesWhenAsked(): void
    {
        $tester = $this->tester('fopost:post');
        $this->transport()->push(201, ['data' => ['id' => 'p_2', 'status' => 'draft']]);
        $this->transport()->push(200, ['data' => ['queued' => true]]);

        $status = $tester->execute(['content' => 'Live now', '--account' => ['acc_1'], '--publish' => true]);

        $this->assertSame(Command::SUCCESS, $status);
        $this->assertStringContainsString('queued for delivery', $tester->getDisplay());
        $this->assertStringEndsWith('/api/v1/posts/p_2/publish', $this->transport()->last()['url']);
    }

    public function testFopostPostSchedulesWhenGivenATime(): void
    {
        $tester = $this->tester('fopost:post');
        $this->transport()->push(201, ['data' => ['id' => 'p_3', 'status' => 'scheduled']]);

        $status = $tester->execute([
            'content' => 'Later',
            '--account' => ['acc_1'],
            '--schedule-at' => '2026-09-01T09:00:00Z',
        ]);

        $this->assertSame(Command::SUCCESS, $status);
        $body = $this->transport()->lastJson();
        $this->assertSame('scheduled', $body['status']);
        $this->assertSame('2026-09-01T09:00:00Z', $body['schedule_at']);
    }

    public function testFopostPostNeedsAWorkspace(): void
    {
        $tester = $this->tester('fopost:post', withDefaultWorkspace: false);

        $status = $tester->execute(['content' => 'Nowhere to go']);

        $this->assertSame(Command::INVALID, $status);
        $this->assertStringContainsString('No workspace given', $tester->getDisplay());
    }

    public function testFopostAccountsTabulatesConnectedAccounts(): void
    {
        $tester = $this->tester('fopost:accounts');
        $this->transport()->push(200, ['data' => [
            ['id' => 'acc_1', 'platform' => 'x', 'username' => 'yourbrand', 'health_status' => 'healthy'],
            ['id' => 'acc_2', 'platform' => 'linkedin', 'name' => 'Your Brand'],
        ]]);

        $status = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $status);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('acc_1', $display);
        $this->assertStringContainsString('linkedin', $display);
        $this->assertStringContainsString('workspaceId=ws_default', $this->transport()->last()['url']);
    }

    private function tester(string $name, bool $withDefaultWorkspace = true): CommandTester
    {
        $config = ['api_key' => 'fp_test'];
        if ($withDefaultWorkspace) {
            $config['default_workspace_id'] = 'ws_default';
        }
        self::bootKernel(['fopost' => $config]);

        $application = new Application(self::$kernel);
        $application->setAutoExit(false);

        return new CommandTester($application->find($name));
    }
}
