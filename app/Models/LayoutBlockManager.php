<?php

declare(strict_types=1);

namespace App\Models;

use App\Entity\LayoutBlock;
use App\Entity\User;
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
			->from(LayoutBlock::class, 'b')
			->where('b.deletedDate IS NULL');
	}

	public function getById(int $id): ?LayoutBlock
	{
		return $this->em->getRepository(LayoutBlock::class)->findOneBy(['id' => $id, 'deletedDate' => null]);
	}

	/**
	 * id => name pairs of one type, for the page-form dropdowns.
	 *
	 * @return array<int, string>
	 */
	public function getSelectItems(string $type): array
	{
		$blocks = $this->em->getRepository(LayoutBlock::class)
			->findBy(['type' => $type, 'deletedDate' => null], ['name' => 'ASC']);

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

	/** Soft delete — pages referencing the block just stop showing it (Page::getNavBlock/getFooterBlock filter). */
	public function delete(int $id, ?User $deletedBy = null): void
	{
		$block = $this->getById($id);
		if ($block) {
			$block->markDeleted($deletedBy);
			$this->em->flush();
		}
	}
}
