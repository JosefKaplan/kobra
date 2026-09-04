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
			->setConfirmation(new StringConfirmation('Opravdu smazat? Stránky, které tento blok používají, jej přestanou zobrazovat.'));

		return $grid;
	}

	public function handleDelete(int $id): void
	{
		$this->layoutBlockManager->delete($id, $this->getUserEntity());
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
			$defaults['brand'] = $content['brand'] ?? '';
			$defaults['menus'] = array_map(fn ($menu) => $menu->getId(), $block->getActiveMenus());
		}
		$this['blockForm']->setDefaults($defaults);

		$this->template->block = $block;
		$this->template->type = $block->getType();
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
			$form->addText('brand', 'Značka v navigaci')
				->setHtmlAttribute('placeholder', 'Core System')
				->setOption('description', 'Text vlevo v navigaci (odkaz na úvod). Prázdné = „Core System“.');
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
			? ['brand' => trim($data->brand ?? ''), 'links' => $data->content]
			: ['text' => $this->richTextSanitizer->sanitize($data->content)]);
		if ($block->getType() === LayoutBlock::TYPE_NAV) {
			$block->setMenus($this->menuManager->getByIds($data->menus ?? []));
		}

		$this->layoutBlockManager->save($block);
		$this->flashMessage('Blok uložen.', 'success');
		$this->redirect('default');
	}
}
