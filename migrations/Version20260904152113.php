<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds the `uploads` table (App\Entity\Upload) — an audit trail of images uploaded through
 * App\Helpers\ImageUploader (drag & drop / click-to-browse widget behind hero.image_url,
 * image.url and each masonry book's main_image_url / gallery image_url). Not a foreign key from those content fields —
 * they just hold the returned public URL as plain text; this table is a foundation for a future
 * media library (cleanup of orphaned files, a picker UI, …), nothing reads it yet.
 */
final class Version20260904152113 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add uploads table (App\Entity\Upload) — audit trail for admin image uploads';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE uploads (
                id INT AUTO_INCREMENT NOT NULL,
                path VARCHAR(500) NOT NULL,
                original_filename VARCHAR(255) NOT NULL,
                mime_type VARCHAR(100) NOT NULL,
                size INT NOT NULL,
                created_date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
                created_by INT DEFAULT NULL,
                edited_date DATETIME DEFAULT NULL,
                edited_by INT DEFAULT NULL,
                deleted_date DATETIME DEFAULT NULL,
                deleted_by INT DEFAULT NULL,
                INDEX IDX_96117F18DE12AB56 (created_by),
                INDEX IDX_96117F18D6A509BC (edited_by),
                INDEX IDX_96117F181F6FA0AF (deleted_by),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
            SQL);
        $this->addSql('ALTER TABLE uploads ADD CONSTRAINT FK_96117F18DE12AB56 FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE uploads ADD CONSTRAINT FK_96117F18D6A509BC FOREIGN KEY (edited_by) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE uploads ADD CONSTRAINT FK_96117F181F6FA0AF FOREIGN KEY (deleted_by) REFERENCES users (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS uploads');
    }
}
