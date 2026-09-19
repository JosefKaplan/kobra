<?php declare(strict_types=1);

namespace App\Helpers;

/** Parses the "Text | URL" one-link-per-line format used by nav blocks and menus. */
final class LinkList
{
	/**
	 * Lines without both a label and a URL are skipped.
	 *
	 * @return list<array{label: string, url: string}>
	 */
	public static function parse(string $lines): array
	{
		$links = [];
		foreach (explode("\n", $lines) as $line) {
			$parts = explode('|', $line, 2);
			$label = trim($parts[0]);
			$url = trim($parts[1] ?? '');
			if ($label !== '' && $url !== '') {
				$links[] = ['label' => $label, 'url' => $url];
			}
		}

		return $links;
	}
}
