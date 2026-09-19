<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * A reusable navbar or footer, configured centrally in the admin (Admin:Layout)
 * and assigned to pages via Page::$navBlock / Page::$footerBlock dropdowns.
 *
 * Content keys by type: `nav` → ['brand' => string, 'links' => "Text | URL\n…"],
 * `footer` → ['text' => sanitized HTML, 'carousel' => [['image_url' => …, 'alt' => …, 'link_url' => …], …]]
 * (the carousel is an endless image marquee rendered above the text — see Front/@layout.latte).
 */
#[ORM\Entity]
#[ORM\Table(name: 'layout_blocks')]
class LayoutBlock
{
    use AuditColumns;

    public const TYPE_NAV = 'nav';
    public const TYPE_FOOTER = 'footer';
    public const TYPES = [self::TYPE_NAV, self::TYPE_FOOTER];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(type: 'string', length: 20)]
    private string $type = self::TYPE_NAV;

    /** Admin-facing label shown in the management grid and the page-form dropdowns. */
    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $content = [];

    /**
     * Dropdown menus shown in this navbar (nav blocks only; footers keep it empty).
     *
     * @var Collection<int, Menu>
     */
    #[ORM\ManyToMany(targetEntity: Menu::class)]
    #[ORM\JoinTable(name: 'layout_block_menus')]
    private Collection $menus;

    public function __construct()
    {
        $this->menus = new ArrayCollection();
        $this->createdDate = new \DateTimeImmutable();
    }

    public function getId(): int
    {
        return $this->id;
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

    /**
     * Menus that still exist (soft-deleted ones excluded) — what the front renders
     * and the edit form pre-checks.
     *
     * @return Menu[]
     */
    public function getActiveMenus(): array
    {
        return $this->menus
            ->filter(fn (Menu $m) => !$m->isDeleted())
            ->getValues();
    }

    /** @param Menu[] $menus */
    public function setMenus(array $menus): self
    {
        $this->menus->clear();
        foreach ($menus as $menu) {
            $this->menus->add($menu);
        }
        return $this;
    }
}
