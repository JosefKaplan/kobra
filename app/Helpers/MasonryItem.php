<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Normalizes one `masonry` component item into the current "book" shape — used by both the
 * front renderer (components.latte) and the admin editor (_componentEditor.latte) so the
 * fallback for older data lives in exactly one place instead of being duplicated in Latte.
 *
 * Items saved before this shape existed are {image_url, link_url, alt, is_visible}: link_url
 * has no replacement (clicking a tile now always opens the book) and is simply dropped the next
 * time the component is saved, the same way spacingTop/spacingBottom silently gain a real value
 * on next save (see PageComponent::SPACING_VALUES).
 */
final class MasonryItem
{
    /**
     * @param array<string, mixed> $item
     * @return array{main_image_url: string, main_image_alt: string, heading: string, text: string, gallery: array<int, array{image_url: string, alt: string}>, is_visible: bool}
     */
    public static function normalize(array $item): array
    {
        $gallery = is_array($item['gallery'] ?? null) ? $item['gallery'] : [];

        return [
            'main_image_url' => (string) ($item['main_image_url'] ?? $item['image_url'] ?? ''),
            'main_image_alt' => (string) ($item['main_image_alt'] ?? $item['alt'] ?? ''),
            'heading'        => (string) ($item['heading'] ?? ''),
            'text'           => (string) ($item['text'] ?? ''),
            'gallery'        => array_values(array_map(
                static fn (array $g): array => [
                    'image_url' => (string) ($g['image_url'] ?? ''),
                    'alt'       => (string) ($g['alt'] ?? ''),
                ],
                array_filter($gallery, static fn ($g): bool => is_array($g)),
            )),
            'is_visible' => (bool) ($item['is_visible'] ?? true),
        ];
    }
}