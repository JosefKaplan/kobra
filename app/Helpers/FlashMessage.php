<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Shape-only type hint for Nette's flash-message payload.
 *
 * `Nette\Application\UI\Control::flashMessage()` actually stores a plain
 * `\stdClass` with these two properties set dynamically (`(object) ['message' =>
 * ..., 'type' => ...]`) — there is no real class to point Latte's `{varType}` at,
 * and `stdClass` itself has no declared properties, so IDEs flag `$flash->type`/
 * `$flash->message` as unknown. This class is never instantiated; it exists only
 * as a `{varType}` target in `@layout.latte` so the editor knows the shape.
 */
final class FlashMessage
{
    public string $message;

    public string $type;
}