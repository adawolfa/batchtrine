Simple garbage collector for easy batch processing with Doctrine ORM.

### Installation

~~~bash
composer require adawolfa/batchtrine
~~~

### Usage

The GC works by copying the identity map from the Unit of Work at the start of each batch and later selectively detaching all the entities that weren't previously part of it. Such entities shouldn't generally be used outside the scope of the batch.

This is supposed to solve the problem with `EntityManager::clear()`, which renders all the existing references to entities throughout the application invalid, and it's much more straightforward than detaching entities manually.

#### Single batch

The `batch()` method runs the supplied callback, forwards its return value and detaches all entities that have been loaded or created during its execution.

~~~php
$gc = new Adawolfa\Batchtrine\GC($em);

$a = $em->getRepository(Entity::class)->findBy(['code' => 'a']);

$b = $gc->batch(function () use ($em): Entity {
	return $em->getRepository(Entity::class)->findBy(['code' => 'b']);
});

$em->contains($a); // true
$em->contains($b); // false - entity is detached
~~~

#### Iterator

The `iterate()` method returns a proxy iterator which periodically performs the GC cycle after set number of iterations (`$interval`).

~~~php
$a = $em->getRepository(Entity::class)->findBy(['code' => 'a']);

foreach ($gc->iterate($ids) as $id) {
	$entity = $em->getRepository(Entity::class)->find($id);
	assert($a !== $entity);
	// ...
}

$em->contains($a);      // true
$em->contains($entity); // false
~~~

#### Pagination

The `paginate()` method is useful for traversing through a large result set `Doctrine\ORM\Query`. The results are obtained by executing the query repeatedly with a smaller limit (`$interval`) and increasing offset. The GC cycle happens every time before a new result page is fetched.

~~~php
$a = $em->getRepository(Entity::class)->findBy(['code' => 'a']);

$query = $em->createQueryBuilder()
	->select('e')
	->from(Entity::class, 'e')
	->getQuery();

foreach ($gc->paginate($query) as $entity) {
	assert($a !== $entity);
	// ...
}

$em->contains($a);      // true
$em->contains($entity); // false
~~~

For frequently changing data, you should use search-after approach instead.

~~~php
$query = $em->createQueryBuilder()
	->select('e')
	->from(Entity::class, 'e')
	->where('e.id > :id')
	->orderBy('e.id')
	->getQuery();

$after = function (Query $query, ?Entity $last): void {
	$query->setParameter('id', $last?->id ?? 0);
};

foreach ($gc->paginate($query, after: $after) as $entity) {
	assert($a !== $entity);
	// ...
}
~~~