<?php declare(strict_types=1);

namespace App\Modules\Admin\Upload;

use App\Models\UploadBrowser;
use App\Models\UploadCleaner;
use App\Modules\Admin\BasePresenter;
use Contributte\Datagrid\Column\Action\Confirmation\StringConfirmation;
use Contributte\Datagrid\Datagrid;
use Contributte\Datagrid\Exception\DatagridException;
use Nette\DI\Attributes\Inject;
use Nette\Utils\Html;

/**
 * Review of www/uploads/: every image with where it's used, so nothing orphaned stays on the server.
 *
 * @phpstan-import-type UploadFile from UploadBrowser
 */
class UploadPresenter extends BasePresenter
{
	private const STATUS_BADGES = [
		UploadBrowser::STATUS_MISSING => 'danger',
		UploadBrowser::STATUS_UNUSED => 'warning',
		UploadBrowser::STATUS_HIDDEN => 'warning',
		UploadBrowser::STATUS_FOREIGN => 'secondary',
		UploadBrowser::STATUS_PUBLIC => 'success',
	];

	#[Inject]
	public UploadBrowser $uploadBrowser;

	/** @var list<UploadFile>|null */
	private ?array $files = null;

	protected bool $adminOnly = true;

	public function renderDefault(): void
	{
		$files = $this->files();
		$counts = array_count_values(array_column($files, 'status'));
		$this->template->total = count($files);
		$this->template->totalSize = UploadBrowser::formatSize(array_sum(array_column($files, 'size')));
		$this->template->statusBadges = array_map(
			fn (string $status, string $label): array => ['label' => $label, 'color' => self::STATUS_BADGES[$status], 'count' => $counts[$status] ?? 0],
			array_keys(UploadBrowser::STATUSES),
			UploadBrowser::STATUSES,
		);
		$this->template->thumbsMissing = count(array_filter(array_column($files, 'thumbMissing')));
	}

	/**
	 * @throws DatagridException
	 */
	public function createComponentUploadsGrid(string $name): Datagrid
	{
		$grid = new Datagrid($this, $name);
		$grid->setPrimaryKey('path');
		$grid->setDataSource($this->files());
		$grid->setDefaultPerPage(50);

		$grid->addColumnText('url', 'Náhled')
			->setRenderer(fn (array $file) => $this->previewOf($file));
		$grid->addColumnText('path', 'Soubor')
			->setSortable()
			->setFilterText();
		$grid->addColumnText('size', 'Velikost')
			->setSortable()
			->setSortableCallback(function (array $files, array $sort): array {
				usort($files, fn (array $a, array $b): int => $a['size'] <=> $b['size']);
				return $sort['size'] === 'DESC' ? array_reverse($files) : $files;
			})
			->setRenderer(fn (array $file) => $file['size'] > 0 ? UploadBrowser::formatSize($file['size']) : '–');
		$grid->addColumnText('width', 'Rozměry')
			->setRenderer(fn (array $file) => $file['width'] !== null ? $file['width'] . ' × ' . $file['height'] : '–');
		$grid->addColumnText('date', 'Nahráno')
			->setSortable()
			->setRenderer(fn (array $file) => $file['date'] !== '' ? date('j. n. Y H:i', (int) strtotime($file['date'])) : '–');
		$grid->addColumnText('usages', 'Kde se používá')
			->setRenderer(fn (array $file) => $this->usagesOf($file));
		$grid->addColumnText('status', 'Stav')
			->setRenderer(fn (array $file) => Html::el('span')
				->class('badge text-bg-' . self::STATUS_BADGES[$file['status']])
				->setText(UploadBrowser::STATUSES[$file['status']]))
			->setFilterSelect(['' => 'Vše'] + UploadBrowser::STATUSES);

		$grid->addAction('delete', 'Smazat', 'delete!', ['path' => 'path'])
			->setIcon('trash')
			->setClass('btn btn-xs btn-outline-danger ajax')
			->setRenderCondition(fn (array $file) => $file['deletable'])
			->setConfirmation(new StringConfirmation('Opravdu smazat natrvalo? Soubor zmizí ze serveru, nelze vrátit.'));

		return $grid;
	}

	public function handleDelete(string $path): void
	{
		// release() itself re-checks that nothing refers to the image
		if (UploadCleaner::isUploadPath($path) && $this->uploadCleaner->release([$path]) > 0) {
			$this->flashMessage('Obrázek smazán.', 'success');
		} else {
			$this->flashMessage('Obrázek se nesmazal — používá se v obsahu, je mimo evidenci, nebo server nemá právo soubor smazat (podrobnosti v logu).', 'danger');
		}

		if ($this->isAjax()) {
			$this->redrawControl('flashes');
			$this->redrawControl('summary');
			$this['uploadsGrid']->reload();
		} else {
			$this->redirect('this');
		}
	}

	/**
	 * UploadBrowser::all() scans the disk and all the content, so it's built once per request — lazily,
	 * which during a delete means after the deletion.
	 *
	 * @return list<UploadFile>
	 */
	private function files(): array
	{
		return $this->files ??= $this->uploadBrowser->all();
	}

	/** @param UploadFile $file */
	private function previewOf(array $file): Html
	{
		if ($file['width'] === null) {
			return Html::el('i')->class('bi bi-file-earmark-x upload-thumb-empty');
		}

		return Html::el('a')->href($file['url'])->target('_blank')->addHtml(
			Html::el('img')->src($file['thumb'])->alt('')->setAttribute('loading', 'lazy')->class('upload-thumb'),
		);
	}

	/** @param UploadFile $file */
	private function usagesOf(array $file): Html
	{
		$list = Html::el('div')->class('small');
		foreach ($file['usages'] as $usage) {
			$list->addHtml(Html::el('div')->addHtml(
				Html::el('a')
					->href($this->link(':Admin:' . $usage['destination'], $usage['id']))
					->setText($usage['label'] . ($usage['public'] ? '' : ' (nezobrazuje se)')),
			));
		}

		return $list;
	}
}
