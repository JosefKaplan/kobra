<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds PageComponent::$spacingBottom — an optional per-component override of the
 * type's built-in bottom margin, set from the "Mezera pod blokem" select in the editor.
 */
final class Version20260714115043 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add page_components.spacing_bottom (custom margin override between components)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE page_components ADD spacing_bottom VARCHAR(10) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE page_components DROP spacing_bottom');
    }
}
