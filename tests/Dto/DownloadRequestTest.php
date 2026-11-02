<?php

declare(strict_types=1);

namespace App\Tests\Dto;

use App\Dto\DownloadRequest;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class DownloadRequestTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator()
        ;
    }

    /**
     * Verifies a missing URL is rejected.
     */
    public function testBlankUrlIsRejected(): void
    {
        $violations = $this->validator->validate(new DownloadRequest());

        $this->assertCount(1, $violations);
        $this->assertSame('This value should not be blank.', $violations[0]->getMessage());
    }

    /**
     * Verifies a malformed URL is rejected.
     */
    public function testInvalidUrlIsRejected(): void
    {
        $violations = $this->validator->validate(new DownloadRequest('not-a-url'));

        $this->assertCount(1, $violations);
        $this->assertSame('This value is not a valid URL.', $violations[0]->getMessage());
    }

    /**
     * Verifies an unsupported quality is rejected.
     */
    public function testInvalidQualityIsRejected(): void
    {
        $violations = $this->validator->validate(new DownloadRequest('https://test.local', 'ultra'));

        $this->assertCount(1, $violations);
        $this->assertSame('The value you selected is not a valid choice.', $violations[0]->getMessage());
    }

    /**
     * Verifies the default and explicit valid payloads pass validation.
     */
    public function testValidPayloadPasses(): void
    {
        $this->assertCount(0, $this->validator->validate(new DownloadRequest('https://test.local')));
        $this->assertCount(0, $this->validator->validate(new DownloadRequest('https://test.local', 'audio')));
    }
}
