<?php declare(strict_types=1);

namespace App\Helpers;

use Latte\Extension;

/** The |thumb filter: {$imageUrl|thumb} is the image's thumbnail URL, or the image itself without one. */
final class ThumbnailLatteExtension extends Extension
{
	public function __construct(
		private readonly Thumbnailer $thumbnailer,
	) {
	}

	/** @return array<string, callable(string): string> */
	public function getFilters(): array
	{
		return ['thumb' => $this->thumbnailer->urlFor(...)];
	}
}
