<?php

declare(strict_types=1);

namespace App\Entity;

use App\Helpers\LinkList;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use LogicException;

/**
 * A reusable named list of links, managed under Admin:Menu. Nav layout blocks
 * reference menus (LayoutBlock::$menus); the front renders each one as a
 * dropdown in the navbar, labelled with the menu name.
 *
 * Content keys: ['links' => "Text | URL\n…"] — same format as nav blocks.
 */
#[ORM\Entity]
#[ORM\Table(name: 'menus')]
class Menu
{
    use AuditColumns;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    /** Shown both in the admin and as the dropdown label on the front. */
    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $content = [];

    public function __construct()
    {
        $this->createdDate = new DateTimeImmutable();
    }

    public function getId(): int
    {
        return $this->id ?? throw new LogicException('Menu has no ID until it is persisted.');
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /** @return array<string, mixed> */
    public function getContent(): array
    {
        return $this->content;
    }

    /** @param array<string, mixed> $content */
    public function setContent(array $content): self
    {
        $this->content = $content;
        return $this;
    }

    /** @return list<array{label: string, url: string}> */
    public function getLinks(): array
    {
        return LinkList::parse((string) ($this->content['links'] ?? ''));
    }
}
