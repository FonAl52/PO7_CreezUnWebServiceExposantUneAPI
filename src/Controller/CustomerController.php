<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Repository\CustomerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\SerializerInterface;
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
        
        // Valider les données de l'entité Customer
        $errors = $validator->validate($customer);

        if (count($errors) > 0) {
            return new JsonResponse(['error' => (string) $errors], JsonResponse::HTTP_BAD_REQUEST);
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

        // Normaliser l'entité Customer en JSON
        $jsonCustomer = $serializer->serialize($customerSerialize, 'json');
        
        
        return new JsonResponse($jsonCustomer,
            Response::HTTP_CREATED,
            [],
            true
        );
    }

    #[Route('/api/customers', name: 'customer', methods: ['GET'])]
    public function getAllCustomers(
        Security $security,
        Request $request,
        SerializerInterface $serializer,
        CustomerRepository $customerRepository
    ): JsonResponse {
        // Récupérer l'utilisateur connecté
        $user = $security->getUser();
        
        // Vérifier si l'utilisateur est connecté
        if (!$user) {
            return new JsonResponse(['error' => 'User not authenticated'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);

        // Récupérer uniquement les customers liés à l'utilisateur connecté
        $customerList = $customerRepository->findCustomersByUserIdWithPagination($user->getId(), $page, $limit);
        $jsonCustomerList = $serializer->serialize($customerList, 'json', ['groups' => 'getCustomers']);

        return new JsonResponse($jsonCustomerList, Response::HTTP_OK, [], true);
    }
}
