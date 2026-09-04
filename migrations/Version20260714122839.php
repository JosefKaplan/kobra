<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds PageComponent::$spacingTop — the top-margin counterpart of $spacingBottom,
 * set from the "Mezera nad blokem" select in the editor.
 */
final class Version20260714122839 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add page_components.spacing_top (custom top margin override between components)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE page_components ADD spacing_top VARCHAR(10) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE page_components DROP spacing_top');
    }
}
