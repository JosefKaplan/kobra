<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260904120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Kobra baseline (MariaDB): users, login_logs, pages, page_components, '
            . 'settings, layout_blocks, menus, layout_block_menus + default admin account';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE users (
                id INT AUTO_INCREMENT NOT NULL,
                username VARCHAR(255) NOT NULL,
                first_name VARCHAR(255) NOT NULL,
                last_name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL,
                phone VARCHAR(20) NOT NULL,
                password VARCHAR(255) NOT NULL,
                role VARCHAR(10) DEFAULT 'user' NOT NULL,
                is_active TINYINT(1) DEFAULT 1 NOT NULL,
                deleted_at DATETIME DEFAULT NULL,
                UNIQUE INDEX UNIQ_1483A5E9F85E0677 (username),
                UNIQUE INDEX UNIQ_1483A5E9E7927C74 (email),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE login_logs (
                id INT AUTO_INCREMENT NOT NULL,
                username_attempt VARCHAR(255) NOT NULL,
                is_success TINYINT(1) NOT NULL,
                ip_address VARCHAR(45) NOT NULL,
                user_agent VARCHAR(255) NOT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE layout_blocks (
                id INT AUTO_INCREMENT NOT NULL,
                type VARCHAR(20) NOT NULL,
                name VARCHAR(255) NOT NULL,
                content JSON NOT NULL,
                created_date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
                created_by INT DEFAULT NULL,
                edited_date DATETIME DEFAULT NULL,
                edited_by INT DEFAULT NULL,
                deleted_date DATETIME DEFAULT NULL,
                deleted_by INT DEFAULT NULL,
                INDEX IDX_B5D582BEDE12AB56 (created_by),
                INDEX IDX_B5D582BED6A509BC (edited_by),
                INDEX IDX_B5D582BE1F6FA0AF (deleted_by),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
            SQL);
        $this->addSql('ALTER TABLE layout_blocks ADD CONSTRAINT FK_B5D582BEDE12AB56 FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE layout_blocks ADD CONSTRAINT FK_B5D582BED6A509BC FOREIGN KEY (edited_by) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE layout_blocks ADD CONSTRAINT FK_B5D582BE1F6FA0AF FOREIGN KEY (deleted_by) REFERENCES users (id) ON DELETE SET NULL');

        $this->addSql(<<<'SQL'
            CREATE TABLE pages (
                id INT AUTO_INCREMENT NOT NULL,
                title VARCHAR(255) NOT NULL,
                link VARCHAR(255) NOT NULL,
                is_visible TINYINT(1) DEFAULT 1 NOT NULL,
                nav_block_id INT DEFAULT NULL,
                footer_block_id INT DEFAULT NULL,
                created_date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
                created_by INT DEFAULT NULL,
                edited_date DATETIME DEFAULT NULL,
                edited_by INT DEFAULT NULL,
                deleted_date DATETIME DEFAULT NULL,
                deleted_by INT DEFAULT NULL,
                UNIQUE INDEX UNIQ_2074E57536AC99F1 (link),
                INDEX IDX_2074E575F19F169C (nav_block_id),
                INDEX IDX_2074E575918AA0D4 (footer_block_id),
                INDEX IDX_2074E575DE12AB56 (created_by),
                INDEX IDX_2074E575D6A509BC (edited_by),
                INDEX IDX_2074E5751F6FA0AF (deleted_by),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
            SQL);
        $this->addSql('ALTER TABLE pages ADD CONSTRAINT FK_2074E575F19F169C FOREIGN KEY (nav_block_id) REFERENCES layout_blocks (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE pages ADD CONSTRAINT FK_2074E575918AA0D4 FOREIGN KEY (footer_block_id) REFERENCES layout_blocks (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE pages ADD CONSTRAINT FK_2074E575DE12AB56 FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE pages ADD CONSTRAINT FK_2074E575D6A509BC FOREIGN KEY (edited_by) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE pages ADD CONSTRAINT FK_2074E5751F6FA0AF FOREIGN KEY (deleted_by) REFERENCES users (id) ON DELETE SET NULL');

        $this->addSql(<<<'SQL'
            CREATE TABLE page_components (
                id INT AUTO_INCREMENT NOT NULL,
                type VARCHAR(50) NOT NULL,
                content JSON NOT NULL,
                sort_order INT DEFAULT 0 NOT NULL,
                is_visible TINYINT(1) DEFAULT 1 NOT NULL,
                spacing_top VARCHAR(10) DEFAULT NULL,
                spacing_bottom VARCHAR(10) DEFAULT NULL,
                page_id INT NOT NULL,
                parent_id INT DEFAULT NULL,
                created_date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
                created_by INT DEFAULT NULL,
                edited_date DATETIME DEFAULT NULL,
                edited_by INT DEFAULT NULL,
                deleted_date DATETIME DEFAULT NULL,
                deleted_by INT DEFAULT NULL,
                INDEX IDX_E5A8C463C4663E4 (page_id),
                INDEX IDX_E5A8C463727ACA70 (parent_id),
                INDEX IDX_E5A8C463DE12AB56 (created_by),
                INDEX IDX_E5A8C463D6A509BC (edited_by),
                INDEX IDX_E5A8C4631F6FA0AF (deleted_by),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
            SQL);
        $this->addSql('ALTER TABLE page_components ADD CONSTRAINT FK_E5A8C463C4663E4 FOREIGN KEY (page_id) REFERENCES pages (id)');
        $this->addSql('ALTER TABLE page_components ADD CONSTRAINT FK_E5A8C463727ACA70 FOREIGN KEY (parent_id) REFERENCES page_components (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE page_components ADD CONSTRAINT FK_E5A8C463DE12AB56 FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE page_components ADD CONSTRAINT FK_E5A8C463D6A509BC FOREIGN KEY (edited_by) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE page_components ADD CONSTRAINT FK_E5A8C4631F6FA0AF FOREIGN KEY (deleted_by) REFERENCES users (id) ON DELETE SET NULL');

        $this->addSql(<<<'SQL'
            CREATE TABLE menus (
                id INT AUTO_INCREMENT NOT NULL,
                name VARCHAR(255) NOT NULL,
                content JSON NOT NULL,
                created_date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
                created_by INT DEFAULT NULL,
                edited_date DATETIME DEFAULT NULL,
                edited_by INT DEFAULT NULL,
                deleted_date DATETIME DEFAULT NULL,
                deleted_by INT DEFAULT NULL,
                INDEX IDX_727508CFDE12AB56 (created_by),
                INDEX IDX_727508CFD6A509BC (edited_by),
                INDEX IDX_727508CF1F6FA0AF (deleted_by),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
            SQL);
        $this->addSql('ALTER TABLE menus ADD CONSTRAINT FK_727508CFDE12AB56 FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE menus ADD CONSTRAINT FK_727508CFD6A509BC FOREIGN KEY (edited_by) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE menus ADD CONSTRAINT FK_727508CF1F6FA0AF FOREIGN KEY (deleted_by) REFERENCES users (id) ON DELETE SET NULL');

        $this->addSql(<<<'SQL'
            CREATE TABLE layout_block_menus (
                layout_block_id INT NOT NULL,
                menu_id INT NOT NULL,
                INDEX IDX_F84CA861F79E0C3B (layout_block_id),
                INDEX IDX_F84CA861CCD7E912 (menu_id),
                PRIMARY KEY (layout_block_id, menu_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
            SQL);
        $this->addSql('ALTER TABLE layout_block_menus ADD CONSTRAINT FK_F84CA861F79E0C3B FOREIGN KEY (layout_block_id) REFERENCES layout_blocks (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE layout_block_menus ADD CONSTRAINT FK_F84CA861CCD7E912 FOREIGN KEY (menu_id) REFERENCES menus (id) ON DELETE CASCADE');

        $this->addSql(<<<'SQL'
            CREATE TABLE settings (
                id INT AUTO_INCREMENT NOT NULL,
                name VARCHAR(64) NOT NULL,
                value LONGTEXT DEFAULT NULL,
                UNIQUE INDEX UNIQ_E545A0C55E237E06 (name),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            INSERT INTO users (username, first_name, last_name, email, phone, password, role, is_active) VALUES
            ('admin', 'Hlavní', 'Administrátor', 'admin@example.com', '+420123456789', '$2y$10$TDs.8sre4gMLG/6rGBPDJus6Wy.huo0TWUUaEGxdDLktprZOeF9hu', 'admin', 1)
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS layout_block_menus');
        $this->addSql('DROP TABLE IF EXISTS menus');
        $this->addSql('DROP TABLE IF EXISTS page_components');
        $this->addSql('DROP TABLE IF EXISTS pages');
        $this->addSql('DROP TABLE IF EXISTS layout_blocks');
        $this->addSql('DROP TABLE IF EXISTS settings');
        $this->addSql('DROP TABLE IF EXISTS login_logs');
        $this->addSql('DROP TABLE IF EXISTS users');
    }
}