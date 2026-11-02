<?php

declare(strict_types=1);

namespace App\Controller\Ui\Source;

use App\Entity\Source;
use App\Form\SourceForm;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route(
    '/ui/source/{id}/edit',
    name: 'ui_source_edit',
    requirements: ['id' => Requirement::DIGITS],
    methods: [Request::METHOD_GET, Request::METHOD_POST],
)]
final class EditController extends AbstractController
{
    public function __construct(
        #[Autowire(env: 'string:resolve:APP_DOWNLOADS_DIR')]
        private readonly string $downloadsDir,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[IsGranted('ROLE_ADMIN')]
    public function __invoke(Request $request, Source $source): Response
    {
        $oldFilename = \sprintf('%s/%s', $this->downloadsDir, $source->getFilename());

        $form = $this->createForm(SourceForm::class, $source);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newFileName = \sprintf('%s/%s', $this->downloadsDir, (string) $form->get('filename')->getData());

            if (!\file_exists($oldFilename)) {
                $this->logger->alert('An error occurred while editing the file, file is not exists', [
                    'message' => \sprintf('File not found, not possible to edit file: %s', $oldFilename),
                ]);
                $this->addFlash('error', $this->translator->trans('ui.source.error_edit'));

                return $this->redirectToRoute('ui_source_index', [], Response::HTTP_SEE_OTHER);
            }

            if ($newFileName !== $oldFilename) {
                try {
                    \rename($oldFilename, $newFileName);
                } catch (\Throwable $e) {
                    $this->logger->alert('An error occurred while renaming the file', [
                        'message'     => $e->getMessage(),
                        'source'      => $source,
                        'oldFilename' => $oldFilename,
                        'newFileName' => $newFileName,
                    ]);

                    $this->addFlash('error', $this->translator->trans('ui.source.error_rename'));

                    return $this->redirectToRoute('ui_source_index', [], Response::HTTP_SEE_OTHER);
                }
            }

            $this->entityManager->flush();

            return $this->redirectToRoute('ui_source_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('ui/source/edit.html.twig', [
            'source' => $source,
            'form'   => $form,
        ]);
    }
}
