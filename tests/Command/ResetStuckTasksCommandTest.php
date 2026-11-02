<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\ResetStuckTasksCommand;
use App\Service\QueueResetServiceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ResetStuckTasksCommandTest extends TestCase
{
    /**
     * Verifies the reset summary reports both task and message counts.
     */
    public function testResetReportsCounts(): void
    {
        $service = $this->createMock(QueueResetServiceInterface::class);
        $service->expects($this->once())
            ->method('reset')
            ->willReturn(['processing' => 2, 'messages' => 3]);

        $output = new BufferedOutput();
        $io     = new SymfonyStyle(new ArrayInput([]), $output);

        $exitCode = (new ResetStuckTasksCommand($service, $this->createTranslator()))->__invoke($io);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Reset 2 processing task(s)', $output->fetch());
    }

    private function createTranslator(): TranslatorInterface
    {
        $translator = new Translator('en');
        $translator->addLoader('yaml', new YamlFileLoader());
        $translator->addResource('yaml', \dirname(__DIR__, 2) . '/translations/messages.en.yaml', 'en');

        return $translator;
    }
}
