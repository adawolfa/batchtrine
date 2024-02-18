<?php declare(strict_types=1);

namespace Tests\Adawolfa\Batchtrine\Benchmark;

use Adawolfa\Batchtrine\GC;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use PhpBench\Attributes\AfterMethods;
use PhpBench\Attributes\BeforeMethods;
use Tests\Adawolfa\Batchtrine\ConnectionFactory;
use Tests\Adawolfa\Batchtrine\Entity\Book;

#[BeforeMethods('setUp')]
#[AfterMethods('tearDown')]
final class ReadBench
{

	private const NumAuthors = 3000;

	private readonly EntityManagerInterface $entityManager;

	/**
	 * @throws OptimisticLockException
	 * @throws ORMException
	 */
	public function setUp(): void
	{
		$this->entityManager = ConnectionFactory::create(self::NumAuthors, 100);
	}

	public function tearDown(): void
	{
		$this->entityManager->close();
	}

	public function benchNoPaginateNoGC(): void
	{
		$query = $this->entityManager
			->createQueryBuilder()
			->select('b')
			->from(Book::class, 'b')
			->getQuery();

		foreach ($query->getResult() as $book);
	}

	public function benchPaginateNoGC(): void
	{
		$query = $this->entityManager
			->createQueryBuilder()
			->select('b')
			->from(Book::class, 'b')
			->getQuery()
			->setMaxResults(100)
			->disableResultCache();

		for ($i = 0;; $i += 100) {

			$query->setFirstResult($i);

			try {

				if (count($query->getResult()) < 100) {
					break;
				}

			} finally {
				$this->entityManager->clear();
			}

		}
	}

	public function benchGC(): void
	{
		$gc = new GC($this->entityManager);

		$query = $this->entityManager
			->createQueryBuilder()
			->select('b')
			->from(Book::class, 'b')
			->getQuery();

		foreach ($gc->paginate($query) as $book);
	}

}