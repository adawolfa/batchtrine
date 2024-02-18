<?php declare(strict_types=1);

namespace Tests\Adawolfa\Batchtrine\Benchmark;

use Adawolfa\Batchtrine\GC;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Faker\Factory;
use PhpBench\Attributes\AfterMethods;
use PhpBench\Attributes\BeforeMethods;
use Tests\Adawolfa\Batchtrine\ConnectionFactory;
use Tests\Adawolfa\Batchtrine\Entity\Author;
use Tests\Adawolfa\Batchtrine\Entity\Book;

#[BeforeMethods('setUp')]
#[AfterMethods('tearDown')]
final class WriteBench
{

	private const NumAuthors = 3000;

	private readonly EntityManagerInterface $entityManager;

	/**
	 * @throws OptimisticLockException
	 * @throws ORMException
	 */
	public function setUp(): void
	{
		$this->entityManager = ConnectionFactory::create(0, 0);
	}

	public function tearDown(): void
	{
		$this->entityManager->close();
	}

	public function benchSingleFlush(): void
	{
		$faker = Factory::create();
		$faker->seed(0);

		for ($i = 0; $i < self::NumAuthors; $i++) {
			$author = new Author($faker->name);
			$this->entityManager->persist($author);

			for ($j = $faker->numberBetween(1, 100); $j > 0; $j--) {
				$book = new Book($faker->sentence, $author);
				$this->entityManager->persist($book);
			}
		}

		$this->entityManager->flush();
	}

	public function benchManualFlushAndClear(): void
	{
		$faker = Factory::create();
		$faker->seed(0);

		for ($i = 0; $i < self::NumAuthors; $i++) {
			$author = new Author($faker->name);
			$this->entityManager->persist($author);

			for ($j = $faker->numberBetween(1, 100); $j > 0; $j--) {
				$book = new Book($faker->sentence, $author);
				$this->entityManager->persist($book);
			}

			$this->entityManager->flush();
			$this->entityManager->clear();
		}
	}

	public function benchGC(): void
	{
		$gc = new GC($this->entityManager);

		$faker = Factory::create();
		$faker->seed(0);

		for ($i = 0; $i < self::NumAuthors; $i++) {
			$gc->batch(function () use ($faker): void {
				$author = new Author($faker->name);
				$this->entityManager->persist($author);

				for ($j = $faker->numberBetween(1, 100); $j > 0; $j--) {
					$book = new Book($faker->sentence, $author);
					$this->entityManager->persist($book);
				}

				$this->entityManager->flush();
			});
		}
	}

}