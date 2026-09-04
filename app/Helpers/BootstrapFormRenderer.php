<?php

declare(strict_types=1);

namespace App\Helpers;

use Nette\Forms\Controls\Checkbox;
use Nette\Forms\Form;
use Nette\Forms\Rendering\DefaultFormRenderer;

/**
 * Applies Bootstrap 5 markup to a default-rendered Nette form
 * (the renderer-wrappers pattern from the Nette documentation).
 * Call after all controls have been added.
 */
final class BootstrapFormRenderer
{
	public static function apply(Form $form): void
	{
		$renderer = $form->getRenderer();
		assert($renderer instanceof DefaultFormRenderer);

		$renderer->wrappers['controls']['container'] = null;
		$renderer->wrappers['pair']['container'] = 'div class="mb-3"';
		$renderer->wrappers['pair']['.error'] = 'has-danger';
		$renderer->wrappers['control']['container'] = null;
		$renderer->wrappers['label']['container'] = null;
		$renderer->wrappers['control']['description'] = 'div class="form-text text-muted small"';
		$renderer->wrappers['control']['errorcontainer'] = 'div class="invalid-feedback d-block"';
		$renderer->wrappers['control']['.error'] = 'is-invalid';
		$renderer->wrappers['error']['container'] = null;
		$renderer->wrappers['error']['item'] = 'div class="alert alert-danger"';

		$usedPrimary = false;
		foreach ($form->getControls() as $control) {
			$type = $control->getOption('type');

			if ($type === 'button') {
				$control->getControlPrototype()->addClass($usedPrimary ? 'btn btn-secondary' : 'btn btn-primary');
				$usedPrimary = true;

			} elseif (in_array($type, ['text', 'textarea', 'file'], true)) {
				$control->getControlPrototype()->addClass('form-control');

			} elseif ($type === 'select') {
				$control->getControlPrototype()->addClass('form-select');

			} elseif (in_array($type, ['checkbox', 'radio'], true)) {
				if ($control instanceof Checkbox) {
					$control->getLabelPrototype()->addClass('form-check-label');
				} else {
					$control->getItemLabelPrototype()->addClass('form-check-label');
				}
				$control->getControlPrototype()->addClass('form-check-input');
				$control->getContainerPrototype()->setName('div')->addClass('form-check');
			}

			if (!in_array($type, ['button', 'hidden', 'checkbox', 'radio'], true)) {
				$control->getLabelPrototype()->addClass('form-label fw-semibold');
			}
		}
	}
}
