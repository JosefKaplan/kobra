<?php declare(strict_types=1);

namespace App\Helpers;

use App\Models\UploadCleaner;
use Nette\Assets\Registry;
use Nette\Utils\FileSystem;
use Nette\Utils\Image;
use Nette\Utils\ImageColor;
use Nette\Utils\ImageType;
use Tracy\Debugger;
use Tracy\ILogger;

/**
 * Lightweight copies of uploads for grids, galleries and logo strips: www/thumbs/<upload path>.webp
 * with the shorter side SIZE px (tiles crop to a square, so the shorter side is what has to fill them).
 * They're disposable: not in the DB, rebuilt from the originals any time (app:generate-thumbs).
 * Anything without one — too small to need it, an external URL, not generated — shows the original.
 */
final class Thumbnailer
{
	/** A masonry tile on a 27" 5K screen is ~910 device px wide; 640 left the tiles soft on Retina displays. */
	public const SIZE = 960;

	private const QUALITY = 80;

	private const WEBP_MAX_SIDE = 16383;

	/** GD holds the decoded image and the resized copy in memory, about 4 bytes per pixel each. */
	private const BYTES_PER_PIXEL = 5;

	/** What generateAll() raises memory_limit to: enough for a ~200 MP original. */
	private const CONSOLE_MEMORY_LIMIT = 1024 * 1024 * 1024;

	public function __construct(
		private readonly string $uploadsDir,
		private readonly string $thumbsDir,
		private readonly Registry $assets,
	) {
	}

	/**
	 * The thumbnail's URL if there is one, otherwise the given URL as it is. The `thumbs` asset mapping
	 * (common.neon) adds ?v=<file time>, so a regenerated thumbnail gets past the browser's cache.
	 */
	public function urlFor(string $url): string
	{
		if (!str_starts_with($url, '/uploads/')) {
			return $url;
		}
		$path = substr($url, strlen('/uploads/'));

		$thumbnail = $this->assets->tryGetAsset(['thumbs', $path . '.webp']);

		return $thumbnail !== null ? (string) $thumbnail : $url;
	}

	/**
	 * By getimagesize()'s width, height and type. Never a GIF: GD reads only its first frame,
	 * so an animated one would stand still in the grid. Never a panorama whose thumbnail's longer
	 * side would exceed what WebP can store: the encoder would just fail on it.
	 */
	public static function isWorthThumbnail(int $width, int $height, int $type): bool
	{
		return $type !== ImageType::GIF
			&& min($width, $height) > self::SIZE
			&& max($width, $height) * self::SIZE / min($width, $height) <= self::WEBP_MAX_SIDE;
	}

	/** Whether the upload has a thumbnail now. Never throws: a failure just leaves the original in use. */
	public function generate(string $path, bool $force = false): bool
	{
		$size = $this->imageSize($path);

		return $size !== null && self::isWorthThumbnail(...$size) && $this->create($path, $size[0], $size[1], $force);
	}

	/**
	 * Thumbnails for everything in www/uploads/ and nothing else in www/thumbs/, so the folder can go
	 * to the server as it is; only the console commands get here.
	 *
	 * @return array{ok: int, unneeded: int, bytes: int, failed: list<string>}
	 */
	public function generateAll(bool $force = false): array
	{
		// originals too big for the web's limit are done from here; never lower a higher (or no) limit
		$limit = ini_parse_quantity((string) ini_get('memory_limit'));
		if ($limit > 0 && $limit < self::CONSOLE_MEMORY_LIMIT) {
			ini_set('memory_limit', (string) self::CONSOLE_MEMORY_LIMIT);
		}

		$this->deleteOrphans();
		$result = ['ok' => 0, 'unneeded' => 0, 'bytes' => 0, 'failed' => []];
		foreach (DateShardedFiles::list($this->uploadsDir) as $path) {
			$size = $this->imageSize($path);
			if ($size === null) {
				continue; // not ImageUploader's (the image browser shows it as foreign) or not an image at all
			}
			if (!self::isWorthThumbnail(...$size)) {
				$this->delete($path); // one left from a smaller SIZE would still be served
				$result['unneeded']++;
			} elseif ($this->create($path, $size[0], $size[1], $force)) {
				$result['ok']++;
				$result['bytes'] += (int) filesize($this->file($path));
			} else {
				$result['failed'][] = $path;
			}
		}

		return $result;
	}

	/** False when the thumbnail stays (no permission to delete it); none at all counts as deleted. */
	public function delete(string $path): bool
	{
		return DateShardedFiles::delete($this->thumbsDir, $path . '.webp');
	}

	/** Thumbnails whose original is gone. */
	public function deleteOrphans(): void
	{
		foreach (DateShardedFiles::list($this->thumbsDir, '.webp') as $thumbnail) {
			$path = substr($thumbnail, 0, -strlen('.webp'));
			if (!is_file($this->uploadsDir . '/' . $path)) {
				$this->delete($path);
			}
		}
	}

	/**
	 * Width, height and type of an upload by its content (getimagesize()); null for a file that isn't
	 * an image or that ImageUploader didn't create.
	 *
	 * @return array{int, int, int}|null
	 */
	private function imageSize(string $path): ?array
	{
		if (!UploadCleaner::isUploadPath($path)) {
			return null;
		}
		$size = @getimagesize($this->uploadsDir . '/' . $path);

		return $size !== false ? [$size[0], $size[1], $size[2]] : null;
	}

	/** The thumbnail of an image worth one, unless an up-to-date one is already there. */
	private function create(string $path, int $width, int $height, bool $force): bool
	{
		$source = $this->uploadsDir . '/' . $path;
		$target = $this->file($path);
		if (!$force && is_file($target) && filemtime($target) >= filemtime($source)) {
			return true;
		}

		if (!self::fitsInMemory($width, $height)) {
			Debugger::log("Náhled $path se nevytvořil: obrázek {$width}×{$height} se nevejde do memory_limit.", ILogger::WARNING);
			return false;
		}

		try {
			$image = Image::fromFile($source);
			// shrink first: rotating makes a copy, which at full size would double the memory fitsInMemory() allows for
			$image->resize(self::SIZE, self::SIZE, Image::OrBigger);
			self::applyOrientation($image, $source);
			FileSystem::createDir(dirname($target));
			$image->save($target, self::QUALITY, ImageType::WEBP);
		} catch (\Throwable $e) {
			Debugger::log($e, ILogger::EXCEPTION);
			return false;
		}

		return true;
	}

	private function file(string $path): string
	{
		return $this->thumbsDir . '/' . $path . '.webp';
	}

	/** Running out of memory is a fatal error, which no try/catch would save. */
	private static function fitsInMemory(int $width, int $height): bool
	{
		$limit = ini_parse_quantity((string) ini_get('memory_limit'));

		return $limit <= 0 || $width * $height * self::BYTES_PER_PIXEL < $limit - memory_get_usage();
	}

	/** GD ignores EXIF orientation but browsers don't, so a phone photo's thumbnail would end up sideways or mirrored. */
	private static function applyOrientation(Image $image, string $file): void
	{
		$exif = function_exists('exif_read_data') ? @exif_read_data($file) : false;
		// orientation => [mirror horizontally first, then rotate clockwise by]
		[$mirror, $clockwise] = match (is_array($exif) ? ($exif['Orientation'] ?? 1) : 1) {
			2 => [true, 0],
			3 => [false, 180],
			4 => [true, 180],
			5 => [true, 270],
			6 => [false, 90],
			7 => [true, 90],
			8 => [false, 270],
			default => [false, 0],
		};
		if ($mirror) {
			$image->flip(IMG_FLIP_HORIZONTAL);
		}
		if ($clockwise !== 0) {
			$image->rotate(-$clockwise, ImageColor::rgb(0, 0, 0, 0)); // GD rotates counter-clockwise
		}
	}
}
