<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\AuditEventRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsCommand(
    name: 'app:audit:prune',
    description: 'Delete audit events older than the retention window',
)]
final readonly class PruneAuditEventsCommand
{
    public function __construct(
        private AuditEventRepositoryInterface $auditEventRepository,
        private TranslatorInterface $translator,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Retention window in days', shortcut: 'd')]
        int $days = 180,
        #[Option(description: 'Count events without deleting them')]
        bool $dryRun = false,
    ): int {
        if ($days < 1) {
            $io->error($this->translator->trans('command.audit.invalid_days'));

            return Command::FAILURE;
        }

        $before = new \DateTimeImmutable(\sprintf('-%d days', $days));

        if ($dryRun) {
            $count = $this->auditEventRepository->countOlderThan($before);
            $io->success($this->translator->trans('command.audit.prune_dry_run', ['%count%' => $count]));

            return Command::SUCCESS;
        }

        $deleted = $this->auditEventRepository->deleteOlderThan($before);
        $io->success($this->translator->trans('command.audit.pruned', ['%count%' => $deleted]));

        return Command::SUCCESS;
    }
}
