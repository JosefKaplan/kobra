<?php

declare(strict_types=1);

namespace App\Console;

use App\Helpers\Thumbnailer;
use App\Models\UploadBrowser;
use App\Models\UploadCleaner;
use Doctrine\DBAL\Connection;
use Nette\Utils\FileSystem;
use Nette\Utils\Json;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Exports the local CMS content into the file the production seed migration inserts
 * (migrations/Version20261030120000.php — the deployment steps are in its docblock).
 * Run again after every content change, as long as that migration hasn't reached production.
 *
 * @phpstan-import-type UploadFile from UploadBrowser
 */
#[AsCommand(name: 'app:export-content', description: 'Exportuje obsah webu pro nasazovací migraci (migrations/data/content-snapshot.json)')]
final class ExportContentCommand extends Command
{
	/**
	 * Files that would break the site or go to the server for nothing; while any is left, the snapshot
	 * isn't written. With them gone, all of www/uploads/ is what the content uses — the FTP step can take
	 * the whole folder. status => what to do about it
	 */
	private const BLOCKING_STATUSES = [
		UploadBrowser::STATUS_MISSING => 'vrať soubor na místo, nebo ho v Administrace → Obrázky smaž (použitý nejdřív odeber z obsahu)',
		UploadBrowser::STATUS_UNUSED => 'smaž v Administrace → Obrázky (na serveru by ho stejně hned smazal denní úklid)',
		UploadBrowser::STATUS_FOREIGN => 'přesuň z www/uploads/ pryč (obsah na něj odkazovat nemůže a z webu nejde smazat)',
	];

	public function __construct(
		private readonly string $snapshotFile,
		private readonly Connection $connection,
		private readonly UploadBrowser $uploadBrowser,
		private readonly Thumbnailer $thumbnailer,
	) {
		parent::__construct();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$files = $this->uploadBrowser->all();
		if (!$this->checkFiles($files, $output)) {
			$output->writeln('<error>Obsah se nezapsal.</error> Ukliď uvedené soubory a spusť export znovu.');
			return Command::FAILURE;
		}

		// the order is the migration's insert order: foreign key targets first, root components before their children
		$tables = [
			'layout_blocks' => $this->rows('SELECT * FROM layout_blocks ORDER BY id'),
			'menus' => $this->rows('SELECT * FROM menus ORDER BY id'),
			'layout_block_menus' => $this->rows('SELECT * FROM layout_block_menus ORDER BY layout_block_id, menu_id'),
			'pages' => $this->rows('SELECT * FROM pages ORDER BY id'),
			'page_components' => $this->rows('SELECT * FROM page_components ORDER BY parent_id IS NOT NULL, id'),
			'uploads' => $this->rows('SELECT * FROM uploads ORDER BY id'),
			'settings' => $this->rows('SELECT name, value FROM settings WHERE name <> ? ORDER BY name', [UploadCleaner::SWEPT_AT_SETTING]),
		];

		FileSystem::write($this->snapshotFile, Json::encode(['tables' => $tables], pretty: true, asciiSafe: false) . "\n");

		foreach ($tables as $table => $rows) {
			$output->writeln(sprintf('%-20s %d', $table, count($rows)));
		}
		$output->writeln('<info>Zapsáno do ' . $this->snapshotFile . '</info>');

		$this->report($files, $output);

		return Command::SUCCESS;
	}

	/**
	 * @param list<mixed> $params
	 * @return list<array<string, mixed>>
	 */
	private function rows(string $sql, array $params = []): array
	{
		$rows = $this->connection->fetchAllAssociative($sql, $params);
		foreach ($rows as &$row) {
			// the audit "by" columns point at local users, which don't exist on another server
			foreach (['created_by', 'edited_by'] as $column) {
				if (array_key_exists($column, $row)) {
					$row[$column] = null;
				}
			}
		}

		return $rows;
	}

	/**
	 * Lists the files of BLOCKING_STATUSES; false when there are any.
	 *
	 * @param list<UploadFile> $files
	 */
	private function checkFiles(array $files, OutputInterface $output): bool
	{
		$ok = true;
		foreach (self::BLOCKING_STATUSES as $status => $advice) {
			$blocking = array_filter($files, fn (array $file): bool => $file['status'] === $status);
			if ($blocking === []) {
				continue;
			}
			$ok = false;
			$output->writeln('<error>' . UploadBrowser::STATUSES[$status] . '</error> — ' . $advice . ':');
			foreach ($blocking as $file) {
				$output->writeln('  www/uploads/' . $file['path']);
			}
		}

		return $ok;
	}

	/**
	 * What goes to the server by FTP: after checkFiles(), everything in www/uploads/.
	 *
	 * @param list<UploadFile> $files
	 */
	private function report(array $files, OutputInterface $output): void
	{
		foreach ($files as $file) {
			if ($file['status'] === UploadBrowser::STATUS_HIDDEN) {
				$output->writeln('<comment>' . UploadBrowser::STATUSES[$file['status']] . ':</comment> www/uploads/' . $file['path']);
			}
		}

		$thumbs = $this->thumbnailer->generateAll();
		foreach ($thumbs['failed'] as $path) {
			$output->writeln('<comment>Náhled se nepovedl (web ukáže originál):</comment> www/uploads/' . $path);
		}
		$output->writeln(sprintf(
			'Přes FTP nahraj obsah www/uploads/ (%d souborů, %s) a www/thumbs/ (%d náhledů, %s) — až po migraci.',
			count($files),
			UploadBrowser::formatSize(array_sum(array_column($files, 'size'))),
			$thumbs['ok'],
			UploadBrowser::formatSize($thumbs['bytes']),
		));
	}
}
