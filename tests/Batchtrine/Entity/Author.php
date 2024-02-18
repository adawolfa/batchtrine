<?php declare(strict_types=1);

namespace Tests\Adawolfa\Batchtrine\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
class Author
{

	use Entity;

	#[ORM\Column]
	public string $name;

	public function __construct(string $name)
	{
		$this->name = $name;
	}

}