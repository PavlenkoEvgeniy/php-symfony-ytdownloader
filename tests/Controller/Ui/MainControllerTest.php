<?php

declare(strict_types=1);

namespace App\Tests\Controller\Ui;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class MainControllerTest extends WebTestCase
{
    /**
     * Verifies the root route permanently redirects to the download page.
     */
    public function testRootRedirectsToDownloadPage(): void
    {
        $client = static::createClient();

        $client->request(Request::METHOD_GET, '/');

        $this->assertResponseStatusCodeSame(Response::HTTP_MOVED_PERMANENTLY);
        $this->assertResponseRedirects('/ui/download');
    }
}
