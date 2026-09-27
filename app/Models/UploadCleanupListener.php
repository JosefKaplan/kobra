<?php

declare(strict_types=1);

namespace App\Models;

use App\Entity\LayoutBlock;
use App\Entity\Menu;
use App\Entity\PageComponent;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Tracy\Debugger;

/**
 * Deleting content deletes its images, whichever code path deletes it: collects the upload paths
 * a flush takes out of the content — deleted entities (container children included, the ORM
 * cascades them) and content edits that dropped an image (a replaced photo, a removed gallery
 * or carousel row) — and once the flush has committed, hands them to UploadCleaner::release().
 */
final class UploadCleanupListener implements EventSubscriber
{
	/** @var array<string, true> */
	private array $released = [];

	public function __construct(
		private readonly UploadCleaner $uploadCleaner,
	) {
	}

	public function getSubscribedEvents(): array
	{
		return [Events::onFlush, Events::postFlush];
	}

	public function onFlush(OnFlushEventArgs $args): void
	{
		$uow = $args->getObjectManager()->getUnitOfWork();

		foreach ($uow->getScheduledEntityDeletions() as $entity) {
			if (self::holdsContent($entity)) {
				$this->collect(UploadCleaner::pathsIn($entity->getContent()));
			}
		}

		foreach ($uow->getScheduledEntityUpdates() as $entity) {
			$change = self::holdsContent($entity) ? ($uow->getEntityChangeSet($entity)['content'] ?? null) : null;
			if ($change !== null) {
				$this->collect(array_diff(UploadCleaner::pathsIn($change[0]), UploadCleaner::pathsIn($change[1])));
			}
		}
	}

	public function postFlush(PostFlushEventArgs $args): void
	{
		if ($this->released === []) {
			return;
		}
		$paths = array_keys($this->released);
		$this->released = [];

		try {
			$this->uploadCleaner->release($paths);
		} catch (\Throwable $e) {
			// the content change itself is committed; leftovers get caught by the daily sweep
			Debugger::log($e, Debugger::EXCEPTION);
		}
	}

	/** @phpstan-assert-if-true PageComponent|LayoutBlock|Menu $entity */
	private static function holdsContent(object $entity): bool
	{
		return $entity instanceof PageComponent || $entity instanceof LayoutBlock || $entity instanceof Menu;
	}

	/** @param array<string> $paths */
	private function collect(array $paths): void
	{
		foreach ($paths as $path) {
			$this->released[$path] = true;
		}
	}
}
