<?php

declare(strict_types=1);

namespace App\Controller\Ui\Source;

use App\Entity\Source;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route(
    '/ui/source/{id}',
    name: 'ui_source_show',
    requirements: ['id' => Requirement::DIGITS],
    methods: [Request::METHOD_GET],
)]
final class ShowController extends AbstractController
{
    public function __invoke(Source $source): Response
    {
        return $this->render('ui/source/show.html.twig', [
            'source' => $source,
        ]);
    }
}
