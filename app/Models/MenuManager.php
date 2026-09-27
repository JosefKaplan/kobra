<?php

declare(strict_types=1);

namespace App\Models;

use App\Entity\Menu;
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
			->from(Menu::class, 'm');
	}

	public function getById(int $id): ?Menu
	{
		return $this->em->getRepository(Menu::class)->find($id);
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
			->findBy([], ['name' => 'ASC']);

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

	/** Hard delete — nav blocks using the menu lose its dropdown (layout_block_menus rows go ON DELETE CASCADE). */
	public function delete(int $id): void
	{
		$menu = $this->getById($id);
		if ($menu) {
			$this->em->remove($menu);
			$this->em->flush();
		}
	}
}
