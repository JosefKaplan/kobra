<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Entity\Upload;
use App\Entity\User;
use App\Models\UploadManager;
use Nette\Http\FileUpload;
use Nette\Utils\Image;
use Nette\Utils\ImageType;
use Nette\Utils\Random;
use Nette\Utils\Strings;

/**
 * Validates and stores one uploaded image under www/uploads/, and records it in the `uploads`
 * table (App\Entity\Upload — an audit trail / foundation for a future media library, not
 * something content fields reference by id; they just hold the returned getUrl() as plain text).
 *
 * Deliberately image-only, GD-decodable raster formats (no SVG — inline SVG can carry
 * <script>/event-handler XSS, so it's excluded rather than sanitized).
 */
class ImageUploader
{
    /** Bytes. Generous for photos without being unbounded; raise here if it turns out too small. */
    public const MAX_SIZE = 8 * 1024 * 1024;

    private const ALLOWED_TYPES = [ImageType::JPEG, ImageType::PNG, ImageType::GIF, ImageType::WEBP];

    /** Keeps the generated filename well under filesystem path-component limits (~255 bytes). */
    private const MAX_BASENAME_LENGTH = 80;

    public function __construct(
        /** Absolute path to www/uploads (%wwwDir%/uploads, see config/services.neon). */
        private readonly string $uploadsDir,
        private readonly UploadManager $uploadManager,
    ) {
    }

    /** @throws ImageUploadException */
    public function upload(FileUpload $file, ?User $uploadedBy): Upload
    {
        if (!$file->hasFile()) {
            throw new ImageUploadException('Nebyl vybrán žádný soubor.');
        }
        if (!$file->isOk()) {
            // PHP itself rejects an oversized upload (based on php.ini's upload_max_filesize /
            // post_max_size) before our code — and our own MAX_SIZE check below — ever runs; without
            // this, that case surfaced as a generic, misleading "upload failed" with no size mentioned.
            if (in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
                throw new ImageUploadException('Soubor je příliš velký pro aktuální nastavení serveru (upload_max_filesize/post_max_size v php.ini).');
            }
            throw new ImageUploadException('Nahrávání souboru selhalo.');
        }
        if ($file->getSize() > self::MAX_SIZE) {
            throw new ImageUploadException('Soubor je příliš velký (max ' . (int) (self::MAX_SIZE / 1024 / 1024) . ' MB).');
        }

        // Type is determined from the file's actual signature (getimagesize reads the header),
        // never from the client-supplied name or Content-Type — both are easily spoofed.
        $imageInfo = @getimagesize($file->getTemporaryFile());
        if ($imageInfo === false || !in_array($imageInfo[2], self::ALLOWED_TYPES, true)) {
            throw new ImageUploadException('Nepodporovaný formát obrázku. Povoleno: JPG, PNG, GIF, WEBP.');
        }

        $extension = Image::typeToExtension($imageInfo[2]);
        // getUntrustedName()/getSanitizedName() are exactly that — untrusted; a client can send an
        // arbitrarily long filename, which would otherwise blow past filesystem path-component
        // limits (move() below) and the DB column (Upload::$originalFilename is VARCHAR(255)).
        $baseName = Strings::truncate(pathinfo($file->getSanitizedName(), PATHINFO_FILENAME), self::MAX_BASENAME_LENGTH, '');
        $filename = ($baseName !== '' ? $baseName : 'obrazek') . '-' . Random::generate(8, '0-9a-f') . '.' . $extension;
        // Date-based subdirectories keep any one directory from accumulating thousands of files.
        $relativePath = date('Y/m') . '/' . $filename;

        try {
            $file->move($this->uploadsDir . '/' . $relativePath);
        } catch (\Throwable $e) {
            // Anything here (disk full, permissions, …) must still come back as JSON from
            // PagePresenter::handleUploadImage(), never an uncaught exception / HTML error page.
            throw new ImageUploadException('Uložení souboru se nezdařilo.', previous: $e);
        }

        try {
            $upload = new Upload();
            $upload->setPath($relativePath);
            $upload->setOriginalFilename(Strings::truncate($file->getUntrustedName(), 255, ''));
            $upload->setMimeType(Image::typeToMimeType($imageInfo[2]));
            $upload->setSize($file->getSize());
            $upload->markCreated($uploadedBy);
            $this->uploadManager->save($upload);
        } catch (\Throwable $e) {
            // The file already landed on disk (move() above succeeded) before this failed — remove
            // it rather than leaving an orphaned file with no `uploads` row, which would defeat the
            // audit trail this table exists for and silently waste disk space on every retry.
            @unlink($this->uploadsDir . '/' . $relativePath);
            throw new ImageUploadException('Uložení souboru se nezdařilo.', previous: $e);
        }

        return $upload;
    }
}