<?php

declare(strict_types=1);

namespace App\Models;

use Doctrine\DBAL\Connection;
use Tracy\Debugger;
use Tracy\ILogger;

/**
 * Keeps www/uploads/ and the `uploads` table in step with the content: an uploaded image nothing
 * refers to any more is deleted, file and row. Content holds uploads as plain URL strings (no FK),
 * so "referenced" means its /uploads/<path> appears anywhere in the JSON content of a page
 * component, layout block or menu.
 *
 * release() runs right after a flush that took images out of the content (UploadCleanupListener);
 * sweep() catches the rest — uploads that never made it into a saved form — once a day from the
 * admin (the hosting has no cron).
 */
final class UploadCleaner
{
	/** A younger unreferenced upload may still sit in a form that hasn't been saved yet. */
	public const SWEEP_MIN_AGE = 24 * 3600;

	private const SWEPT_AT_SETTING = 'uploads_swept_at';

	/** What ImageUploader creates: /uploads/<year>/<month>/<name>.<ext> — captures the part under www/uploads/. */
	private const PATH_PATTERN = '~/uploads/(\d{4}/\d{2}/[\w-][\w.-]*\.(?:jpe?g|png|gif|webp))~i';

	public function __construct(
		private readonly string $uploadsDir,
		private readonly Connection $connection,
		private readonly SettingsManager $settingsManager,
	) {
	}

	/**
	 * Upload paths (relative to www/uploads/) mentioned anywhere in a content value.
	 *
	 * @return list<string>
	 */
	public static function pathsIn(mixed $value): array
	{
		if (is_array($value)) {
			return array_values(array_unique(array_merge([], ...array_map(self::pathsIn(...), array_values($value)))));
		}
		if (!is_string($value) || !preg_match_all(self::PATH_PATTERN, $value, $matches)) {
			return [];
		}

		return array_values(array_unique($matches[1]));
	}

	/**
	 * Deletes those of the given uploads nothing refers to any more.
	 *
	 * @param list<string> $paths
	 */
	public function release(array $paths): void
	{
		$referenced = $this->referencedPaths();
		foreach (array_unique($paths) as $path) {
			if (!isset($referenced[$path])) {
				$this->delete($path);
			}
		}
	}

	/** sweep(), at most once a day. */
	public function sweepDaily(): void
	{
		if ((int) $this->settingsManager->get(self::SWEPT_AT_SETTING, '0') > time() - 24 * 3600) {
			return;
		}
		$this->settingsManager->set(self::SWEPT_AT_SETTING, (string) time());
		$this->sweep();
	}

	/**
	 * Deletes every unreferenced upload older than SWEEP_MIN_AGE: rows (with their file, if any)
	 * and files ImageUploader left without a row.
	 */
	public function sweep(): int
	{
		$referenced = $this->referencedPaths();
		$cutoff = time() - self::SWEEP_MIN_AGE;
		$deleted = 0;

		/** @var array<string, string> $rows path => created_date */
		$rows = $this->connection->fetchAllKeyValue('SELECT path, created_date FROM uploads');
		foreach ($rows as $path => $createdDate) {
			if (!isset($referenced[$path]) && strtotime($createdDate) < $cutoff) {
				$this->delete((string) $path);
				$deleted++;
			}
		}

		foreach (glob($this->uploadsDir . '/[0-9][0-9][0-9][0-9]/[0-9][0-9]/*') ?: [] as $file) {
			$path = substr($file, strlen($this->uploadsDir) + 1);
			if (!isset($rows[$path]) && !isset($referenced[$path]) && self::isUploadPath($path)
				&& is_file($file) && filemtime($file) < $cutoff
			) {
				$this->delete($path);
				$deleted++;
			}
		}

		return $deleted;
	}

	/** @return array<string, true> */
	private function referencedPaths(): array
	{
		$referenced = [];
		foreach (['page_components', 'layout_blocks', 'menus'] as $table) {
			foreach ($this->connection->iterateColumn("SELECT content FROM $table") as $json) {
				foreach (self::pathsIn(json_decode((string) $json, true)) as $path) {
					$referenced[$path] = true;
				}
			}
		}

		return $referenced;
	}

	private function delete(string $path): void
	{
		$this->connection->executeStatement('DELETE FROM uploads WHERE path = ?', [$path]);
		if (!self::isUploadPath($path)) {
			return; // never touch the filesystem outside what ImageUploader creates
		}

		$file = $this->uploadsDir . '/' . $path;
		if (is_file($file) && !@unlink($file)) {
			Debugger::log("Nepodařilo se smazat nahraný soubor $file", ILogger::WARNING);
			return;
		}
		// drop the month, then the year directory once empty (rmdir refuses non-empty ones)
		if (@rmdir(dirname($file))) {
			@rmdir(dirname($file, 2));
		}
	}

	private static function isUploadPath(string $path): bool
	{
		return self::pathsIn('/uploads/' . $path) === [$path];
	}
}
