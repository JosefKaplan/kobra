<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260927140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Masonry tiles are listed newest first (descending sort_order) — flip existing tile orders';
    }

    public function up(Schema $schema): void
    {
        $this->flipTileOrders();
    }

    public function down(Schema $schema): void
    {
        $this->flipTileOrders();
    }

    // Keeps what's on screen: the tile shown first gets the highest number. Its own inverse.
    private function flipTileOrders(): void
    {
        $tiles = $this->connection->fetchAllAssociative(
            'SELECT c.id, c.parent_id FROM page_components c'
            . ' JOIN page_components p ON p.id = c.parent_id'
            . " WHERE p.type = 'masonry'"
            . ' ORDER BY c.parent_id, c.sort_order, c.id',
        );

        $byParent = [];
        foreach ($tiles as $tile) {
            $byParent[$tile['parent_id']][] = $tile['id'];
        }

        foreach ($byParent as $ids) {
            $last = count($ids) - 1;
            foreach ($ids as $position => $id) {
                $this->addSql('UPDATE page_components SET sort_order = ? WHERE id = ?', [$last - $position, $id]);
            }
        }
    }
}
