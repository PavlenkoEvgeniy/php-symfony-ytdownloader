<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\PruneAuditEventsCommand;
use App\Repository\AuditEventRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatorInterface;

final class PruneAuditEventsCommandTest extends TestCase
{
    /**
     * Verifies a non-positive retention window is rejected.
     */
    public function testRejectsInvalidRetentionWindow(): void
    {
        $repository = $this->createMock(AuditEventRepositoryInterface::class);
        $repository->expects($this->never())->method('countOlderThan');
        $repository->expects($this->never())->method('deleteOlderThan');

        $output   = new BufferedOutput();
        $exitCode = $this->createCommand($repository)(
            new SymfonyStyle(new ArrayInput([]), $output),
            0,
        );

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Retention window must be at least one day', $output->fetch());
    }

    /**
     * Verifies a dry run counts events without deleting them.
     */
    public function testDryRunCountsEvents(): void
    {
        $repository = $this->createMock(AuditEventRepositoryInterface::class);
        $repository->expects($this->once())->method('countOlderThan')->willReturn(5);
        $repository->expects($this->never())->method('deleteOlderThan');

        $output   = new BufferedOutput();
        $exitCode = $this->createCommand($repository)(
            new SymfonyStyle(new ArrayInput([]), $output),
            30,
            true,
        );

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('5 audit event(s) would be deleted', $output->fetch());
    }

    /**
     * Verifies old events are deleted and the count is reported.
     */
    public function testDeletesOldEvents(): void
    {
        $repository = $this->createMock(AuditEventRepositoryInterface::class);
        $repository->expects($this->once())->method('deleteOlderThan')->willReturn(3);

        $output   = new BufferedOutput();
        $exitCode = $this->createCommand($repository)(
            new SymfonyStyle(new ArrayInput([]), $output),
            30,
        );

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Deleted 3 audit event(s)', $output->fetch());
    }

    private function createCommand(AuditEventRepositoryInterface $repository): PruneAuditEventsCommand
    {
        return new PruneAuditEventsCommand($repository, $this->createTranslator());
    }

    private function createTranslator(): TranslatorInterface
    {
        $translator = new Translator('en');
        $translator->addLoader('yaml', new YamlFileLoader());
        $translator->addResource('yaml', \dirname(__DIR__, 2) . '/translations/messages.en.yaml', 'en');

        return $translator;
    }
}
