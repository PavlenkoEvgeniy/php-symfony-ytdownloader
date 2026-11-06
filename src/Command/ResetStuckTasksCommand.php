<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\QueueResetServiceInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsCommand(
    description: 'Reset processing tasks to queued and redeliver stuck transport messages',
    name: 'app:task:reset-stuck',
)]
final readonly class ResetStuckTasksCommand
{
    public function __construct(
        private QueueResetServiceInterface $queueResetService,
        private TranslatorInterface $translator,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $result = $this->queueResetService->reset();

        $io->success($this->translator->trans('command.reset_stuck.success', [
            '%count%'    => $result['processing'],
            '%messages%' => $result['messages'],
            '%skipped%'  => $result['skipped'],
        ]));

        return Command::SUCCESS;
    }
}
