<?php declare(strict_types=1);

namespace App\Modules\Admin\Layout;

use App\Entity\LayoutBlock;
use App\Helpers\BootstrapFormRenderer;
use App\Helpers\FormHelper;
use App\Helpers\RichTextSanitizer;
use App\Models\LayoutBlockManager;
use App\Models\MenuManager;
use App\Modules\Admin\BasePresenter;
use Contributte\Datagrid\Column\Action\Confirmation\StringConfirmation;
use Contributte\Datagrid\Datagrid;
use Contributte\Datagrid\Exception\DatagridException;
use Nette\Application\UI\Form;
use Nette\DI\Attributes\Inject;

/** Central management of reusable navbars and footers — pages pick them in their settings form. */
class LayoutPresenter extends BasePresenter
{
	#[Inject]
	public LayoutBlockManager $layoutBlockManager;

	#[Inject]
	public MenuManager $menuManager;

	#[Inject]
	public RichTextSanitizer $richTextSanitizer;

	private ?LayoutBlock $editedBlock = null;

	protected function startup(): void
	{
		parent::startup();

		if (!$this->getUser()->isInRole('admin')) {
			$this->flashMessage(FormHelper::ERR_ACCESS_DENIED, 'danger');
			$this->redirect(':Admin:User:default');
		}
	}

	/**
	 * @throws DatagridException
	 */
	public function createComponentBlocksGrid(string $name): Datagrid
	{
		$grid = new Datagrid($this, $name);
		$grid->setPrimaryKey('id');
		$grid->setDataSource($this->layoutBlockManager->findAll());

		$grid->addColumnText('name', 'Název')->setSortable()->setFilterText();
		$grid->addColumnText('type', 'Typ')
			->setRenderer(fn (LayoutBlock $block) => $block->getType() === LayoutBlock::TYPE_NAV ? 'Navigace' : 'Patička');
		$grid->addColumnDateTime('editedDate', 'Upraveno')
			->setRenderer(fn (LayoutBlock $block) => ($block->getEditedDate() ?? $block->getCreatedDate())->format('j. n. Y H:i'));

		$grid->addAction('edit', 'Upravit', 'edit')
			->setIcon('pencil')
			->setClass('btn btn-xs btn-outline-primary');
		$grid->addAction('delete', 'Smazat', 'delete!')
			->setIcon('trash')
			->setClass('btn btn-xs btn-outline-danger ajax')
			->setConfirmation(new StringConfirmation('Opravdu smazat natrvalo, včetně obrázků karuselu a loga? Stránky, které tento blok používají, jej přestanou zobrazovat. Nelze vrátit.'));

		return $grid;
	}

	public function handleDelete(int $id): void
	{
		$this->layoutBlockManager->delete($id);
		$this->flashMessage('Blok smazán.', 'success');

		if ($this->isAjax()) {
			$this->redrawControl('flashes');
			$this['blocksGrid']->reload();
		} else {
			$this->redirect('this');
		}
	}

	public function actionAdd(string $type): void
	{
		if (!in_array($type, LayoutBlock::TYPES, true)) {
			$this->error('Neznámý typ bloku.');
		}

		$this->template->block = null;
		$this->template->type = $type;
		$this->template->carousel = [];
		$this->template->logo = '';
		$this->setView('edit');
	}

	public function actionEdit(int $id): void
	{
		$block = $this->layoutBlockManager->getById($id);
		if (!$block) {
			$this->error('Blok nenalezen.');
		}
		$this->editedBlock = $block;

		$content = $block->getContent();
		$defaults = [
			'name'    => $block->getName(),
			'content' => $block->getType() === LayoutBlock::TYPE_NAV
				? ($content['links'] ?? '')
				: ($content['text'] ?? ''),
		];
		if ($block->getType() === LayoutBlock::TYPE_NAV) {
			$defaults['style'] = $block->getStyle();
			$defaults['brand'] = $content['brand'] ?? '';
			$defaults['menus'] = array_map(fn ($menu) => $menu->getId(), $block->getMenus());
		}
		$this['blockForm']->setDefaults($defaults);

		$this->template->block = $block;
		$this->template->type = $block->getType();
		$this->template->carousel = self::carouselItems($content['carousel'] ?? null);
		$this->template->logo = (string) ($content['logo_url'] ?? '');
	}

	/**
	 * Normalizes footer carousel rows — from stored content or from the raw POST
	 * (`carousel[<index>][image_url|alt|link_url]`, see edit.latte / masonry.js): keeps the
	 * body order, drops rows without an image, re-indexes.
	 *
	 * @return list<array{image_url: string, alt: string, link_url: string}>
	 */
	private static function carouselItems(mixed $rows): array
	{
		return array_values(array_filter(array_map(
			fn ($row) => [
				'image_url' => trim((string) (is_array($row) ? ($row['image_url'] ?? '') : '')),
				'alt'       => trim((string) (is_array($row) ? ($row['alt'] ?? '') : '')),
				'link_url'  => trim((string) (is_array($row) ? ($row['link_url'] ?? '') : '')),
			],
			is_array($rows) ? $rows : [],
		), fn (array $row) => $row['image_url'] !== ''));
	}

	protected function createComponentBlockForm(): Form
	{
		$type = $this->editedBlock?->getType() ?? (string) $this->getParameter('type');

		$form = new Form();
		$form->addProtection(FormHelper::ERR_CSRF);
		$form->addText('name', 'Název (jen pro administraci)')->setRequired();

		if ($type === LayoutBlock::TYPE_FOOTER) {
			$form->addTextArea('content', 'Obsah patičky')
				->setHtmlAttribute('rows', 5)
				->setHtmlAttribute('data-richtext');
		} else {
			$form->addSelect('style', 'Vzhled', [
				LayoutBlock::STYLE_STUDIO => 'Logo s menu pod sebou',
				LayoutBlock::STYLE_NAVBAR => 'Klasická lišta',
			])->setDefaultValue(LayoutBlock::STYLE_STUDIO);
			$form->addText('brand', 'Značka v navigaci')
				->setHtmlAttribute('placeholder', 'Kobra')
				->setOption('description', 'Odkaz na úvod vlevo. S nahraným logem slouží jako jeho alternativní text, bez loga se zobrazí jako text. Prázdné = „Kobra“.');
			$form->addTextArea('content', 'Odkazy')
				->setHtmlAttribute('rows', 6)
				->setHtmlAttribute('class', 'font-monospace')
				->setHtmlAttribute('placeholder', "Kontakt | /kontakt\nDokumentace | https://example.com/docs");
			$menuItems = $this->menuManager->getSelectItems();
			if ($menuItems !== []) {
				$form->addCheckboxList('menus', 'Menu (rozbalovací nabídky)', $menuItems);
			}
		}

		$form->addSubmit('send', 'Uložit');
		$form->onSuccess[] = [$this, 'blockFormSucceeded'];
		BootstrapFormRenderer::apply($form);
		return $form;
	}

	public function blockFormSucceeded(Form $form, \stdClass $data): void
	{
		$id = (int) $this->getParameter('id');
		if ($id) {
			$block = $this->layoutBlockManager->getById($id);
			if (!$block) {
				$this->error('Blok nenalezen.');
			}
			$block->markEdited($this->getUserEntity());
		} else {
			$type = (string) $this->getParameter('type');
			if (!in_array($type, LayoutBlock::TYPES, true)) {
				$this->error('Neznámý typ bloku.');
			}
			$block = new LayoutBlock();
			$block->setType($type);
			$block->markCreated($this->getUserEntity());
		}

		$block->setName($data->name);
		$block->setContent($block->getType() === LayoutBlock::TYPE_NAV
			? [
				'style'    => $data->style,
				'brand'    => trim($data->brand ?? ''),
				// rendered by the shared imageUploadField widget, not a Nette control — read from the raw POST
				'logo_url' => trim((string) $this->getHttpRequest()->getPost('logo_url')),
				'links'    => $data->content,
			]
			: [
				'text'     => $this->richTextSanitizer->sanitize($data->content),
				// The carousel rows are plain inputs inside the same <form>, not Nette form controls
				// (dynamic list: add/remove/reorder client-side), so they're read from the raw POST.
				'carousel' => self::carouselItems($this->getHttpRequest()->getPost('carousel')),
			]);
		if ($block->getType() === LayoutBlock::TYPE_NAV) {
			$block->setMenus($this->menuManager->getByIds($data->menus ?? []));
		}

		$this->layoutBlockManager->save($block);
		$this->flashMessage('Blok uložen.', 'success');
		$this->redirect('default');
	}
}
