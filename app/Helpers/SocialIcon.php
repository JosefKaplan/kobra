<?php declare(strict_types=1);

namespace App\Helpers;

/** Picks the Bootstrap Icons glyph for a social-network link by its domain (contact_info component). */
final class SocialIcon
{
	/** domain => icon name without the `bi-` prefix; subdomains (www., cs-cz., …) match too */
	private const ICONS = [
		'instagram.com' => 'instagram',
		'facebook.com'  => 'facebook',
		'fb.com'        => 'facebook',
		'linkedin.com'  => 'linkedin',
		'behance.net'   => 'behance',
		'dribbble.com'  => 'dribbble',
		'pinterest.com' => 'pinterest',
		'youtube.com'   => 'youtube',
		'youtu.be'      => 'youtube',
		'vimeo.com'     => 'vimeo',
		'tiktok.com'    => 'tiktok',
		'x.com'         => 'twitter-x',
		'twitter.com'   => 'twitter-x',
	];

	public static function for(string $url): string
	{
		$host = strtolower((string) parse_url($url, PHP_URL_HOST));
		foreach (self::ICONS as $domain => $icon) {
			if ($host === $domain || str_ends_with($host, '.' . $domain)) {
				return $icon;
			}
		}

		return 'link-45deg';
	}
}
