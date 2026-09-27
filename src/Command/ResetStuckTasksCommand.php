<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\QueueResetService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:task:reset-stuck',
    description: 'Reset processing tasks to queued and redeliver stuck transport messages',
)]
final class ResetStuckTasksCommand extends Command
{
    public function __construct(
        private readonly QueueResetService $queueResetService,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $result = $this->queueResetService->reset();

        $io->success(\sprintf(
            'Reset %d processing task(s) to queued and made %d stuck message(s) deliverable again.',
            $result['processing'],
            $result['messages'],
        ));

        return Command::SUCCESS;
    }
}
