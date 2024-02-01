<?php

namespace App\DataFixtures;

use Faker\Factory;
use App\Entity\User;
use Faker\Generator;
use App\Entity\Product;
use App\Entity\Customer;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private Generator $faker;
    
    private $userPasswordHasher;
    
    /**
     * Construct
     *
     * @param UserPasswordHasherInterface $userPasswordHasher
     */
    public function __construct(UserPasswordHasherInterface $userPasswordHasher)
    {
        $this->userPasswordHasher = $userPasswordHasher;
        $this->faker = Factory::create('fr_FR');
    }
    //end __construct()


    /**
     * Fixtures creation
     *
     * @param ObjectManager $manager
     * @return void
     */
    public function load(ObjectManager $manager): void
    {
        // Création du user BileMo
        $user = new User();
        $user->setUsername("BileMo");
        $user->setEmail("user@bilemo.com");
        $user->setRoles(["ROLE_USER"]);
        $user->setPassword($this->userPasswordHasher->hashPassword($user, "BileMoP07"));
        $users[] = $user;
        $manager->persist($user);

        //Création de 3 users aléatoire
        for ($i = 0; $i < 3; $i++) {
            $user = new User();
            $user->setUsername($this->faker->userName);
            $user->setEmail($this->faker->email);
            $user->setRoles(["ROLE_USER"]);
            $user->setPassword($this->userPasswordHasher->hashPassword($user, $this->faker->password));

            $users[] = $user;
            $manager->persist($user);
        }

        // Création de 50 customers liés aléatoirement aux utilisateurs
        for ($i = 0; $i < 50; $i++) {
            $customer = new Customer();
            $customer->setFirstName($this->faker->firstName);
            $customer->setLastName($this->faker->lastName);
            $customer->setEmail($this->faker->email);
            $randomUser = $users[array_rand($users)];

            $customer->setUser($randomUser);

            $manager->persist($customer);
        }

        // Création de produits réalistes avec Faker
        for ($i = 0; $i < 10; $i++) {
            $product = new Product();
            $product->setName($this->faker->word);
            $product->setDescription($this->faker->sentence);
            $product->setPrice($this->faker->randomFloat(2, 10, 100)); // Prix aléatoire entre 10 et 100 avec 2 décimales

            $manager->persist($product);
        }

        $manager->flush();
    }
}
