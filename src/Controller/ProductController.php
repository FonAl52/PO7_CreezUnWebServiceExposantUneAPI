<?php

namespace App\Controller;

use App\Entity\Product;
use OpenApi\Attributes as OA;
use App\Repository\ProductRepository;
use JMS\Serializer\SerializerInterface;
use JMS\Serializer\SerializationContext;
use Nelmio\ApiDocBundle\Annotation\Model;
use Symfony\Contracts\Cache\ItemInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: 'Products')]
class ProductController extends AbstractController
{
    /**
     * Get a list of all products
     *
     * 
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param PaginatorInterface $paginator
     * @param ProductRepository $productRepository
     * @param TagAwareCacheInterface $cachePool
     * @return JsonResponse
     */
    #[Route('/api/products', name: 'product', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Returns the rewards of an user',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(
                ref: new Model(
                    type: Product::class,
                    groups: ['getProducts']
                )
            )
        )
    )]
    #[OA\Parameter(
        name: 'page',
        in: 'query',
        description: 'The page you want to retrieve',
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'limit',
        in: 'query',
        description: 'The number of elements we want to recover',
        schema: new OA\Schema(type: 'string')
    )]
    public function getAllProducts(
        Request $request,
        SerializerInterface $serializer,
        PaginatorInterface $paginator,
        ProductRepository $productRepository,
        TagAwareCacheInterface $cachePool
    ): JsonResponse {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);

        // Collect all products
        $idCache = "getAllProducts-" . $page . "-" . $limit;
        $allProducts = $cachePool->get($idCache, function (ItemInterface $item) use ($productRepository, $page, $limit) {
            echo ("Pas encore en cache");
            $item->tag("productsCache");

            // Retrieve only customers linked to the logged in user
            return $productRepository->findAll();;
        });

        // Paginate results with KnpPaginator
        $pagination = $paginator->paginate(
            $allProducts,
            $page,
            $limit
        );

        $context = SerializationContext::create()->setGroups(['getProducts']);
        $jsonProductList = $serializer->serialize($pagination->getItems(), 'json', $context);

        return new JsonResponse($jsonProductList, Response::HTTP_OK, [], true);
    }

    /**
     * Get the detail of one product
     * 
     * 
     * @param Product $product
     * @param SerializerInterface $serializer
     * @return JsonResponse
     */
    #[Route('api/products/{id}', name: 'product_detail', methods: ['GET'])]
    public function getProductDetail(
        Product $product,
        SerializerInterface $serializer
    ): JsonResponse {
        $context = SerializationContext::create()->setGroups(['getProducts']);
        $jsonProduct = $serializer->serialize($product, 'json', $context);

        return new JsonResponse($jsonProduct, Response::HTTP_OK, [], true);
    }
}
