<?php declare(strict_types=1);

namespace App\Helpers;

use Tracy\Debugger;
use Tracy\ILogger;

/** The <year>/<month>/ directories ImageUploader stores uploads in, mirrored by Thumbnailer in www/thumbs/. */
final class DateShardedFiles
{
	/**
	 * Paths (relative to $dir) of the files in its <yyyy>/<mm>/ directories whose name ends with $suffix.
	 *
	 * @return list<string>
	 */
	public static function list(string $dir, string $suffix = ''): array
	{
		return array_map(
			fn (string $file): string => substr($file, strlen($dir) + 1),
			glob($dir . '/[0-9][0-9][0-9][0-9]/[0-9][0-9]/*' . $suffix) ?: [],
		);
	}

	/**
	 * Deletes $dir/$path, then its month and year directories once empty (rmdir refuses non-empty ones).
	 * False when the file stays (no permission — e.g. owned by the FTP user); one already gone counts as deleted.
	 */
	public static function delete(string $dir, string $path): bool
	{
		$file = $dir . '/' . $path;
		if (is_file($file) && !@unlink($file)) {
			Debugger::log("Nepodařilo se smazat soubor $file", ILogger::WARNING);
			return false;
		}
		if (@rmdir(dirname($file))) {
			@rmdir(dirname($file, 2));
		}

		return true;
	}
}
