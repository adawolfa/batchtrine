<?php declare(strict_types=1);

namespace Tests\Adawolfa\Batchtrine;

use Adawolfa\Batchtrine\LogicException;
use Doctrine\ORM\Query;
use Tests\Adawolfa\Batchtrine\Entity\Author;
use Tests\Adawolfa\Batchtrine\Entity\Book;

final class GCTest extends TestCase
{

	public function testBatchOne(): void
	{
		(function (): void {

			$author = $this->entityManager->getRepository(Author::class)->find(50);
			$book   = $this->entityManager->getRepository(Book::class)->find(50);

			$this->assertTrue($this->gc->batch(function (): bool {
				$book = $this->entityManager->getRepository(Book::class)->find(1);
				$this->assertNotNull($book);
				$this->assertSame([
					Author::class => 1,
					Book::class   => 2,
				], Stats::$stats);
				return true;
			}));

			$this->assertTrue($this->entityManager->contains($author));
			$this->assertTrue($this->entityManager->contains($book));

		})();

		$this->assertSame([
			Author::class => 1,
			Book::class   => 1,
		], Stats::$stats);

		$this->entityManager->clear();
	}

	public function testBatchAll(): void
	{
		$this->assertTrue($this->gc->batch(function (): bool {
			$book = $this->entityManager->getRepository(Book::class)->find(1);
			$this->assertNotNull($book);
			return true;
		}));

		$this->assertSame([], Stats::$stats);
	}

    public function testIterate(): void
    {
        $this->entityManager->getRepository(Book::class)->find(1);

		foreach ($this->gc->iterate(range(2, 50), 1) as $id) {
			$this->entityManager->getRepository(Book::class)->find($id);
			$this->assertSame([Book::class => 2], Stats::$stats);
		}

        $this->assertSame([Book::class => 1], Stats::$stats);
		$this->entityManager->clear();
    }

	public function testBatchNotFlushedBeforeBegin(): void
	{
		$author = new Author('John Doe');
		$this->entityManager->persist($author);
		$this->expectException(LogicException::class);
        $this->expectExceptionMessage('Unit of work has scheduled changes.');
		$this->gc->batch(fn () => null);
	}

	public function testBatchNotFlushedBeforeFinish(): void
	{
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Unit of work has scheduled changes.');

        $this->gc->batch(function (): void {
            $author = new Author('John Doe');
            $this->entityManager->persist($author);
        });
	}

    public function testNestedBatch(): void
    {
		$a = $this->entityManager->getRepository(Book::class)->find(1);

        $this->gc->batch(function (): void {
			$b = $this->entityManager->getRepository(Book::class)->find(2);

			$this->gc->batch(function (): void {
				$this->entityManager->getRepository(Book::class)->find(3);
				$this->assertSame([Book::class => 3], Stats::$stats);
			});

			$this->assertTrue($this->entityManager->contains($b));
			$this->assertSame([Book::class => 2], Stats::$stats);
        });

		$this->assertTrue($this->entityManager->contains($a));
		$this->assertSame([Book::class => 1], Stats::$stats);
		$this->entityManager->clear();
    }

	public function testConflictingBatch(): void
	{
		$i1 = $this->gc->iterate([1, 2], 1);
		$i2 = $this->gc->iterate([1, 2], 1);

		$i1->rewind();
		$i2->rewind();

		$this->expectException(LogicException::class);
		$this->expectExceptionMessage('Conflicting GC batch encountered.');

		$i1->next();
	}

	public function testPaginateWMaxResults(): void
	{
		if (PHP_VERSION_ID < 80300) {
			$this->markTestSkipped();
		}

		$booksQuery = $this->entityManager
			->createQueryBuilder()
			->select('b')
			->from(Book::class, 'b')
			->getQuery()
			->setMaxResults(1620);

		$this->assertPagination(1620, '85766dbdc5d5b598e8744e3562b3f78b', $booksQuery);
	}

	public function testPaginateWoMaxResults(): void
	{
		if (PHP_VERSION_ID < 80300) {
			$this->markTestSkipped();
		}

		$booksQuery = $this->entityManager
			->createQueryBuilder()
			->select('b')
			->from(Book::class, 'b')
			->getQuery();

		$this->assertPagination(1621, '42fe82dcc38e8e88795383df035fe3ce', $booksQuery);
	}

	public function testPaginateWFirstResultAndMaxResults(): void
	{
		$booksQuery = $this->entityManager
			->createQueryBuilder()
			->select('b')
			->from(Book::class, 'b')
			->setFirstResult(1000)
			->setMaxResults(200)
			->getQuery();

		$this->assertPagination(200, 'e98087fff1a665691c0256c517eb8c31', $booksQuery);
	}

	public function testPaginateSearchAfter(): void
	{
		if (PHP_VERSION_ID < 80300) {
			$this->markTestSkipped();
		}

		$booksQuery = $this->entityManager
			->createQueryBuilder()
			->select('b')
			->from(Book::class, 'b')
			->where('b.id > :id')
			->getQuery();

		$after = function (Query $query, ?Book $last): void {
			$query->setParameter('id', $last?->id ?? 0);
		};

		$this->assertPagination(1621, '42fe82dcc38e8e88795383df035fe3ce', $booksQuery, $after);
	}

	private function assertPagination(
		int      $expectedCount,
		string   $expectedMd5,
		Query    $query,
		callable $after = null,
	): void
	{
		$count = 0;
		$hash = hash_init('md5');

		foreach ($this->gc->paginate($query, after: $after) as $book) {
			/** @var Book $book */
			$count++;
			hash_update($hash, (string) $book->id);
		}

		$this->assertSame($expectedCount, $count);
		$this->assertSame($expectedMd5, hash_final($hash));
	}

}