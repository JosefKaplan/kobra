<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * One image file uploaded through the admin (App\Helpers\ImageUploader) and stored under
 * www/uploads/. Independent of where/whether it's actually referenced — content fields that
 * point at an upload (e.g. masonry.items[].main_image_url / .gallery[].image_url, hero.image_url, image.url) just hold its
 * public URL as a plain string, not a foreign key. This table exists purely as an audit trail /
 * foundation for a future media library (cleanup of orphaned files, a picker UI, …); nothing
 * reads it yet beyond ImageUploader writing to it.
 */
#[ORM\Entity]
#[ORM\Table(name: 'uploads')]
class Upload
{
    use AuditColumns;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    /** Relative to www/uploads/, e.g. "2026/09/nazev-a1b2c3d4.jpg" — getUrl() prefixes it with /uploads/. */
    #[ORM\Column(type: 'string', length: 500)]
    private string $path;

    /** Client-supplied filename, kept only for display — never used to build $path. */
    #[ORM\Column(name: 'original_filename', type: 'string', length: 255)]
    private string $originalFilename;

    #[ORM\Column(name: 'mime_type', type: 'string', length: 100)]
    private string $mimeType;

    /** Bytes. */
    #[ORM\Column(type: 'integer')]
    private int $size;

    public function __construct()
    {
        $this->createdDate = new \DateTimeImmutable();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): self
    {
        $this->path = $path;
        return $this;
    }

    public function getOriginalFilename(): string
    {
        return $this->originalFilename;
    }

    public function setOriginalFilename(string $originalFilename): self
    {
        $this->originalFilename = $originalFilename;
        return $this;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): self
    {
        $this->mimeType = $mimeType;
        return $this;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function setSize(int $size): self
    {
        $this->size = $size;
        return $this;
    }

    /** Public URL under the front web root. */
    public function getUrl(): string
    {
        return '/uploads/' . $this->path;
    }
}