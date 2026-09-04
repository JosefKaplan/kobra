<?php

declare(strict_types=1);

namespace App\Models;

use App\Entity\Page;
use App\Entity\PageComponent;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

class PageManager
{
	public function __construct(
		private readonly EntityManagerInterface $em,
	) {
	}

	public function findAll(): QueryBuilder
	{
		return $this->em->createQueryBuilder()
			->select('p')
			->from(Page::class, 'p')
			->where('p.deletedDate IS NULL');
	}

	public function getById(int $id): ?Page
	{
		return $this->em->getRepository(Page::class)->findOneBy(['id' => $id, 'deletedDate' => null]);
	}

    public function getByLink(string $link): ?Page
    {
        return $this->em->getRepository(Page::class)->findOneBy(['link' => $link, 'deletedDate' => null]);
    }

    /** @return Page[] */
    public function findVisible(): array
    {
        return $this->em->getRepository(Page::class)->findBy(['isVisible' => true, 'deletedDate' => null], ['title' => 'ASC']);
    }

    public function getComponentById(int $id): ?PageComponent
    {
        // A component of a soft-deleted page is gone too — signals must not reach it by id
        return $this->em->createQueryBuilder()
            ->select('c')
            ->from(PageComponent::class, 'c')
            ->join('c.page', 'p')
            ->where('c.id = :id')
            ->andWhere('c.deletedDate IS NULL')
            ->andWhere('p.deletedDate IS NULL')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function linkExists(string $link, ?int $excludeId = null): bool
    {
        // Deliberately counts soft-deleted pages too: they still hold their row under
        // the DB unique constraint on link, so their links stay unavailable.
        $qb = $this->em->createQueryBuilder()
            ->select('COUNT(p.id)')
            ->from(Page::class, 'p')
            ->where('p.link = :link')
            ->setParameter('link', $link);

        if ($excludeId !== null) {
            $qb->andWhere('p.id != :id')->setParameter('id', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Returns a link guaranteed to be unique by appending -2, -3, … when needed
     * (the same behaviour as WordPress' wp_unique_post_slug).
     */
    public function buildUniqueLink(string $link, ?int $excludeId = null): string
    {
        $base = $link;
        $i = 2;
        while ($this->linkExists($link, $excludeId)) {
            $link = $base . '-' . $i++;
        }

        return $link;
    }

	public function save(Page $page): void
	{
		$this->em->persist($page);
		$this->em->flush();
	}

	/** Soft delete — the row stays for history, every query filters it out via deletedDate. */
	public function delete(int $id, ?User $deletedBy = null): void
	{
		$page = $this->getById($id);
		if ($page) {
			$page->markDeleted($deletedBy);
			$this->em->flush();
		}
	}
}
