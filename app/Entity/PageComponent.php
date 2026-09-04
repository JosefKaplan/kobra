<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'page_components')]
class PageComponent
{
    use AuditColumns;

    /** Allowed values of $spacingTop/$spacingBottom mapped to the CSS length they override the type's default margin with. */
    public const SPACING_VALUES = [
        'none' => '0',
        'sm'   => '.75rem',
        'lg'   => '4rem',
        'xl'   => '6rem',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Page::class, inversedBy: 'components')]
    #[ORM\JoinColumn(nullable: false)]
    private Page $page;

    /** Container component (e.g. `columns`) this component is nested in; null for top-level components. */
    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id', nullable: true, onDelete: 'CASCADE')]
    private ?PageComponent $parent = null;

    /** @var Collection<int, PageComponent> */
    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: self::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['sortOrder' => 'ASC'])]
    private Collection $children;

    #[ORM\Column(type: 'string', length: 50)]
    private string $type = 'text_block';

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $content = [];

    #[ORM\Column(name: 'sort_order', type: 'integer', options: ['default' => 0])]
    private int $sortOrder = 0;

    #[ORM\Column(name: 'is_visible', type: 'boolean', options: ['default' => true])]
    private bool $isVisible = true;

    /** Override of the type's built-in top margin; null = use the type default. One of self::SPACING_VALUES. */
    #[ORM\Column(name: 'spacing_top', type: 'string', length: 10, nullable: true)]
    private ?string $spacingTop = null;

    /** Override of the type's built-in bottom margin; null = use the type default. One of self::SPACING_VALUES. */
    #[ORM\Column(name: 'spacing_bottom', type: 'string', length: 10, nullable: true)]
    private ?string $spacingBottom = null;

    public function __construct()
    {
        $this->children = new ArrayCollection();
        $this->createdDate = new \DateTimeImmutable();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getPage(): Page
    {
        return $this->page;
    }

    public function getParent(): ?PageComponent
    {
        return $this->parent;
    }

    public function setParent(?PageComponent $parent): self
    {
        $this->parent = $parent;
        return $this;
    }

    /** @return Collection<int, PageComponent> */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    /**
     * Children that still exist (soft-deleted ones excluded) — what the app should
     * render, count and reorder. getChildren() stays raw for collection management.
     *
     * @return PageComponent[]
     */
    public function getActiveChildren(): array
    {
        return $this->children
            ->filter(fn (PageComponent $c) => !$c->isDeleted())
            ->getValues();
    }

    public function addChild(PageComponent $child): self
    {
        if (!$this->children->contains($child)) {
            $this->children->add($child);
            $child->setParent($this);
        }
        return $this;
    }


    public function setPage(Page $page): self
    {
        $this->page = $page;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
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

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): self
    {
        $this->sortOrder = $sortOrder;
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

    public function getSpacingTop(): ?string
    {
        return $this->spacingTop;
    }

    public function setSpacingTop(?string $spacingTop): self
    {
        $this->spacingTop = $spacingTop;
        return $this;
    }

    public function getSpacingBottom(): ?string
    {
        return $this->spacingBottom;
    }

    public function setSpacingBottom(?string $spacingBottom): self
    {
        $this->spacingBottom = $spacingBottom;
        return $this;
    }
}
