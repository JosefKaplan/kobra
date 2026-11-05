<?php

declare(strict_types=1);

namespace App\Models;

use App\Helpers\Thumbnailer;
use Doctrine\DBAL\Connection;
use Nette\Utils\Finder;

/**
 * Read-only overview behind the admin's image browser (Admin:Upload): everything that is on disk
 * under www/uploads/, in the `uploads` table or mentioned in the content, with where it's used
 * and what state it's in. Deleting stays with UploadCleaner.
 *
 * @phpstan-type Usage array{label: string, destination: string, id: int, public: bool}
 * @phpstan-type UploadFile array{path: string, name: string, url: string, thumb: string, thumbMissing: bool, size: int, width: int|null, height: int|null, date: string, status: string, usages: list<Usage>, deletable: bool}
 */
final class UploadBrowser
{
	public const STATUS_MISSING = 'missing';
	public const STATUS_UNUSED = 'unused';
	public const STATUS_HIDDEN = 'hidden';
	public const STATUS_FOREIGN = 'foreign';
	public const STATUS_PUBLIC = 'public';

	/** status => label, in display order: what needs attention comes first */
	public const STATUSES = [
		self::STATUS_MISSING => 'Chybí soubor',
		self::STATUS_UNUSED => 'Nepoužitý',
		self::STATUS_HIDDEN => 'Jen ve skrytém obsahu',
		self::STATUS_FOREIGN => 'Cizí soubor',
		self::STATUS_PUBLIC => 'Na webu',
	];

	private const COMPONENT_LABELS = [
		'hero' => 'hero sekce',
		'heading' => 'nadpis',
		'text_block' => 'text',
		'image' => 'obrázek',
		'columns' => 'sloupce',
		'masonry' => 'masonry',
		'masonry_item' => 'dlaždice',
		'cta' => 'výzva k akci',
		'contact_form' => 'kontaktní formulář',
		'contact_info' => 'kontaktní údaje',
	];

	public function __construct(
		private readonly string $uploadsDir,
		private readonly Connection $connection,
		private readonly Thumbnailer $thumbnailer,
	) {
	}

	/** @return list<UploadFile> */
	public function all(): array
	{
		$usages = $this->usages();
		$files = $this->filesOnDisk();
		/** @var array<string, array{size: int, created_date: string}> $rows */
		$rows = $this->connection->fetchAllAssociativeIndexed('SELECT path, size, created_date FROM uploads');

		$all = [];
		foreach (array_keys($files + $rows + $usages) as $path) {
			$path = (string) $path;
			$file = $files[$path] ?? null;
			$pathUsages = $usages[$path] ?? [];
			$isUploadPath = UploadCleaner::isUploadPath($path);
			$imageSize = $file !== null ? @getimagesize($file) : false;
			$url = '/uploads/' . implode('/', array_map(rawurlencode(...), explode('/', $path)));
			$thumb = $this->thumbnailer->urlFor($url);

			$all[] = [
				'path' => $path,
				'name' => basename($path),
				'url' => $url,
				'thumb' => $thumb,
				'thumbMissing' => $thumb === $url && $isUploadPath && $imageSize !== false
					&& Thumbnailer::isWorthThumbnail($imageSize[0], $imageSize[1], $imageSize[2]),
				'size' => $file !== null ? (int) filesize($file) : (int) ($rows[$path]['size'] ?? 0),
				'width' => $imageSize !== false ? $imageSize[0] : null,
				'height' => $imageSize !== false ? $imageSize[1] : null,
				'date' => $rows[$path]['created_date'] ?? ($file !== null ? date('Y-m-d H:i:s', (int) filemtime($file)) : ''),
				'status' => self::status($file !== null, $isUploadPath, $pathUsages),
				'usages' => $pathUsages,
				'deletable' => $pathUsages === [] && $isUploadPath,
			];
		}

		$order = array_flip(array_keys(self::STATUSES));
		usort($all, fn (array $a, array $b): int => [$order[$a['status']], $a['name']] <=> [$order[$b['status']], $b['name']]);

		return $all;
	}

	public static function formatSize(int $bytes): string
	{
		return $bytes < 1024 * 1024
			? round($bytes / 1024) . ' kB'
			: number_format($bytes / 1024 / 1024, 1, ',', ' ') . ' MB';
	}

	/** @param list<Usage> $usages */
	private static function status(bool $exists, bool $isUploadPath, array $usages): string
	{
		return match (true) {
			!$exists => self::STATUS_MISSING,
			!$isUploadPath => self::STATUS_FOREIGN,
			$usages === [] => self::STATUS_UNUSED,
			array_filter($usages, fn (array $usage): bool => $usage['public']) === [] => self::STATUS_HIDDEN,
			default => self::STATUS_PUBLIC,
		};
	}

	/** @return array<string, string> path relative to www/uploads/ => absolute path */
	private function filesOnDisk(): array
	{
		$files = [];
		foreach (Finder::findFiles('*')->from($this->uploadsDir) as $file) {
			if (!str_starts_with($file->getFilename(), '.')) { // .htaccess, .gitkeep, .DS_Store
				$files[$file->getRelativePathname()] = $file->getPathname();
			}
		}

		return $files;
	}

	/**
	 * Where each upload is used. "public" = a visitor can actually see it: not on a hidden page, in
	 * a hidden component or in a tile without a main image, not in a layout block or menu no visible
	 * page shows. These mirror what the front renders — keep them in step with components.latte.
	 *
	 * @return array<string, list<Usage>>
	 */
	private function usages(): array
	{
		$usages = [];
		$add = function (string $json, string $label, string $destination, int $id, bool $public) use (&$usages): void {
			foreach (UploadCleaner::pathsIn(json_decode($json, true)) as $path) {
				$usages[$path][] = ['label' => $label, 'destination' => $destination, 'id' => $id, 'public' => $public];
			}
		};

		$components = $this->connection->fetchAllAssociative(
			'SELECT c.content, c.type, c.page_id, c.parent_id, parent.type AS parent_type, p.title,'
			. ' c.is_visible AND p.is_visible AND COALESCE(parent.is_visible, 1) AS public'
			. ' FROM page_components c'
			. ' JOIN pages p ON p.id = c.page_id'
			. ' LEFT JOIN page_components parent ON parent.id = c.parent_id',
		);
		foreach ($components as $row) {
			$type = (string) $row['type'];
			$public = (bool) $row['public'];
			if ($type === 'masonry_item') { // like components.latte: no main image, no tile — its gallery and text don't show either
				$content = json_decode((string) $row['content'], true);
				$public = $public && is_array($content) && trim((string) ($content['main_image_url'] ?? '')) !== '';
			}
			$destination = match ($row['parent_type']) {
				'masonry' => 'Page:masonry',
				'columns' => 'Page:columns',
				default => 'Page:edit',
			};
			$add(
				(string) $row['content'],
				'Stránka „' . $row['title'] . '“ – ' . (self::COMPONENT_LABELS[$type] ?? $type),
				$destination,
				(int) ($destination === 'Page:edit' ? $row['page_id'] : $row['parent_id']),
				$public,
			);
		}

		$blocks = $this->connection->fetchAllAssociative(
			'SELECT b.id, b.type, b.name, b.content,'
			. ' EXISTS(SELECT 1 FROM pages p WHERE p.is_visible = 1 AND b.id IN (p.nav_block_id, p.footer_block_id)) AS public'
			. ' FROM layout_blocks b',
		);
		foreach ($blocks as $row) {
			$label = ($row['type'] === 'nav' ? 'Navigace' : 'Patička') . ' „' . $row['name'] . '“';
			$add((string) $row['content'], $label, 'Layout:edit', (int) $row['id'], (bool) $row['public']);
		}

		$menus = $this->connection->fetchAllAssociative(
			'SELECT m.id, m.name, m.content,'
			. ' EXISTS(SELECT 1 FROM layout_block_menus lm'
			. ' JOIN pages p ON p.nav_block_id = lm.layout_block_id' // menus belong to nav blocks only
			. ' WHERE lm.menu_id = m.id AND p.is_visible = 1) AS public'
			. ' FROM menus m',
		);
		foreach ($menus as $row) {
			$add((string) $row['content'], 'Menu „' . $row['name'] . '“', 'Menu:edit', (int) $row['id'], (bool) $row['public']);
		}

		return $usages;
	}
}
