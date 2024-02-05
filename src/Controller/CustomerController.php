<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Repository\CustomerRepository;
use JMS\Serializer\SerializerInterface;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializationContext;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;


class CustomerController extends AbstractController
{
    /**
     * Create a Customer link to a User
     *
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param EntityManagerInterface $entityManager
     * @param Security $security
     * @param ValidatorInterface $validator
     * @return JsonResponse
     */
    #[Route('/api/customers', name: "createCustomer", methods: ['POST'])]
    public function createCustomer(
        Request $request,
        SerializerInterface $serializer,
        EntityManagerInterface $entityManager,
        Security $security,
        ValidatorInterface $validator
    ): JsonResponse {
        // Récupérer l'utilisateur connecté
        $user = $security->getUser();

        // Vérifier si l'utilisateur est connecté
        if (!$user) {
            return new JsonResponse(['error' => 'User not authenticated'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        // Désérialiser les données de la requête en une instance de Customer
        $customer = $serializer->deserialize($request->getContent(), Customer::class, 'json');

        // Ajouter la date actuelle à la propriété createdAt et updatedAt
        $customer->setCreatedAt(new \DateTimeImmutable());
        $customer->setUpdatedAt(new \DateTimeImmutable());

        // Valider les données de l'entité Customer
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
                    case 'c1051bb4-d103-4f74-8988-acbcafc7fdc3':
                        $errorCode = '400';
                        $errorMessage = 'Le nom et prénom de client sont obligatoires.';
                        break;
                    default:
                        // Message par défaut pour les autres types d'erreur
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
        
        // Associer le client à l'utilisateur connecté
        $customer->setUser($user);
        
        // Persister et sauvegarder
        $entityManager->persist($customer);
        $entityManager->flush();
        
        // Transformer la requéte en tableau
        $customerSerialize = $request->toArray();

        // Ajouter l'ID de l'utilisateur à la réponse
        $customerSerialize['userId'] = $customer->getUser()->getId();

        $context = SerializationContext::create()->setGroups(['getCustomers']);
        // Normaliser l'entité Customer en JSON
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
     * @param Security $security
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param CustomerRepository $customerRepository
     * @return JsonResponse
     */
    #[Route('/api/customers', name: 'customer', methods: ['GET'])]
    public function getAllCustomers(
        Security $security,
        Request $request,
        SerializerInterface $serializer,
        CustomerRepository $customerRepository,
        TagAwareCacheInterface $cachePool
    ): JsonResponse {
        // Récupérer l'utilisateur connecté
        $user = $security->getUser();
        
        // Vérifier si l'utilisateur est connecté
        if (!$user) {
            return new JsonResponse(['error' => 'User not authenticated'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);

        $idCache = "getAllCustomers-" . $page . "-" . $limit;
        $customerList = $cachePool->get($idCache, function (ItemInterface $item) use ($customerRepository, $page, $limit, $user) {
            echo ("Pas encore en cache");
            $item->tag("customersCache");

            // Récupérer uniquement les customers liés à l'utilisateur connecté
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
        // Vérifier si l'utilisateur connecté est le propriétaire du client
        $user = $this->getUser();
        if ($user !== $customer->getUser()) {
            return new JsonResponse(['code' => '401 Unauthorized' ,'message' => 'Ce client ne vous appartient pas'], JsonResponse::HTTP_UNAUTHORIZED);
        }
        $context = SerializationContext::create()->setGroups(['getCustomers']);
        // Normaliser l'entité Customer en JSON
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
    public function updateCustomer(
        Customer $customer,
        Request $request,
        EntityManagerInterface $entityManager,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        TagAwareCacheInterface $cachePool
    ): JsonResponse {
        // Vérifier si l'utilisateur connecté est le propriétaire du client
        $user = $this->getUser();
        if ($user !== $customer->getUser()) {
            return new JsonResponse(['code' => '401 Unauthorized' ,'message' => 'Ce client ne vous appartient pas'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        // Désérialiser les données de la requête en une instance de Customer
        $updatedCustomer = $serializer->deserialize($request->getContent(), Customer::class, 'json');
        
        // Valider les données de l'entité Customer
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
                        // Message par défaut pour les autres types d'erreur
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
        
        // Mettre à jour les propriétés de l'entité Customer
        $customer->setFirstName($updatedCustomer->getFirstName());
        $customer->setLastName($updatedCustomer->getLastName());

        // Persister et sauvegarder
        $entityManager->flush();

        // Clear cache data
        $cachePool->invalidateTags(["customersCache"]);
        
        // Transformer la requête mise à jour en tableau
        $updatedCustomerData = $request->toArray();

        // Ajouter l'ID de l'utilisateur à la réponse
        $updatedCustomerData['userId'] = $customer->getUser()->getId();

        // Normaliser l'entité Customer mise à jour en JSON
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
        // Vérifier si l'utilisateur connecté est le propriétaire du client
        $user = $this->getUser();
        if ($user !== $customer->getUser()) {
            return new JsonResponse(['code' => '401 Unauthorized' ,'message' => 'Ce client ne vous appartient pas'], JsonResponse::HTTP_UNAUTHORIZED);
        }
        // Clear cache data
        $cachePool->invalidateTags(["customersCache"]);
        // Delete the customer
        $entityManager->remove($customer);
        $entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
