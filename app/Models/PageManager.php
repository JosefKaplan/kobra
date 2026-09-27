<?php

declare(strict_types=1);

namespace App\Models;

use App\Entity\Page;
use App\Entity\PageComponent;
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
			->from(Page::class, 'p');
	}

	public function getById(int $id): ?Page
	{
		return $this->em->getRepository(Page::class)->find($id);
	}

    public function getByLink(string $link): ?Page
    {
        return $this->em->getRepository(Page::class)->findOneBy(['link' => $link]);
    }

    /** @return Page[] */
    public function findVisible(): array
    {
        return $this->em->getRepository(Page::class)->findBy(['isVisible' => true], ['title' => 'ASC']);
    }

    public function getComponentById(int $id): ?PageComponent
    {
        return $this->em->getRepository(PageComponent::class)->find($id);
    }

    public function linkExists(string $link, ?int $excludeId = null): bool
    {
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

	/** Hard delete — the page's components go with it (ORM cascade), their images via UploadCleanupListener. */
	public function delete(int $id): void
	{
		$page = $this->getById($id);
		if ($page) {
			$this->em->remove($page);
			$this->em->flush();
		}
	}

	/** Hard delete — a container's children go with it (ORM cascade), images via UploadCleanupListener. */
	public function deleteComponent(PageComponent $component): void
	{
		$this->em->remove($component);
		$this->em->flush();
	}
}
