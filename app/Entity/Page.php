<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use LogicException;

#[ORM\Entity]
#[ORM\Table(name: 'pages')]
class Page
{
    use AuditColumns;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $title;

    /** URL path of the page (single segment, no leading slash) — routed by RouterFactory's `<link>` route. */
    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private string $link;

    #[ORM\Column(name: 'is_visible', type: 'boolean', options: ['default' => true])]
    private bool $isVisible = true;

    /** @var Collection<int, PageComponent> */
    #[ORM\OneToMany(targetEntity: PageComponent::class, mappedBy: 'page', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['sortOrder' => 'ASC'])]
    private Collection $components;

    /** Navbar shown on this page; null means no navbar at all (deleting the block nulls it). */
    #[ORM\ManyToOne(targetEntity: LayoutBlock::class)]
    #[ORM\JoinColumn(name: 'nav_block_id', nullable: true, onDelete: 'SET NULL')]
    private ?LayoutBlock $navBlock = null;

    /** Footer shown on this page; null means no footer at all (deleting the block nulls it). */
    #[ORM\ManyToOne(targetEntity: LayoutBlock::class)]
    #[ORM\JoinColumn(name: 'footer_block_id', nullable: true, onDelete: 'SET NULL')]
    private ?LayoutBlock $footerBlock = null;

    public function __construct()
    {
        $this->components = new ArrayCollection();
        $this->createdDate = new DateTimeImmutable();
    }

    public function getId(): int
    {
        return $this->id ?? throw new LogicException('Page has no ID until it is persisted.');
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getLink(): string
    {
        return $this->link;
    }

    public function setLink(string $link): self
    {
        $this->link = $link;
        return $this;
    }

    public function isVisible(): bool
    {
        return $this->isVisible;
    }

    public function setIsVisible(bool $isVisible): self
    {
        $this->isVisible = $isVisible;
        return $this;
    }

    /** @return Collection<int, PageComponent> */
    public function getComponents(): Collection
    {
        return $this->components;
    }

    public function getNavBlock(): ?LayoutBlock
    {
        return $this->navBlock;
    }

    public function setNavBlock(?LayoutBlock $navBlock): self
    {
        $this->navBlock = $navBlock;
        return $this;
    }

    public function getFooterBlock(): ?LayoutBlock
    {
        return $this->footerBlock;
    }

    public function setFooterBlock(?LayoutBlock $footerBlock): self
    {
        $this->footerBlock = $footerBlock;
        return $this;
    }

    /**
     * Top-level components only — children of container components (columns, masonry) are
     * rendered by their parent, never in the page's main flow.
     *
     * @return PageComponent[]
     */
    public function getRootComponents(): array
    {
        return $this->components
            ->filter(fn (PageComponent $c) => $c->getParent() === null)
            ->getValues();
    }

    public function addComponent(PageComponent $component): self
    {
        if (!$this->components->contains($component)) {
            $this->components->add($component);
            $component->setPage($this);
        }
        return $this;
    }
}
