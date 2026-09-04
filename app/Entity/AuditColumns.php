<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * History/audit columns shared by CMS entities (Page, PageComponent).
 *
 * Rows are never hard-deleted: markDeleted() stamps deleted_date/deleted_by and every
 * query and collection accessor must filter deleted rows out (deletedDate IS NULL).
 * The "by" references survive user soft-deletes (users are soft-deleted themselves);
 * ON DELETE SET NULL only fires on a hard DB delete of the user row.
 */
trait AuditColumns
{
    #[ORM\Column(name: 'created_date', type: 'datetime_immutable', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeImmutable $createdDate;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by', nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\Column(name: 'edited_date', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $editedDate = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'edited_by', nullable: true, onDelete: 'SET NULL')]
    private ?User $editedBy = null;

    #[ORM\Column(name: 'deleted_date', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deletedDate = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'deleted_by', nullable: true, onDelete: 'SET NULL')]
    private ?User $deletedBy = null;

    public function getCreatedDate(): \DateTimeImmutable
    {
        return $this->createdDate;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function getEditedDate(): ?\DateTimeImmutable
    {
        return $this->editedDate;
    }

    public function getEditedBy(): ?User
    {
        return $this->editedBy;
    }

    public function getDeletedDate(): ?\DateTimeImmutable
    {
        return $this->deletedDate;
    }

    public function getDeletedBy(): ?User
    {
        return $this->deletedBy;
    }

    public function isDeleted(): bool
    {
        return $this->deletedDate !== null;
    }

    public function markCreated(?User $by): self
    {
        $this->createdDate = new \DateTimeImmutable();
        $this->createdBy = $by;
        return $this;
    }

    public function markEdited(?User $by): self
    {
        $this->editedDate = new \DateTimeImmutable();
        $this->editedBy = $by;
        return $this;
    }

    public function markDeleted(?User $by): self
    {
        $this->deletedDate = new \DateTimeImmutable();
        $this->deletedBy = $by;
        return $this;
    }
}
