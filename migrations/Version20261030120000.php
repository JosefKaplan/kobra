<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * NASAZENÍ OBSAHU NA PRODUKCI — stránky, bloky, menu, nastavení a evidence obrázků z lokálu.
 *
 * Migrace vloží do prázdné databáze obsah z migrations/data/content-snapshot.json. Samotné obrázky
 * v ní nejsou, ty se nahrávají přes FTP (www/uploads/ není v gitu). Do databáze, kde už nějaký obsah
 * je (třeba lokální), nic nevloží: přeskočí se a ohlásí to. Uživatele nepřenáší, zůstává výchozí admin.
 *
 * Postup:
 *
 * 1. Lokálně dočisti obsah a zkontroluj Administrace → Obrázky: žádné „Chybí soubor“, „Nepoužitý“ ani
 *    „Cizí soubor“ (dokud nějaké jsou, export nic nezapíše) a žádné zbytečné „Jen ve skrytém obsahu“.
 *
 * 2. Vyexportuj obsah a vygeneruj náhledy (po každé další změně obsahu znovu, dokud migrace neběžela
 *    na produkci):
 *
 *        php bin/console app:export-content
 *
 * 3. Do config/local.neon dej údaje produkční DB a spusť migrace (poběží i základní schéma):
 *
 *        rm -rf temp/cache/nette.configurator/
 *        php bin/console migrations:migrate --no-interaction
 *
 *    Ve výpisu nesmí být „skipped“ — to znamená, že se obsah nevložil. Pak local.neon vrať zpět a cache
 *    smaž znovu. Bez přístupu k DB zvenčí viz DEPLOYMENT.md, §5.5.
 *
 *    POZOR NA POŘADÍ: obrázky nahrávej až po migraci a do administrace se před ní nepřihlašuj. Při
 *    návštěvě administrace běží denní úklid, který smaže soubory starší než 24 hodin, na které nic
 *    neodkazuje. Bez vloženého obsahu by to byly všechny obrázky (FTP klient může zachovat původní datum).
 *
 * 4. Přes FTP nahraj obsah lokálních složek www/uploads/ a www/thumbs/ do stejných složek na serveru,
 *    tedy tak, aby vznikly cesty www/uploads/2026/09/kobra-logo.png a www/thumbs/2026/09/kobra-g-velke.jpeg.webp:
 *
 *      - přenos v binárním režimu (FileZilla: Přenos → Typ přenosu → Binární), v ASCII se obrázky rozbijí,
 *      - .htaccess a .gitkeep v těch složkách patří na server taky, .DS_Store ne,
 *      - PHP musí mít do složek právo zápisu, jinak nepůjde nahrávat ani mazat: www/uploads/, www/thumbs/
 *        a jejich 2026/ a 2026/09/ na 775 (777, pokud PHP běží pod jiným uživatelem než FTP), soubory
 *        na 664 (666).
 *
 * 5. Na serveru smaž temp/cache/, přihlas se (admin / Admin123), hned změň heslo a v Administrace →
 *    Obrázky zkontroluj, že „Chybí soubor“ je 0. Nakonec projdi web bez přihlášení.
 */
final class Version20261030120000 extends AbstractMigration
{
    private const SNAPSHOT = __DIR__ . '/data/content-snapshot.json';

    public function getDescription(): string
    {
        return 'Seed an empty database with the site content (data/content-snapshot.json); images go by FTP — see the class docblock';
    }

    public function up(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        $existingRows = [
            'pages' => 'SELECT COUNT(*) FROM pages',
            'layout_blocks' => 'SELECT COUNT(*) FROM layout_blocks',
            'menus' => 'SELECT COUNT(*) FROM menus',
            'uploads' => 'SELECT COUNT(*) FROM uploads',
            // uploads_swept_at zapíše už první návštěva administrace a ve snapshotu není, takže nevadí
            'settings' => "SELECT COUNT(*) FROM settings WHERE name <> 'uploads_swept_at'",
        ];
        foreach ($existingRows as $table => $countSql) {
            // --dry-run základní migraci neprovede, takže v prázdné DB tabulky ještě nejsou — po ní by byly prázdné
            if (!$schemaManager->tablesExist([$table])) {
                continue;
            }
            $this->skipIf(
                (int) $this->connection->fetchOne($countSql) > 0,
                "Tabulka $table už obsahuje data, obsah se nevkládá. Na produkci nejdřív tabulky vyprázdni, lokálně je to v pořádku.",
            );
        }

        $snapshot = json_decode((string) file_get_contents(self::SNAPSHOT), true, flags: JSON_THROW_ON_ERROR);
        foreach ($snapshot['tables'] as $table => $rows) {
            foreach ($rows as $row) {
                $columns = implode(', ', array_map(fn (string $column): string => "`$column`", array_keys($row)));
                $placeholders = implode(', ', array_fill(0, count($row), '?'));
                $this->addSql("INSERT INTO $table ($columns) VALUES ($placeholders)", array_values($row));
            }
        }
    }

    public function postUp(Schema $schema): void
    {
        $this->write('Obrázky se nahrávají zvlášť přes FTP do www/uploads/ — postup je v Version20261030120000.php.');
    }

    public function down(Schema $schema): void
    {
        // lokálně se up() přeskakuje, takže by down() mazal i obsah, který tahle migrace nevložila
        $this->throwIrreversibleMigrationException('Obsah se migrací nevrací. Obnov zálohu databáze.');
    }
}
