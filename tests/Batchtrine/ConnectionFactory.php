<?php declare(strict_types=1);

namespace Tests\Adawolfa\Batchtrine;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Faker\Factory;
use Tests\Adawolfa\Batchtrine\Entity\Author;
use Tests\Adawolfa\Batchtrine\Entity\Book;

final class ConnectionFactory
{

	/**
	 * @throws ORMException
	 * @throws OptimisticLockException
	 */
	public static function create(int $numAuthors, int $numMaxBooksPerAuthor): EntityManagerInterface
	{
		$configuration = ORMSetup::createAttributeMetadataConfiguration([__DIR__ . '/Entity'], true);
		$connection    = DriverManager::getConnection([
			'driver' => 'pdo_sqlite',
			'memory' => true,
		], $configuration);

		$entityManager = new EntityManager($connection, $configuration);
		$schemaTool    = new SchemaTool($entityManager);

		$schemaTool->updateSchema($entityManager->getMetadataFactory()->getAllMetadata());

		$faker = Factory::create();
		$faker->seed(0);

		for ($i = 0; $i < $numAuthors; $i++) {
			$author = new Author($faker->name);
			$entityManager->persist($author);

			for ($j = $faker->numberBetween(1, $numMaxBooksPerAuthor); $j > 0; $j--) {
				$book = new Book($faker->sentence, $author);
				$entityManager->persist($book);
			}

			$entityManager->flush();
			$entityManager->clear();
		}

		return $entityManager;
	}

}