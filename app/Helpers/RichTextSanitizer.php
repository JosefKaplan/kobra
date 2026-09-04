<?php

declare(strict_types=1);

namespace App\Helpers;

use HTMLPurifier;
use HTMLPurifier_AttrDef_Enum;
use HTMLPurifier_Config;

/**
 * Sanitizes HTML posted from the Quill rich-text editor before it is persisted.
 * Defense in depth: the client-side toolbar already restricts formatting to the
 * same allowlist, but posted HTML is never trusted as-is.
 */
final class RichTextSanitizer
{
	private ?HTMLPurifier $purifier = null;

	public function __construct(
		private readonly string $cacheDir,
	) {
	}

	public function sanitize(string $html): string
	{
		$clean = $this->getPurifier()->purify($html);

		$textOnly = trim(str_replace("\u{a0}", '', strip_tags(str_replace('&nbsp;', "\u{a0}", $clean))));
		return $textOnly === '' ? '' : $clean;
	}

	private function getPurifier(): HTMLPurifier
	{
		if ($this->purifier !== null) {
			return $this->purifier;
		}

		if (!is_dir($this->cacheDir)) {
			mkdir($this->cacheDir, recursive: true);
		}

		$config = HTMLPurifier_Config::createDefault();
		$config->set('Core.Encoding', 'UTF-8');
		$config->set('HTML.Doctype', 'HTML 4.01 Transitional');
		// Quill toolbar output: bold/italic/underline/strike, links, lists, blockquote.
		// No headings here on purpose — the dedicated "heading" component type owns page heading structure.
		$config->set('HTML.Allowed', 'p,br,strong,em,u,s,a[href|title],ul,ol,li[data-list],blockquote');
		$config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
		$config->set('HTML.TargetBlank', true);
		$config->set('Cache.SerializerPath', $this->cacheDir);

		// Quill 2 marks bullet vs. ordered list items via data-list rather than <ul> vs <ol>.
		$definition = $config->getHTMLDefinition(true);
		$definition->addAttribute('li', 'data-list', new HTMLPurifier_AttrDef_Enum(['bullet', 'ordered']));

		return $this->purifier = new HTMLPurifier($config);
	}
}
