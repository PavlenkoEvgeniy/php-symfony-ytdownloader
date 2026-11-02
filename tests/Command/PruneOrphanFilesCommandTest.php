<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\PruneOrphanFilesCommand;
use App\Service\OrphanFilePrunerInterface;
use OskarStark\Twig\ReadableFilesizeExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatorInterface;

final class PruneOrphanFilesCommandTest extends TestCase
{
    /**
     * Verifies dry run lists orphans without deleting them.
     */
    public function testDryRunListsOrphans(): void
    {
        $pruner = $this->createMock(OrphanFilePrunerInterface::class);
        $pruner->expects($this->once())
            ->method('findOrphans')
            ->willReturn([[
                'name'       => 'orphan.part',
                'path'       => '/tmp/orphan.part',
                'size'       => 12,
                'modifiedAt' => new \DateTimeImmutable(),
            ]]);
        $pruner->expects($this->never())->method('prune');

        $output = $this->execute($pruner, dryRun: true);

        $this->assertStringContainsString('Dry run: 1 orphan file(s) would be deleted.', $output);
    }

    /**
     * Verifies a run without orphans reports success.
     */
    public function testNoOrphansReportsSuccess(): void
    {
        $pruner = $this->createMock(OrphanFilePrunerInterface::class);
        $pruner->method('prune')->willReturn(['deleted' => 0, 'bytes' => 0, 'failed' => 0]);

        $output = $this->execute($pruner);

        $this->assertStringContainsString('No orphan files found.', $output);
    }

    /**
     * Verifies a successful prune reports the freed size.
     */
    public function testPruneReportsDeletedFiles(): void
    {
        $pruner = $this->createMock(OrphanFilePrunerInterface::class);
        $pruner->method('prune')->willReturn(['deleted' => 1, 'bytes' => 1024, 'failed' => 0]);

        $output = $this->execute($pruner);

        $this->assertStringContainsString('Pruned 1 orphan file(s), 1 KB freed.', $output);
    }

    /**
     * Verifies a failed file removal is reported and returns a failure code.
     */
    public function testPruneReportsFailures(): void
    {
        $pruner = $this->createMock(OrphanFilePrunerInterface::class);
        $pruner->method('prune')->willReturn(['deleted' => 1, 'bytes' => 0, 'failed' => 1]);

        $io       = new SymfonyStyle(new ArrayInput([]), new BufferedOutput());
        $exitCode = (new PruneOrphanFilesCommand($pruner, new ReadableFilesizeExtension(), $this->createTranslator()))->__invoke($io);

        $this->assertSame(Command::FAILURE, $exitCode);
    }

    private function execute(OrphanFilePrunerInterface $pruner, bool $dryRun = false): string
    {
        $output = new BufferedOutput();
        $io     = new SymfonyStyle(new ArrayInput([]), $output);

        $exitCode = (new PruneOrphanFilesCommand($pruner, new ReadableFilesizeExtension(), $this->createTranslator()))->__invoke($io, $dryRun);

        $this->assertSame(Command::SUCCESS, $exitCode);

        return $output->fetch();
    }

    private function createTranslator(): TranslatorInterface
    {
        $translator = new Translator('en');
        $translator->addLoader('yaml', new YamlFileLoader());
        $translator->addResource('yaml', \dirname(__DIR__, 2) . '/translations/messages.en.yaml', 'en');

        return $translator;
    }
}
