<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TestController extends AbstractController {

	#[Route('/test-bundle', name: 'test_bundle')]
	public function index(): Response {
		return $this->render('test.html.twig');
	}
}
