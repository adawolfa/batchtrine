<?php declare(strict_types=1);

namespace Adawolfa\Batchtrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Generator;

/**
 * Simple garbage collector for easy batch-processing with Doctrine.
 */
final class GC
{

	/** @var SavePoint[] */
	private array $stack = [];

	public function __construct(private readonly EntityManagerInterface $entityManager)
	{
	}

	/**
	 * Runs the callback and detaches all the entities that haven't
	 * been referenced by the UoW prior to its execution thereafter.
	 *
	 * @param  mixed ...$args optional arguments to be passed to the callback
	 * @return mixed          return value from the callback
	 */
	public function batch(callable $callback, mixed ...$args): mixed
	{
		$savePoint = $this->begin();

		try {
			return $callback(...$args);
		} finally {
			$this->collect($savePoint);
		}
	}

	/**
	 * Runs the callback for each element of given iterable. GC cycle happens after each iteration.
	 * Callback can break the iteration by returning `false`.
	 *
	 * @template K
	 * @template V
	 * @param  iterable<K, V>  $iterable
	 * @param  int             $interval number of iterations between each GC cycle
	 * @return Generator<K, V>
	 */
	public function iterate(iterable $iterable, int $interval = 100): Generator
	{
		$i = 0;

		$savePoint = $this->begin();

		foreach ($iterable as $key => $item) {

			yield $key => $item;

			if (++$i % $interval === 0) {
				$this->collect($savePoint);
				$savePoint = $this->begin();
			}

		}

		$this->collect($savePoint);
	}

	/**
	 * Pages over query result-set.
	 *
	 * @param int           $interval how many results to fetch per page and between each GC cycle
	 * @param callable|null $after    modifies query parameters for search-after queries
	 */
	public function paginate(Query $query, int $interval = 100, callable $after = null): Generator
	{
		$maxResults = $query->getMaxResults();
		$offset = $query->getFirstResult();
		$cntResults = 0;
		$last = null;

		$query->setMaxResults($interval);
		$query->disableResultCache();

		if ($after !== null && $query->getFirstResult() !== 0) {
			throw new LogicException('First result with search-after function is not supported.');
		}

		for ($firstResult = $query->getFirstResult();
			($maxResults === null || $firstResult < $maxResults + $offset)
			&& $interval > 0
			&& $cntResults % $interval === 0;
			$firstResult += $interval) {

			if ($after !== null) {
				$after($query, $last);
			} else {
				$query->setFirstResult($firstResult);
			}

			$savePoint = $this->begin();
			yield from $result = $query->getResult();
			$this->collect($savePoint);

			$cntResults += count($result);
			$last        = array_pop($result);

			if ($maxResults !== null) {
				$perPage = min($interval, $maxResults - $cntResults);
				$query->setMaxResults($perPage);
			}
		}
	}

	private function begin(): SavePoint
	{
		$this->checkUnitOfWorkNoScheduledChanges();
		return $this->stack[] = new SavePoint($this->entityManager->getUnitOfWork()->getIdentityMap());
	}

	private function collect(SavePoint $expectedSavePoint): void
	{
		$savePoint = array_pop($this->stack);

		if ($savePoint !== $expectedSavePoint) {
			throw new LogicException('Conflicting GC batch encountered.');
		}

		$this->checkUnitOfWorkNoScheduledChanges();
		$this->restoreSavePoint($savePoint);
	}

	private function restoreSavePoint(SavePoint $savePoint): void
	{
		foreach ($this->entityManager->getUnitOfWork()->getIdentityMap() as $class => $entities) {

			foreach ($entities as $hash => $entity) {

				if (isset($savePoint->identityMap[$class][$hash])) {
					continue;
				}

				$this->entityManager->detach($entity);

			}

		}
	}

	private function checkUnitOfWorkNoScheduledChanges(): void
	{
		if ($this->hasUnitOfWorkScheduledChanges()) {
			throw new LogicException('Unit of work has scheduled changes.');
		}
	}

	private function hasUnitOfWorkScheduledChanges(): bool
	{
		if (count($this->entityManager->getUnitOfWork()->getScheduledEntityInsertions()) > 0) {
			return true;
		}

		if (count($this->entityManager->getUnitOfWork()->getScheduledEntityUpdates()) > 0) {
			return true;
		}

		if (count($this->entityManager->getUnitOfWork()->getScheduledEntityDeletions()) > 0) {
			return true;
		}

		if (count($this->entityManager->getUnitOfWork()->getScheduledCollectionUpdates()) > 0) {
			return true;
		}

		if (count($this->entityManager->getUnitOfWork()->getScheduledCollectionDeletions()) > 0) {
			return true;
		}

		return false;
	}

}