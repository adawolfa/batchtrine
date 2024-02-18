<?php declare(strict_types=1);

namespace Tests\Adawolfa\Batchtrine;

use Adawolfa\Batchtrine\GC;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use PHPUnit\Framework\TestCase as TC;

abstract class TestCase extends TC
{

	protected readonly EntityManagerInterface $entityManager;

	protected readonly GC $gc;

	/**
	 * @throws OptimisticLockException
	 * @throws ORMException
	 */
	protected function setUp(): void
	{
		$this->entityManager = ConnectionFactory::create(100, 30);
		$this->gc            = new GC($this->entityManager);
	}

	protected function tearDown(): void
	{
		$this->entityManager->close();
		Stats::reset();
	}

	protected function assertPreConditions(): void
	{
		$this->assertSame([], Stats::$stats);
	}

	protected function assertPostConditions(): void
	{
		$this->assertSame([], Stats::$stats);
	}

}