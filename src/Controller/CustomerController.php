<?php

namespace App\Controller;

use App\Entity\Customer;
use OpenApi\Attributes as OA;
use App\Repository\CustomerRepository;
use JMS\Serializer\SerializerInterface;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializationContext;
use Nelmio\ApiDocBundle\Annotation\Model;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Bundle\SecurityBundle\Security as SymfonySecurity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;


#[OA\Tag(name: 'Customers')]
class CustomerController extends AbstractController
{
    /**
     * Create a Customer link to a User
     *
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param EntityManagerInterface $entityManager
     * @param SymfonySecurity $security
     * @param ValidatorInterface $validator
     * @return JsonResponse
     */
    #[Route('/api/customers', name: "createCustomer", methods: ['POST'])]
    #[OA\Response(
        response: 200,
        description: 'Create a new user',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(
                ref: new Model(
                    type: Customer::class,
                    groups: ['getCustomers']
                )
            )
        )
    )]
    #[OA\RequestBody(
        description: 'Create a new user',
        required: true,
        content: new OA\MediaType(
            mediaType: 'application/json',
            schema: new OA\Schema(
                type: 'object',
                properties: [
                    new OA\Property(
                        type: 'string',
                    )
                    ],
                    example: [
                        'lastName' => 'Doe',
                        'firstName' => 'John',
                        'email' => 'john.doe@example.com'
                    ]
            )
        )
    )]
    public function createCustomer(
        Request $request,
        SerializerInterface $serializer,
        EntityManagerInterface $entityManager,
        SymfonySecurity $security,
        ValidatorInterface $validator
    ): JsonResponse {
        // Retrieve the logged in user
        $user = $security->getUser();

        // Check if the user is logged in
        if (!$user) {
            return new JsonResponse(['error' => 'User not authenticated'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $customer = $serializer->deserialize($request->getContent(), Customer::class, 'json');

        // Add the current date to the createdAt and updatedAt property
        $customer->setCreatedAt(new \DateTimeImmutable());
        $customer->setUpdatedAt(new \DateTimeImmutable());

        $errors = $validator->validate($customer);
        
        if (count($errors) > 0) {
            $errorData = [];

            foreach ($errors as $violation) {
                $errorCode = $violation->getCode();
                $errorMessage = $violation->getMessage();
            
                switch ($errorCode) {
                    case '23bd9dbf-6b9b-41cd-a99e-4844bcf3077f':
                        $errorCode = '400';
                        $errorMessage = 'L\'email est déjà utilisée.';
                        break;
                    case  'bd79c0ab-ddba-46cc-a703-a7a4b08de310':
                        $errorCode = '400';
                        $errorMessage = 'Cet email n\'est pas valide.';
                        break;
                    default:
                        $errorCode = '400';
                        $errorMessage = 'Tous les champs sont obligatoires';
                        break;
                }
            
                $errorData[] = [
                    'code' => $errorCode,
                    'message' => $errorMessage,
                ];
            }

            return new JsonResponse($errorData, JsonResponse::HTTP_BAD_REQUEST);
        }
        
        $customer->setUser($user);
        
        $entityManager->persist($customer);
        $entityManager->flush();
        
        $customerSerialize = $request->toArray();

        // Add user ID to response
        $customerSerialize['userId'] = $customer->getUser()->getId();

        $context = SerializationContext::create()->setGroups(['getCustomers']);

        $jsonCustomer = $serializer->serialize($customerSerialize, 'json', $context);
        
        return new JsonResponse($jsonCustomer,
            Response::HTTP_CREATED,
            [],
            true
        );
    }

    /**
     * Get all customers link to a user
     *
     * 
     * @OA\Response(
     *     response=200,
     *     description="Return the list of customers",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=Customer::class, groups={"getCustomers"}))
     *     )
     * )
     * @OA\Parameter(
     *     name="page",
     *     in="query",
     *     description="The page you want to retrieve",
     *     @OA\Schema(type="int")
     * )
     *
     * @OA\Parameter(
     *     name="limit",
     *     in="query",
     *     description="The number of elements we want to recover",
     *     @OA\Schema(type="int")
     * )
     * 
     * @OA\Tag(name="Customer")
     * 
     * 
     * @param SymfonySecurity $security
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param CustomerRepository $customerRepository
     * @return JsonResponse
     */
    #[Route('/api/customers', name: 'customer', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Returns the rewards of an user',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(
                ref: new Model(
                    type: Customer::class,
                    groups: ['getCustomers']
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
    public function getAllCustomers(
        SymfonySecurity $security,
        Request $request,
        SerializerInterface $serializer,
        CustomerRepository $customerRepository,
        TagAwareCacheInterface $cachePool
    ): JsonResponse {
        // Retrieve the logged in user
        $user = $security->getUser();
        
        // Check if the user is logged in
        if (!$user) {
            return new JsonResponse(['error' => 'User not authenticated'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);

        $idCache = "getAllCustomers-" . $page . "-" . $limit;
        $customerList = $cachePool->get($idCache, function (ItemInterface $item) use ($customerRepository, $page, $limit, $user) {
            echo ("Pas encore en cache");
            $item->tag("customersCache");

            return $customerRepository->findCustomersByUserIdWithPagination($user->getId(), $page, $limit);
        });

        $context = SerializationContext::create()->setGroups(['getCustomers']);
        $jsonCustomerList = $serializer->serialize($customerList, 'json', $context);

        return new JsonResponse($jsonCustomerList, Response::HTTP_OK, [], true);
    }


    /**
     * Get a Customer Details
     * 
     * @param Customer $customer
     * @param SerializerInterface $serializer
     * @return JsonResponse
     */
    #[Route('api/customers/{id}', name: 'customer_detail', methods: ['GET'])]
    public function getCustomerDetail(Customer $customer, SerializerInterface $serializer): JsonResponse
    {
        // Check if the logged in user is the client owner
        $user = $this->getUser();
        if ($user !== $customer->getUser()) {
            return new JsonResponse(['code' => '401 Unauthorized' ,'message' => 'Ce client ne vous appartient pas'], JsonResponse::HTTP_UNAUTHORIZED);
        }
        $context = SerializationContext::create()->setGroups(['getCustomers']);

        $jsonCustomer = $serializer->serialize($customer, 'json', $context);

        return new JsonResponse($jsonCustomer, Response::HTTP_OK, [], true);
    }

    /**
     * Update a specific customer
     *
     * @param Customer $customer
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param SerializerInterface $serializer
     * @param ValidatorInterface $validator
     * @param TagAwareCacheInterface $cachePool
     * @return JsonResponse
     */
    #[Route('api/customers/{id}', name: 'customer_update', methods: ['PUT'])]
    #[OA\Response(
        response: 200,
        description: 'Update a user',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(
                ref: new Model(
                    type: Customer::class,
                    groups: ['getCustomers']
                )
            )
        )
    )]
    #[OA\RequestBody(
        description: 'Update a user',
        required: true,
        content: new OA\MediaType(
            mediaType: 'application/json',
            schema: new OA\Schema(
                type: 'object',
                properties: [
                    new OA\Property(
                        type: 'string',
                    )
                    ],
                    example: [
                        'lastName' => 'Doe',
                        'firstName' => 'John',
                        'email' => 'john.doe@example.com'
                    ]
            )
        )
    )]
    public function updateCustomer(
        Customer $customer,
        Request $request,
        EntityManagerInterface $entityManager,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        TagAwareCacheInterface $cachePool
    ): JsonResponse {
        // Check if the logged in user is the client owner
        $user = $this->getUser();
        if ($user !== $customer->getUser()) {
            return new JsonResponse(['code' => '401 Unauthorized' ,'message' => 'Ce client ne vous appartient pas'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $updatedCustomer = $serializer->deserialize($request->getContent(), Customer::class, 'json');
        
        $errors = $validator->validate($updatedCustomer);
        if (count($errors) > 0) {
            $errorData = [];

            foreach ($errors as $violation) {
                $errorCode = $violation->getCode();
                $errorMessage = $violation->getMessage();
            
                switch ($errorCode) {
                    case '23bd9dbf-6b9b-41cd-a99e-4844bcf3077f':
                        $errorCode = '400';
                        $errorMessage = 'L\'email est déjà utilisée.';
                        break;
                    case  'bd79c0ab-ddba-46cc-a703-a7a4b08de310':
                        $errorCode = '400';
                        $errorMessage = 'Cet email n\'est pas valide.';
                        break;
                    case 'c1051bb4-d103-4f74-8988-acbcafc7fdc3':
                        $errorCode = '400';
                        $errorMessage = 'Le nom et prénom de client sont obligatoires.';
                        break;
                    default:
                        $errorMessage = 'Une erreur est survenue lors de la validation.';
                        break;
                }
                
                $errorData[] = [
                    'code' => $errorCode,
                    'message' => $errorMessage,
                ];
            }
            
            return new JsonResponse($errorData, JsonResponse::HTTP_BAD_REQUEST);
        }
        
        $customer->setFirstName($updatedCustomer->getFirstName());
        $customer->setLastName($updatedCustomer->getLastName());

        $entityManager->flush();

        // Clear cache data
        $cachePool->invalidateTags(["customersCache"]);
        
        $updatedCustomerData = $request->toArray();

        $updatedCustomerData['userId'] = $customer->getUser()->getId();

        $jsonUpdatedCustomer = $serializer->serialize($updatedCustomerData, 'json');

        return new JsonResponse($jsonUpdatedCustomer, Response::HTTP_OK, [], true);
    }

    /**
     * Delete a Customer
     * 
     * @param Customer $customer
     * @param EntityManagerInterface $entityManager
     * @return JsonResponse
     */
    #[Route('/api/customers/{id}', name: 'customer_delete', methods: ['DELETE'])]
    public function deleteCustomer(
        Customer $customer,
        EntityManagerInterface $entityManager,
        TagAwareCacheInterface $cachePool
        ): JsonResponse {
        // Check if the logged in user is the client owner
        $user = $this->getUser();
        if ($user !== $customer->getUser()) {
            return new JsonResponse(['code' => '401 Unauthorized' ,'message' => 'Ce client ne vous appartient pas'], JsonResponse::HTTP_UNAUTHORIZED);
        }
        // Clear cache data
        $cachePool->invalidateTags(["customersCache"]);
        
        $entityManager->remove($customer);
        $entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
