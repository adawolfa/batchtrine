<?php declare(strict_types=1);

namespace Adawolfa\Batchtrine;

/**
 * @internal
 */
final class SavePoint
{

	/**
	 * @param array<class-string, array<string, object>> $identityMap
	 */
	public function __construct(public readonly array $identityMap)
	{
	}

}