<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Created/edited audit columns shared by CMS entities (Page, PageComponent, LayoutBlock, Menu, Upload).
 *
 * There is no deleted state: deleting is always a hard DELETE of the row. The "by" references
 * are ON DELETE SET NULL, so deleting a user just blanks them.
 */
trait AuditColumns
{
    #[ORM\Column(name: 'created_date', type: 'datetime_immutable', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private DateTimeImmutable $createdDate;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by', nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\Column(name: 'edited_date', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $editedDate = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'edited_by', nullable: true, onDelete: 'SET NULL')]
    private ?User $editedBy = null;

    public function getCreatedDate(): DateTimeImmutable
    {
        return $this->createdDate;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function getEditedDate(): ?DateTimeImmutable
    {
        return $this->editedDate;
    }

    public function getEditedBy(): ?User
    {
        return $this->editedBy;
    }

    public function markCreated(?User $by): self
    {
        $this->createdDate = new DateTimeImmutable();
        $this->createdBy = $by;
        return $this;
    }

    public function markEdited(?User $by): self
    {
        $this->editedDate = new DateTimeImmutable();
        $this->editedBy = $by;
        return $this;
    }
}
