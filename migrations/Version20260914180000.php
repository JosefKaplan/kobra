<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260914180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Convert masonry content.items into masonry_item child components';
    }

    public function up(Schema $schema): void
    {
        $parents = $this->connection->fetchAllAssociative(
            "SELECT id, page_id, content, created_by, deleted_date, deleted_by FROM page_components WHERE type = 'masonry'",
        );

        foreach ($parents as $parent) {
            $content = json_decode((string) $parent['content'], true);
            $items = is_array($content['items'] ?? null) ? $content['items'] : [];

            $order = 0;
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $gallery = [];
                foreach (is_array($item['gallery'] ?? null) ? $item['gallery'] : [] as $photo) {
                    $url = is_array($photo) ? trim((string) ($photo['image_url'] ?? '')) : '';
                    if ($url !== '') {
                        $gallery[] = ['image_url' => $url, 'alt' => trim((string) ($photo['alt'] ?? ''))];
                    }
                }

                $this->addSql(
                    'INSERT INTO page_components (page_id, parent_id, type, content, sort_order, is_visible, spacing_top, spacing_bottom, created_by, deleted_date, deleted_by)'
                    . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [
                        $parent['page_id'],
                        $parent['id'],
                        'masonry_item',
                        json_encode([
                            'main_image_url' => trim((string) ($item['main_image_url'] ?? $item['image_url'] ?? '')),
                            'main_image_alt' => trim((string) ($item['main_image_alt'] ?? $item['alt'] ?? '')),
                            'heading'        => trim((string) ($item['heading'] ?? '')),
                            'text'           => trim((string) ($item['text'] ?? '')),
                            'gallery'        => $gallery,
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        $order++,
                        ($item['is_visible'] ?? true) ? 1 : 0,
                        'sm',
                        'sm',
                        $parent['created_by'],
                        $parent['deleted_date'],
                        $parent['deleted_by'],
                    ],
                );
            }

            $this->addSql('UPDATE page_components SET content = ? WHERE id = ?', ['[]', $parent['id']]);
        }
    }

    public function down(Schema $schema): void
    {
        $parents = $this->connection->fetchAllAssociative(
            "SELECT id, deleted_date FROM page_components WHERE type = 'masonry'",
        );

        foreach ($parents as $parent) {
            $children = $this->connection->fetchAllAssociative(
                "SELECT id, content, is_visible FROM page_components WHERE parent_id = ? AND type = 'masonry_item'"
                . ($parent['deleted_date'] === null ? ' AND deleted_date IS NULL' : '')
                . ' ORDER BY sort_order',
                [$parent['id']],
            );
            $items = [];
            foreach ($children as $child) {
                $content = json_decode((string) $child['content'], true) ?: [];
                $content['is_visible'] = (bool) $child['is_visible'];
                $items[] = $content;
            }
            $this->addSql(
                'UPDATE page_components SET content = ? WHERE id = ?',
                [json_encode(['items' => $items], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $parent['id']],
            );
            foreach ($children as $child) {
                $this->addSql('DELETE FROM page_components WHERE id = ?', [$child['id']]);
            }
        }
    }
}
