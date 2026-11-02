<?php

declare(strict_types=1);

namespace App\Tests\Form;

use App\Entity\Source;
use App\Form\SourceForm;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Validator\Validation;

final class SourceFormTest extends TypeTestCase
{
    /**
     * Verifies the form exposes the editable source fields.
     */
    public function testFormHasExpectedFields(): void
    {
        $form = $this->factory->create(SourceForm::class);

        $this->assertTrue($form->has('filename'));
        $this->assertTrue($form->has('filepath'));
        $this->assertTrue($form->has('description'));
    }

    /**
     * Verifies the fields use translated labels and expected attributes.
     */
    public function testFormFieldOptions(): void
    {
        $form = $this->factory->create(SourceForm::class);

        $this->assertSame('Filename', $form->get('filename')->getConfig()->getOption('label'));
        $this->assertSame('Filepath', $form->get('filepath')->getConfig()->getOption('label'));
        $this->assertSame('Description', $form->get('description')->getConfig()->getOption('label'));
        $this->assertTrue($form->get('filepath')->getConfig()->getOption('attr')['readonly']);
        $this->assertSame(5, $form->get('description')->getConfig()->getOption('attr')['rows']);
    }

    /**
     * Verifies valid data is mapped to the source entity.
     */
    public function testFormSubmitsValidData(): void
    {
        $form = $this->factory->create(SourceForm::class);

        $form->submit([
            'filename'    => 'renamed-video.mp4',
            'filepath'    => '/var/downloads',
            'description' => 'Renamed video',
        ]);

        $this->assertTrue($form->isValid());

        $source = $form->getData();
        $this->assertInstanceOf(Source::class, $source);
        $this->assertSame('renamed-video.mp4', $source->getFilename());
        $this->assertSame('/var/downloads', $source->getFilepath());
        $this->assertSame('Renamed video', $source->getDescription());
    }

    /**
     * Verifies a filename shorter than the minimum is rejected.
     */
    public function testFormRejectsTooShortFilename(): void
    {
        $form = $this->factory->create(SourceForm::class, new Source());

        $form->submit([
            'filename'    => 'abc',
            'filepath'    => '/var/downloads',
            'description' => '',
        ]);

        $this->assertFalse($form->isValid());
    }

    /**
     * Verifies a blank filename is rejected.
     */
    public function testFormRejectsBlankFilename(): void
    {
        $form = $this->factory->create(SourceForm::class, new Source());

        $form->submit([
            'filename'    => '   ',
            'filepath'    => '/var/downloads',
            'description' => '',
        ]);

        $this->assertFalse($form->isValid());
    }

    /**
     * Verifies the description is optional.
     */
    public function testDescriptionIsOptional(): void
    {
        $form = $this->factory->create(SourceForm::class, new Source());

        $form->submit([
            'filename' => 'valid-name.mp4',
            'filepath' => '/var/downloads',
        ]);

        $this->assertTrue($form->isValid());
    }

    protected function getTypes(): array
    {
        $translator = new Translator('en');
        $translator->addLoader('yaml', new YamlFileLoader());
        $translator->addResource('yaml', \dirname(__DIR__, 2) . '/translations/messages.en.yaml', 'en');

        return [new SourceForm($translator)];
    }

    protected function getExtensions(): array
    {
        return [new ValidatorExtension(Validation::createValidator())];
    }
}
