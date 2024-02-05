<?php

namespace App\Controller;

use App\Entity\Product;
use App\Repository\ProductRepository;
use JMS\Serializer\SerializerInterface;
use JMS\Serializer\SerializationContext;
use Symfony\Contracts\Cache\ItemInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;


class ProductController extends AbstractController
{
    #[Route('/api/products', name: 'product')]
    public function getAllProducts(
        Request $request,
        SerializerInterface $serializer,
        PaginatorInterface $paginator,
        ProductRepository $productRepository,
        TagAwareCacheInterface $cachePool
    ): JsonResponse {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);

        // Récupérer tous les produits

        $idCache = "getAllProducts-" . $page . "-" . $limit;
        $allProducts = $cachePool->get($idCache, function (ItemInterface $item) use ($productRepository, $page, $limit) {
            echo ("Pas encore en cache");
            $item->tag("productsCache");

            // Récupérer uniquement les customers liés à l'utilisateur connecté
            return $productRepository->findAll();;
        });

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

    #[Route('api/products/{id}', name: 'product_detail', methods: ['GET'])]
    public function getProductDetail(Product $product, SerializerInterface $serializer): JsonResponse
    {
        $context = SerializationContext::create()->setGroups(['getProducts']);
        // Normaliser l'entité Product en JSON
        $jsonProduct = $serializer->serialize($product, 'json', $context);

        return new JsonResponse($jsonProduct, Response::HTTP_OK, [], true);
    }
}
