<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\OrphanFilePrunerInterface;
use OskarStark\Twig\ReadableFilesizeExtension;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsCommand(
    description: 'Delete orphan files in the downloads directory',
    name: 'app:downloads:prune',
)]
final readonly class PruneOrphanFilesCommand
{
    public function __construct(
        private OrphanFilePrunerInterface $orphanFilePruner,
        private ReadableFilesizeExtension $readableFilesizeExtension,
        private TranslatorInterface $translator,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'List orphan files without deleting them')]
        bool $dryRun = false,
    ): int {
        if ($dryRun) {
            return $this->listOrphans($io);
        }

        $result = $this->orphanFilePruner->prune();

        if (0 === $result['deleted'] && 0 === $result['failed']) {
            $io->success($this->translator->trans('command.prune.no_orphans'));

            return Command::SUCCESS;
        }

        $io->success($this->translator->trans('command.prune.pruned', [
            '%count%' => $result['deleted'],
            '%size%'  => $this->readableFilesizeExtension->readableFilesize($result['bytes']),
        ]));

        if ($result['failed'] > 0) {
            $io->error($this->translator->trans('command.prune.failed', ['%count%' => $result['failed']]));

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function listOrphans(SymfonyStyle $io): int
    {
        $orphans = $this->orphanFilePruner->findOrphans();

        if ([] === $orphans) {
            $io->success($this->translator->trans('command.prune.no_orphans'));

            return Command::SUCCESS;
        }

        $io->table(
            [
                $this->translator->trans('command.prune.table.file'),
                $this->translator->trans('command.prune.table.size'),
                $this->translator->trans('command.prune.table.modified'),
            ],
            \array_map(static fn (array $orphan): array => [
                $orphan['name'],
                $orphan['size'],
                $orphan['modifiedAt']->format('Y-m-d H:i:s'),
            ], $orphans),
        );

        $io->note($this->translator->trans('command.prune.dry_run', ['%count%' => \count($orphans)]));

        return Command::SUCCESS;
    }
}
