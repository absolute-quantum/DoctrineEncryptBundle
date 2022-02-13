<?php

namespace App\Controller;

use App\Entity\Secret;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends AbstractController
{
    /**
     * @Route(name="home", path="/")
     */
    public function index(\App\Repository\Annotation\SecretRepository $secretUsingAnnotationRepository,
                          \App\Repository\Attribute\SecretRepository  $secretUsingAttributesRepository): Response
    {
        $secrets = array_merge(
            $secretUsingAnnotationRepository->findAll(),
            $secretUsingAttributesRepository->findAll()
        );

        return $this->render('index.html.twig',['secrets' => $secrets]);
    }

    /**
     * @Route(name="create", path="/create")
     */
    public function create(Request $request, EntityManagerInterface $em): Response
    {
        if (!$request->query->has('name') || !$request->query->has('secret') || !$request->query->has('type')) {
            return new Response('Please specify name, secret and type in url-query');
        }

        $type = $request->query->get('type');
        if ($type === 'annotation') {
            $secret = new \App\Entity\Annotation\Secret();
        } elseif($type === 'attribute') {
            $secret = new \App\Entity\Attribute\Secret();
        } else {
            return new Response('Type is only allowed to be "annotation" or "attribute"');
        }

        $secret
            ->setName($request->query->getAlnum('name'))
            ->setSecret($request->query->getAlnum('secret'));

        $em->persist($secret);
        $em->flush();

        return new Response(sprintf('OK - secret %s stored',$secret->getName()));
    }
}