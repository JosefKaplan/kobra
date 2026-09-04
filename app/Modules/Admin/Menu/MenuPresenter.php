<?php declare(strict_types=1);

namespace App\Modules\Admin\Menu;

use App\Entity\Menu;
use App\Helpers\BootstrapFormRenderer;
use App\Helpers\FormHelper;
use App\Models\MenuManager;
use App\Modules\Admin\BasePresenter;
use Contributte\Datagrid\Column\Action\Confirmation\StringConfirmation;
use Contributte\Datagrid\Datagrid;
use Contributte\Datagrid\Exception\DatagridException;
use Nette\Application\UI\Form;
use Nette\DI\Attributes\Inject;

/** Central management of reusable link menus — nav layout blocks pick them as dropdowns. */
class MenuPresenter extends BasePresenter
{
	#[Inject]
	public MenuManager $menuManager;

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
	public function createComponentMenusGrid(string $name): Datagrid
	{
		$grid = new Datagrid($this, $name);
		$grid->setPrimaryKey('id');
		$grid->setDataSource($this->menuManager->findAll());

		$grid->addColumnText('name', 'Název')->setSortable()->setFilterText();
		$grid->addColumnText('links', 'Odkazy')
			->setRenderer(function (Menu $menu): string {
				$lines = array_filter(array_map('trim', explode("\n", $menu->getContent()['links'] ?? '')));
				return count($lines) . '×';
			});
		$grid->addColumnDateTime('editedDate', 'Upraveno')
			->setRenderer(fn (Menu $menu) => ($menu->getEditedDate() ?? $menu->getCreatedDate())->format('j. n. Y H:i'));

		$grid->addAction('edit', 'Upravit', 'edit')
			->setIcon('pencil')
			->setClass('btn btn-xs btn-outline-primary');
		$grid->addAction('delete', 'Smazat', 'delete!')
			->setIcon('trash')
			->setClass('btn btn-xs btn-outline-danger ajax')
			->setConfirmation(new StringConfirmation('Opravdu smazat? Navigace, které toto menu používají, jej přestanou zobrazovat.'));

		return $grid;
	}

	public function handleDelete(int $id): void
	{
		$this->menuManager->delete($id, $this->getUserEntity());
		$this->flashMessage('Menu smazáno.', 'success');

		if ($this->isAjax()) {
			$this->redrawControl('flashes');
			$this['menusGrid']->reload();
		} else {
			$this->redirect('this');
		}
	}

	public function actionAdd(): void
	{
		$this->template->menu = null;
		$this->setView('edit');
	}

	public function actionEdit(int $id): void
	{
		$menu = $this->menuManager->getById($id);
		if (!$menu) {
			$this->error('Menu nenalezeno.');
		}

		$this['menuForm']->setDefaults([
			'name'  => $menu->getName(),
			'links' => $menu->getContent()['links'] ?? '',
		]);

		$this->template->menu = $menu;
	}

	protected function createComponentMenuForm(): Form
	{
		$form = new Form();
		$form->addProtection(FormHelper::ERR_CSRF);
		$form->addText('name', 'Název (zobrazí se jako popisek menu na webu)')->setRequired();
		$form->addTextArea('links', 'Odkazy')
			->setHtmlAttribute('rows', 8)
			->setHtmlAttribute('class', 'font-monospace')
			->setHtmlAttribute('placeholder', "Kontakt | /kontakt\nDokumentace | https://example.com/docs");
		$form->addSubmit('send', 'Uložit');

		$form->onSuccess[] = [$this, 'menuFormSucceeded'];
		BootstrapFormRenderer::apply($form);
		return $form;
	}

	public function menuFormSucceeded(Form $form, \stdClass $data): void
	{
		$id = (int) $this->getParameter('id');
		if ($id) {
			$menu = $this->menuManager->getById($id);
			if (!$menu) {
				$this->error('Menu nenalezeno.');
			}
			$menu->markEdited($this->getUserEntity());
		} else {
			$menu = new Menu();
			$menu->markCreated($this->getUserEntity());
		}

		$menu->setName($data->name);
		$menu->setContent(['links' => $data->links]);

		$this->menuManager->save($menu);
		$this->flashMessage('Menu uloženo.', 'success');
		$this->redirect('default');
	}
}
