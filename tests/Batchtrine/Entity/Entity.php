<?php declare(strict_types=1);

namespace Tests\Adawolfa\Batchtrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Tests\Adawolfa\Batchtrine\Stats;

trait Entity
{

	private bool $registered = false;

	#[ORM\Id]
	#[ORM\Column]
	#[ORM\GeneratedValue]
	public readonly int $id;

	#[ORM\PostLoad]
	#[ORM\PostPersist]
	public function register(): void
	{
		if ($this->registered = !$this->registered) {
			Stats::add($this);
		}
	}

	public function __destruct()
	{
		if ($this->registered) {
			Stats::remove($this);
		}
	}

}