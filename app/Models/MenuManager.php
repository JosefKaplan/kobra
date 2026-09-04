<?php

declare(strict_types=1);

namespace App\Models;

use App\Entity\Menu;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

class MenuManager
{
	public function __construct(
		private readonly EntityManagerInterface $em,
	) {
	}

	public function findAll(): QueryBuilder
	{
		return $this->em->createQueryBuilder()
			->select('m')
			->from(Menu::class, 'm')
			->where('m.deletedDate IS NULL');
	}

	public function getById(int $id): ?Menu
	{
		return $this->em->getRepository(Menu::class)->findOneBy(['id' => $id, 'deletedDate' => null]);
	}

	/** @param int[] $ids
	 * @return Menu[] */
	public function getByIds(array $ids): array
	{
		if ($ids === []) {
			return [];
		}

		return $this->em->createQueryBuilder()
			->select('m')
			->from(Menu::class, 'm')
			->where('m.id IN (:ids)')
			->andWhere('m.deletedDate IS NULL')
			->setParameter('ids', $ids)
			->orderBy('m.name', 'ASC')
			->getQuery()
			->getResult();
	}

	/**
	 * id => name pairs for the nav-block form's checkbox list.
	 *
	 * @return array<int, string>
	 */
	public function getSelectItems(): array
	{
		$menus = $this->em->getRepository(Menu::class)
			->findBy(['deletedDate' => null], ['name' => 'ASC']);

		$items = [];
		foreach ($menus as $menu) {
			$items[$menu->getId()] = $menu->getName();
		}

		return $items;
	}

	public function save(Menu $menu): void
	{
		$this->em->persist($menu);
		$this->em->flush();
	}

	/** Soft delete — nav blocks referencing the menu just stop showing it (LayoutBlock::getActiveMenus filters). */
	public function delete(int $id, ?User $deletedBy = null): void
	{
		$menu = $this->getById($id);
		if ($menu) {
			$menu->markDeleted($deletedBy);
			$this->em->flush();
		}
	}
}
