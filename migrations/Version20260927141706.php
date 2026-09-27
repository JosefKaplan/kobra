<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260927141706 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Hard delete everywhere: purge soft-deleted rows, drop deleted_date/deleted_by and users.deleted_at';
    }

    public function up(Schema $schema): void
    {
        // Purge what was soft-deleted (needs the deleted_* columns, so before dropping them). Their
        // uploaded files can't be deleted from here — migrations may run from a local machine against
        // the production DB — UploadCleaner::sweep() removes them on the next admin request.
        $this->addSql("DELETE FROM settings WHERE name = 'homepage_page_id' AND value IN (SELECT CAST(id AS CHAR) COLLATE utf8mb4_unicode_ci FROM pages WHERE deleted_date IS NOT NULL)");
        // components of a soft-deleted page weren't marked themselves; children go ON DELETE CASCADE
        $this->addSql('DELETE FROM page_components WHERE deleted_date IS NOT NULL OR page_id IN (SELECT id FROM pages WHERE deleted_date IS NOT NULL)');
        $this->addSql('DELETE FROM pages WHERE deleted_date IS NOT NULL');
        // pages.nav_block_id/footer_block_id → NULL, layout_block_menus rows go ON DELETE CASCADE
        $this->addSql('DELETE FROM layout_blocks WHERE deleted_date IS NOT NULL');
        $this->addSql('DELETE FROM menus WHERE deleted_date IS NOT NULL');
        $this->addSql('DELETE FROM uploads WHERE deleted_date IS NOT NULL');
        // created_by/edited_by → NULL; login_logs don't reference users (username string only) and stay
        $this->addSql('DELETE FROM users WHERE deleted_at IS NOT NULL');

        $this->addSql('ALTER TABLE layout_blocks DROP FOREIGN KEY `FK_B5D582BE1F6FA0AF`');
        $this->addSql('DROP INDEX IDX_B5D582BE1F6FA0AF ON layout_blocks');
        $this->addSql('ALTER TABLE layout_blocks DROP deleted_date, DROP deleted_by');
        $this->addSql('ALTER TABLE menus DROP FOREIGN KEY `FK_727508CF1F6FA0AF`');
        $this->addSql('DROP INDEX IDX_727508CF1F6FA0AF ON menus');
        $this->addSql('ALTER TABLE menus DROP deleted_date, DROP deleted_by');
        $this->addSql('ALTER TABLE page_components DROP FOREIGN KEY `FK_E5A8C463C4663E4`');
        $this->addSql('ALTER TABLE page_components DROP FOREIGN KEY `FK_E5A8C4631F6FA0AF`');
        $this->addSql('DROP INDEX IDX_E5A8C4631F6FA0AF ON page_components');
        $this->addSql('ALTER TABLE page_components DROP deleted_date, DROP deleted_by');
        $this->addSql('ALTER TABLE page_components ADD CONSTRAINT FK_E5A8C463C4663E4 FOREIGN KEY (page_id) REFERENCES pages (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE pages DROP FOREIGN KEY `FK_2074E5751F6FA0AF`');
        $this->addSql('DROP INDEX IDX_2074E5751F6FA0AF ON pages');
        $this->addSql('ALTER TABLE pages DROP deleted_date, DROP deleted_by');
        $this->addSql('ALTER TABLE uploads DROP FOREIGN KEY `FK_96117F181F6FA0AF`');
        $this->addSql('DROP INDEX IDX_96117F181F6FA0AF ON uploads');
        $this->addSql('ALTER TABLE uploads DROP deleted_date, DROP deleted_by');
        $this->addSql('ALTER TABLE users DROP deleted_at');
    }

    public function down(Schema $schema): void
    {
        // restores the columns only — purged rows are gone for good
        $this->addSql('ALTER TABLE layout_blocks ADD deleted_date DATETIME DEFAULT NULL, ADD deleted_by INT DEFAULT NULL');
        $this->addSql('ALTER TABLE layout_blocks ADD CONSTRAINT `FK_B5D582BE1F6FA0AF` FOREIGN KEY (deleted_by) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_B5D582BE1F6FA0AF ON layout_blocks (deleted_by)');
        $this->addSql('ALTER TABLE menus ADD deleted_date DATETIME DEFAULT NULL, ADD deleted_by INT DEFAULT NULL');
        $this->addSql('ALTER TABLE menus ADD CONSTRAINT `FK_727508CF1F6FA0AF` FOREIGN KEY (deleted_by) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_727508CF1F6FA0AF ON menus (deleted_by)');
        $this->addSql('ALTER TABLE pages ADD deleted_date DATETIME DEFAULT NULL, ADD deleted_by INT DEFAULT NULL');
        $this->addSql('ALTER TABLE pages ADD CONSTRAINT `FK_2074E5751F6FA0AF` FOREIGN KEY (deleted_by) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_2074E5751F6FA0AF ON pages (deleted_by)');
        $this->addSql('ALTER TABLE page_components DROP FOREIGN KEY FK_E5A8C463C4663E4');
        $this->addSql('ALTER TABLE page_components ADD deleted_date DATETIME DEFAULT NULL, ADD deleted_by INT DEFAULT NULL');
        $this->addSql('ALTER TABLE page_components ADD CONSTRAINT `FK_E5A8C463C4663E4` FOREIGN KEY (page_id) REFERENCES pages (id)');
        $this->addSql('ALTER TABLE page_components ADD CONSTRAINT `FK_E5A8C4631F6FA0AF` FOREIGN KEY (deleted_by) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_E5A8C4631F6FA0AF ON page_components (deleted_by)');
        $this->addSql('ALTER TABLE uploads ADD deleted_date DATETIME DEFAULT NULL, ADD deleted_by INT DEFAULT NULL');
        $this->addSql('ALTER TABLE uploads ADD CONSTRAINT `FK_96117F181F6FA0AF` FOREIGN KEY (deleted_by) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_96117F181F6FA0AF ON uploads (deleted_by)');
        $this->addSql('ALTER TABLE users ADD deleted_at DATETIME DEFAULT NULL');
    }
}
