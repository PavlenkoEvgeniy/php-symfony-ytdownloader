<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\OrphanFilePruner;
use OskarStark\Twig\ReadableFilesizeExtension;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:downloads:prune',
    description: 'Delete orphan files in the downloads directory',
)]
final class PruneOrphanFilesCommand extends Command
{
    public function __construct(
        private readonly OrphanFilePruner $pruner,
        private readonly ReadableFilesizeExtension $filesizeExtension,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'List orphan files without deleting them');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io       = new SymfonyStyle($input, $output);
        $isDryRun = (bool) $input->getOption('dry-run');

        if ($isDryRun) {
            return $this->listOrphans($io);
        }

        $result = $this->pruner->prune();

        if (0 === $result['deleted'] && 0 === $result['failed']) {
            $io->success('No orphan files found.');

            return Command::SUCCESS;
        }

        $io->success(\sprintf(
            'Pruned %d orphan file(s), %s freed.',
            $result['deleted'],
            $this->filesizeExtension->readableFilesize($result['bytes']),
        ));

        if ($result['failed'] > 0) {
            $io->error(\sprintf('Failed to delete %d file(s).', $result['failed']));

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function listOrphans(SymfonyStyle $io): int
    {
        $orphans = $this->pruner->findOrphans();

        if ([] === $orphans) {
            $io->success('No orphan files found.');

            return Command::SUCCESS;
        }

        $io->table(
            ['File', 'Size', 'Modified'],
            \array_map(static fn (array $orphan): array => [
                $orphan['name'],
                $orphan['size'],
                $orphan['modifiedAt']->format('Y-m-d H:i:s'),
            ], $orphans),
        );

        $io->note(\sprintf('Dry run: %d orphan file(s) would be deleted.', \count($orphans)));

        return Command::SUCCESS;
    }
}
