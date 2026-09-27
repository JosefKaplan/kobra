<?php

declare(strict_types=1);

namespace App\Models;

use App\Entity\LayoutBlock;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

class LayoutBlockManager
{
	public function __construct(
		private readonly EntityManagerInterface $em,
	) {
	}

	public function findAll(): QueryBuilder
	{
		return $this->em->createQueryBuilder()
			->select('b')
			->from(LayoutBlock::class, 'b');
	}

	public function getById(int $id): ?LayoutBlock
	{
		return $this->em->getRepository(LayoutBlock::class)->find($id);
	}

	/**
	 * id => name pairs of one type, for the page-form dropdowns.
	 *
	 * @return array<int, string>
	 */
	public function getSelectItems(string $type): array
	{
		$blocks = $this->em->getRepository(LayoutBlock::class)
			->findBy(['type' => $type], ['name' => 'ASC']);

		$items = [];
		foreach ($blocks as $block) {
			$items[$block->getId()] = $block->getName();
		}

		return $items;
	}

	public function save(LayoutBlock $block): void
	{
		$this->em->persist($block);
		$this->em->flush();
	}

	/** Hard delete — pages using the block lose it (FK ON DELETE SET NULL), its images go via UploadCleanupListener. */
	public function delete(int $id): void
	{
		$block = $this->getById($id);
		if ($block) {
			$this->em->remove($block);
			$this->em->flush();
		}
	}
}
