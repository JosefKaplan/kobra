<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260927120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Convert masonry_item content.text from plain text to rich-text HTML';
    }

    public function up(Schema $schema): void
    {
        $this->convertTexts(static function (string $text): string {
            $text = trim(str_replace(["\r\n", "\r"], "\n", $text));
            // already saved by the rich-text editor (code deployed before this ran) — don't escape it twice
            if ($text === '' || preg_match('~^<(p|ol|ul|blockquote)>~', $text)) {
                return $text;
            }

            // Quill's own shape: one <p> per line, an empty line is <p><br></p>
            return implode('', array_map(
                static fn (string $line) => trim($line) === ''
                    ? '<p><br></p>'
                    : '<p>' . htmlspecialchars(trim($line), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401, 'UTF-8') . '</p>',
                explode("\n", $text),
            ));
        });
    }

    public function down(Schema $schema): void
    {
        $this->convertTexts(static function (string $html): string {
            $lines = array_map(
                static fn (string $block) => trim(html_entity_decode(strip_tags($block), ENT_QUOTES | ENT_HTML401, 'UTF-8')),
                preg_split('~</(?:p|li|blockquote)>~i', $html) ?: [],
            );

            return trim(implode("\n", $lines));
        });
    }

    /** @param callable(string): string $convert */
    private function convertTexts(callable $convert): void
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT id, content FROM page_components WHERE type = 'masonry_item'",
        );

        foreach ($rows as $row) {
            $content = json_decode((string) $row['content'], true);
            $text = is_array($content) ? (string) ($content['text'] ?? '') : '';
            $converted = trim($text) !== '' ? $convert($text) : $text;
            if ($converted === $text) {
                continue;
            }

            $content['text'] = $converted;
            $this->addSql(
                'UPDATE page_components SET content = ? WHERE id = ?',
                [json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $row['id']],
            );
        }
    }
}
