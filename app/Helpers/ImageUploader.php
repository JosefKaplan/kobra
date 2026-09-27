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

class ImageUploader
{
    public const MAX_SIZE = 8 * 1024 * 1024;
    private const ALLOWED_TYPES = [ImageType::JPEG, ImageType::PNG, ImageType::GIF, ImageType::WEBP];
    private const MAX_BASENAME_LENGTH = 80;

    public function __construct(
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
            if (in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
                throw new ImageUploadException('Soubor je příliš velký pro aktuální nastavení serveru (upload_max_filesize/post_max_size v php.ini).');
            }
            throw new ImageUploadException('Nahrávání souboru selhalo.');
        }
        if ($file->getSize() > self::MAX_SIZE) {
            throw new ImageUploadException('Soubor je příliš velký (max ' . (int) (self::MAX_SIZE / 1024 / 1024) . ' MB).');
        }

        $imageInfo = @getimagesize($file->getTemporaryFile());
        if ($imageInfo === false || !in_array($imageInfo[2], self::ALLOWED_TYPES, true)) {
            throw new ImageUploadException('Nepodporovaný formát obrázku. Povoleno: JPG, PNG, GIF, WEBP.');
        }

        $extension = Image::typeToExtension($imageInfo[2]);
        $baseName = Strings::truncate(pathinfo($file->getSanitizedName(), PATHINFO_FILENAME), self::MAX_BASENAME_LENGTH, '');
        $filename = ($baseName !== '' ? $baseName : 'obrazek') . '-' . Random::generate(8, '0-9a-f') . '.' . $extension;
        $relativePath = date('Y/m') . '/' . $filename;

        try {
            $file->move($this->uploadsDir . '/' . $relativePath);
        } catch (\Throwable $e) {
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
            @unlink($this->uploadsDir . '/' . $relativePath);
            throw new ImageUploadException('Uložení souboru se nezdařilo.', previous: $e);
        }

        return $upload;
    }
}