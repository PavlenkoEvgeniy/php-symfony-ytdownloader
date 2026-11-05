<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\DownloadIntakePolicyInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Exposes the Web Download Intake switch to templates that hide entry points
 * when it is disabled. The value is read lazily, only where it is used.
 */
final class WebIntakeExtension extends AbstractExtension
{
    public function __construct(
        private readonly DownloadIntakePolicyInterface $intakePolicy,
    ) {
    }

    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('web_intake_enabled', $this->isWebIntakeEnabled(...)),
        ];
    }

    public function isWebIntakeEnabled(): bool
    {
        return $this->intakePolicy->isWebIntakeEnabled();
    }
}
