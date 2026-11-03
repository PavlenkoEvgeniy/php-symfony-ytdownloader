<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\PruneLibraryCommand;
use App\Service\LibraryPrunerInterface;
use OskarStark\Twig\ReadableFilesizeExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatorInterface;

final class PruneLibraryCommandTest extends TestCase
{
    /**
     * Verifies a dry run lists old files without deleting them.
     */
    public function testDryRunListsFiles(): void
    {
        $pruner = $this->createMock(LibraryPrunerInterface::class);
        $pruner->expects($this->once())
            ->method('prune')
            ->with($this->isInstanceOf(\DateTimeImmutable::class), true)
            ->willReturn([
                'deleted' => 0,
                'bytes'   => 4,
                'failed'  => 0,
                'files'   => [[
                    'name'      => 'old.mp4',
                    'size'      => 4,
                    'createdAt' => new \DateTimeImmutable('-2 days'),
                ]],
            ]);

        $output = $this->execute($pruner, dryRun: true);

        $this->assertStringContainsString('old.mp4', $output);
        $this->assertStringContainsString('Dry run: 1 library file(s) would be deleted, 4 B would be freed.', $output);
    }

    /**
     * Verifies a dry run without old files reports success.
     */
    public function testDryRunWithoutFilesReportsSuccess(): void
    {
        $pruner = $this->createMock(LibraryPrunerInterface::class);
        $pruner->method('prune')->willReturn(['deleted' => 0, 'bytes' => 0, 'failed' => 0, 'files' => []]);

        $output = $this->execute($pruner, dryRun: true);

        $this->assertStringContainsString('No library files older than 1 day(s) found.', $output);
    }

    /**
     * Verifies a real run reports deleted files and the freed size.
     */
    public function testPruneReportsDeletedFiles(): void
    {
        $pruner = $this->createMock(LibraryPrunerInterface::class);
        $pruner->method('prune')->willReturn(['deleted' => 1, 'bytes' => 1024, 'failed' => 0, 'files' => []]);

        $output = $this->execute($pruner);

        $this->assertStringContainsString('Deleted 1 library file(s), 1 KB freed.', $output);
    }

    /**
     * Verifies a failed unlink returns a failure code.
     */
    public function testPruneReportsFailures(): void
    {
        $pruner = $this->createMock(LibraryPrunerInterface::class);
        $pruner->method('prune')->willReturn(['deleted' => 1, 'bytes' => 0, 'failed' => 1, 'files' => []]);

        $io       = new SymfonyStyle(new ArrayInput([]), new BufferedOutput());
        $exitCode = $this->command($pruner)->__invoke($io);

        $this->assertSame(Command::FAILURE, $exitCode);
    }

    private function execute(LibraryPrunerInterface $pruner, ?string $days = null, bool $dryRun = false): string
    {
        $output = new BufferedOutput();
        $io     = new SymfonyStyle(new ArrayInput([]), $output);

        $exitCode = $this->command($pruner)->__invoke($io, $days, $dryRun);

        $this->assertSame(Command::SUCCESS, $exitCode);

        return $output->fetch();
    }

    private function command(LibraryPrunerInterface $pruner): PruneLibraryCommand
    {
        return new PruneLibraryCommand($pruner, new ReadableFilesizeExtension(), $this->createTranslator(), 1);
    }

    private function createTranslator(): TranslatorInterface
    {
        $translator = new Translator('en');
        $translator->addLoader('yaml', new YamlFileLoader());
        $translator->addResource('yaml', \dirname(__DIR__, 2) . '/translations/messages.en.yaml', 'en');

        return $translator;
    }
}
