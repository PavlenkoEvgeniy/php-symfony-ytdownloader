<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\LibraryPrunerInterface;
use OskarStark\Twig\ReadableFilesizeExtension;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsCommand(
    description: 'Delete library files older than the retention period',
    name: 'app:library:prune',
)]
final readonly class PruneLibraryCommand
{
    public function __construct(
        private LibraryPrunerInterface $libraryPruner,
        private ReadableFilesizeExtension $readableFilesizeExtension,
        private TranslatorInterface $translator,
        #[Autowire(env: 'int:LIBRARY_RETENTION_DAYS')]
        private int $retentionDays,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Retention in days (defaults to LIBRARY_RETENTION_DAYS)')]
        ?string $days = null,
        #[Option(description: 'List files without deleting them')]
        bool $dryRun = false,
    ): int {
        $days   = null === $days ? $this->retentionDays : \max(1, (int) $days);
        $cutoff = new \DateTimeImmutable(\sprintf('-%d days', $days));

        $result = $this->libraryPruner->prune($cutoff, $dryRun);

        if ($dryRun) {
            if ([] === $result['files']) {
                $io->success($this->translator->trans('command.library_prune.no_files', ['%days%' => $days]));

                return Command::SUCCESS;
            }

            $io->table(
                [
                    $this->translator->trans('command.library_prune.table.file'),
                    $this->translator->trans('command.library_prune.table.size'),
                    $this->translator->trans('command.library_prune.table.created'),
                ],
                \array_map(fn (array $file): array => [
                    $file['name'],
                    $this->readableFilesizeExtension->readableFilesize($file['size']),
                    $file['createdAt']->format('Y-m-d H:i:s'),
                ], $result['files']),
            );

            $io->note($this->translator->trans('command.library_prune.dry_run', [
                '%count%' => \count($result['files']),
                '%size%'  => $this->readableFilesizeExtension->readableFilesize($result['bytes']),
            ]));

            return Command::SUCCESS;
        }

        if (0 === $result['deleted'] && 0 === $result['failed']) {
            $io->success($this->translator->trans('command.library_prune.no_files', ['%days%' => $days]));

            return Command::SUCCESS;
        }

        $io->success($this->translator->trans('command.library_prune.pruned', [
            '%count%' => $result['deleted'],
            '%size%'  => $this->readableFilesizeExtension->readableFilesize($result['bytes']),
        ]));

        if ($result['failed'] > 0) {
            $io->error($this->translator->trans('command.library_prune.failed', ['%count%' => $result['failed']]));

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
