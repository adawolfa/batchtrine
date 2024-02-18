<?php declare(strict_types=1);

namespace Tests\Adawolfa\Batchtrine\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
class Book
{

	use Entity;

	#[ORM\Column]
	public string $name;

	#[ORM\ManyToOne]
	public Author $author;

	public function __construct(string $name, Author $author)
	{
		$this->name   = $name;
		$this->author = $author;
	}

}