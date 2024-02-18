<?php declare(strict_types=1);

namespace Tests\Adawolfa\Batchtrine;

use UnderflowException;

/**
 * Tracks number of entities in memory.
 */
final class Stats
{

	/** @var array<class-string, int> */
	public static array $stats = [];

	public static function add(object $entity): void
	{
		$class = self::getRootClass($entity);
		self::$stats[$class] ??= 0;
		self::$stats[$class]++;
		ksort(self::$stats);
	}

	public static function remove(object $entity): void
	{
		$class = self::getRootClass($entity);

		if (!isset(self::$stats[$class])) {
			throw new UnderflowException;
		}

		if (--self::$stats[$class] === 0) {
			unset(self::$stats[$class]);
		}
	}

	public static function reset(): void
	{
		self::$stats = [];
	}

	private static function getRootClass(object $entity): string
	{
		$class = $entity::class;

		while (($parentClass = get_parent_class($class)) !== false) {
			$class = $parentClass;
		}

		return $class;
	}

}