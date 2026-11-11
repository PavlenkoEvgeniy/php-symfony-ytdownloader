<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use App\Service\DownloadIntakePolicyInterface;
use App\Twig\WebIntakeExtension;
use PHPUnit\Framework\TestCase;

final class WebIntakeExtensionTest extends TestCase
{
    /**
     * Verifies the extension exposes the web_intake_enabled Twig function.
     */
    public function testGetFunctionsExposesWebIntakeEnabled(): void
    {
        $extension = new WebIntakeExtension($this->createStub(DownloadIntakePolicyInterface::class));

        $functions = $extension->getFunctions();

        $this->assertCount(1, $functions);
        $this->assertSame('web_intake_enabled', $functions[0]->getName());
    }

    /**
     * Verifies the helper answers through the intake policy.
     */
    public function testIsWebIntakeEnabledDelegatesToThePolicy(): void
    {
        $policy = $this->createMock(DownloadIntakePolicyInterface::class);
        $policy->expects($this->once())
            ->method('isWebIntakeEnabled')
            ->willReturn(false);

        $this->assertFalse(new WebIntakeExtension($policy)->isWebIntakeEnabled());
    }

    /**
     * Verifies the callable registered for the template function works too.
     */
    public function testRegisteredCallableAnswersThroughThePolicy(): void
    {
        $policy = $this->createStub(DownloadIntakePolicyInterface::class);
        $policy->method('isWebIntakeEnabled')->willReturn(true);

        $callable = new WebIntakeExtension($policy)->getFunctions()[0]->getCallable();
        $this->assertIsCallable($callable);

        $this->assertTrue($callable());
    }
}
