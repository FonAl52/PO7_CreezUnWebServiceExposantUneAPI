<?php

namespace App\Controller;

use App\Entity\Product;
use App\Repository\ProductRepository;
use JMS\Serializer\SerializerInterface;
use JMS\Serializer\SerializationContext;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;


class ProductController extends AbstractController
{
    #[Route('/api/products', name: 'product')]
    public function getAllProducts(
        Request $request,
        SerializerInterface $serializer,
        PaginatorInterface $paginator,
        ProductRepository $productRepository
    ): JsonResponse {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);

        // Récupérer tous les produits
        $allProducts = $productRepository->findAll();

        // Paginer les résultats avec KnpPaginator
        $pagination = $paginator->paginate(
            $allProducts,
            $page,
            $limit
        );

        $context = SerializationContext::create()->setGroups(['getProducts']);
        $jsonProductList = $serializer->serialize($pagination->getItems(), 'json', $context);

        return new JsonResponse($jsonProductList, Response::HTTP_OK, [], true);
    }
}
