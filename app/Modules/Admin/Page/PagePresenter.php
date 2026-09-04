<?php declare(strict_types=1);

namespace App\Modules\Admin\Page;

use App\Entity\Page;
use App\Entity\PageComponent;
use App\Entity\LayoutBlock;
use App\Helpers\BootstrapFormRenderer;
use App\Helpers\FormHelper;
use App\Helpers\RichTextSanitizer;
use App\Models\LayoutBlockManager;
use App\Models\PageManager;
use App\Models\SettingsManager;
use App\Modules\Admin\BasePresenter;
use Contributte\Datagrid\Column\Action\Confirmation\StringConfirmation;
use Contributte\Datagrid\Datagrid;
use Contributte\Datagrid\Exception\DatagridException;
use Nette\Application\UI\Form;
use Nette\DI\Attributes\Inject;
use Nette\Http\IResponse;
use Nette\Utils\Strings;

class PagePresenter extends BasePresenter
{
	/** Types allowed inside a `columns` container (no nested containers, no layout components). */
	private const CHILD_COMPONENT_TYPES = ['heading', 'text_block', 'cta', 'image'];

	/** More columns than this stop being columns on any screen. */
	private const MAX_COLUMNS = 4;

	#[Inject]
	public PageManager $pageManager;

	#[Inject]
	public SettingsManager $settingsManager;

	#[Inject]
	public RichTextSanitizer $richTextSanitizer;

	#[Inject]
	public LayoutBlockManager $layoutBlockManager;

	protected function startup(): void
	{
		parent::startup();

		if (!$this->getUser()->isInRole('admin')) {
			$this->flashMessage(FormHelper::ERR_ACCESS_DENIED, 'danger');
			$this->redirect(':Admin:User:default');
		}
	}

	public function renderDefault(): void
	{
	}

	/**
	 * @throws DatagridException
	 */
	public function createComponentPagesGrid(string $name): Datagrid
	{
		$grid = new Datagrid($this, $name);
		$grid->setPrimaryKey('id');
		$grid->setDataSource($this->pageManager->findAll());

		$grid->addColumnNumber('id', 'ID')->setSortable();
		$grid->addColumnText('title', 'Název')->setSortable()->setFilterText();
		$grid->addColumnText('link', 'URL')->setSortable()->setFilterText();
		$grid->addColumnText('isVisible', 'Viditelná')
			->setRenderer(function (Page $page) {
				return $page->isVisible() ? 'Ano' : 'Ne';
			});

		$homepageId = (int) $this->settingsManager->get(SettingsManager::HOMEPAGE_PAGE_ID);
		$grid->addColumnText('homepage', 'Úvodní')
			->setRenderer(function (Page $page) use ($homepageId) {
				return $page->getId() === $homepageId ? 'Ano' : '';
			});

		$grid->addAction('setHomepage', '', 'setHomepage!')
			->setIcon('house')
			->setTitle('Nastavit jako úvodní stránku')
			->setClass('btn btn-xs btn-outline-secondary ajax')
			->setRenderCondition(fn (Page $page) => $page->getId() !== $homepageId);
		$grid->addAction('unsetHomepage', '', 'unsetHomepage!')
			->setIcon('house-circle-xmark')
			->setTitle('Zrušit úvodní stránku')
			->setClass('btn btn-xs btn-outline-secondary ajax')
			->setRenderCondition(fn (Page $page) => $page->getId() === $homepageId);
		$grid->addAction('edit', 'Upravit', 'edit')
			->setIcon('pencil')
			->setClass('btn btn-xs btn-outline-primary');
		$grid->addAction('delete', 'Smazat', 'delete!')
			->setIcon('trash')
			->setClass('btn btn-xs btn-outline-danger ajax')
			->setConfirmation(new StringConfirmation('Opravdu smazat tuto stránku včetně všech komponent?'));
		$grid->addToolbarButton('add', 'Nová stránka')
			->setIcon('plus')
			->setClass('btn btn-primary');

		return $grid;
	}

	public function handleDelete(int $id): void
	{
		// Deleting the homepage must also unset it, or "/" would point to a dead page id
		if ((int) $this->settingsManager->get(SettingsManager::HOMEPAGE_PAGE_ID) === $id) {
			$this->settingsManager->set(SettingsManager::HOMEPAGE_PAGE_ID, null);
		}

		$this->pageManager->delete($id, $this->getUserEntity());
		$this->flashMessage('Stránka smazána.', 'success');

		if ($this->isAjax()) {
			$this->redrawControl('flashes');
			$this['pagesGrid']->reload();
		} else {
			$this->redirect('this');
		}
	}

	public function handleSetHomepage(int $id): void
	{
		$page = $this->pageManager->getById($id);
		if (!$page) {
			$this->error('Stránka nenalezena.');
		}

		$this->settingsManager->set(SettingsManager::HOMEPAGE_PAGE_ID, (string) $id);
		$this->flashMessage('Stránka nastavena jako úvodní.', 'success');
		if (!$page->isVisible()) {
			$this->flashMessage('Pozor: stránka není viditelná, na úvodu se proto zatím nezobrazí.', 'warning');
		}

		if ($this->isAjax()) {
			$this->redrawControl('flashes');
			$this['pagesGrid']->reload();
		} else {
			$this->redirect('this');
		}
	}

	public function handleUnsetHomepage(int $id): void
	{
		if ((int) $this->settingsManager->get(SettingsManager::HOMEPAGE_PAGE_ID) === $id) {
			$this->settingsManager->set(SettingsManager::HOMEPAGE_PAGE_ID, null);
			$this->flashMessage('Úvodní stránka zrušena.', 'success');
		}

		if ($this->isAjax()) {
			$this->redrawControl('flashes');
			$this['pagesGrid']->reload();
		} else {
			$this->redirect('this');
		}
	}

	public function actionEdit(int $id): void
	{
		$page = $this->pageManager->getById($id);
		if (!$page) $this->error('Stránka nenalezena.');

		$this['pageForm']->setDefaults([
			'title'       => $page->getTitle(),
			'link'        => $page->getLink(),
			'isVisible'   => $page->isVisible(),
			'navBlock'    => $page->getNavBlock()?->getId(),
			'footerBlock' => $page->getFooterBlock()?->getId(),
		]);

		$this->template->page = $page;
	}

	public function actionAdd(): void
	{
	}

	/** Dedicated editing screen of one `columns` container — its columns are its child components. */
	public function actionColumns(int $id): void
	{
		$component = $this->pageManager->getComponentById($id);
		if (!$component || $component->getType() !== 'columns') {
			$this->error('Komponenta nenalezena.');
		}

		$this->template->component = $component;
		$this->template->page = $component->getPage();
	}

	protected function createComponentPageForm(): Form
	{
		$form = new Form();
		$form->addProtection(FormHelper::ERR_CSRF);
		$form->addText('title', 'Název stránky')->setRequired();
		$form->addText('link', 'URL adresa (nechte prázdné pro automatické generování)');
		$form->addCheckbox('isVisible', 'Viditelná')->setDefaultValue(true);
		$form->addSelect('navBlock', 'Navigace', $this->layoutBlockManager->getSelectItems(LayoutBlock::TYPE_NAV))
			->setPrompt('— žádná —');
		$form->addSelect('footerBlock', 'Patička', $this->layoutBlockManager->getSelectItems(LayoutBlock::TYPE_FOOTER))
			->setPrompt('— žádná —');
		$form->addSubmit('send', 'Uložit');

		$form->onSuccess[] = [$this, 'pageFormSucceeded'];
		BootstrapFormRenderer::apply($form);
		return $form;
	}

	public function pageFormSucceeded(Form $form, \stdClass $data): void
	{
		$id = (int) $this->getParameter('id');
		if ($id) {
			$page = $this->pageManager->getById($id);
			if (!$page) {
				$this->error('Stránka nenalezena.');
			}
		} else {
			$page = new Page();
		}

		$link = Strings::webalize($data->link ?: $data->title);
		if ($link === '') {
			$link = 'stranka';
		}

		if ($data->link !== '' && $this->pageManager->linkExists($link, $id ?: null)) {
			$form['link']->addError('Stránka s touto URL adresou už existuje. Zvolte prosím jinou.');
			return;
		}

		// Auto-generated links get a -2, -3… suffix instead of failing (like WordPress)
		$link = $this->pageManager->buildUniqueLink($link, $id ?: null);

		$page->setTitle($data->title);
		$page->setLink($link);
		$page->setIsVisible($data->isVisible);
		$page->setNavBlock($data->navBlock !== null ? $this->layoutBlockManager->getById($data->navBlock) : null);
		$page->setFooterBlock($data->footerBlock !== null ? $this->layoutBlockManager->getById($data->footerBlock) : null);
		if ($id) {
			$page->markEdited($this->getUserEntity());
		} else {
			$page->markCreated($this->getUserEntity());
		}

		try {
			$this->pageManager->save($page);
		} catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException) {
			$form['link']->addError('Stránka s touto URL adresou už existuje. Zvolte prosím jinou.');
			return;
		}

		$this->flashMessage('Stránka uložena.', 'success');
		$this->redirect('edit', ['id' => $page->getId()]);
	}

	public function handleAddComponent(int $pageId, string $type, ?int $parentId = null): void
	{
		$this->checkSameSiteRequest();

		$page = $this->pageManager->getById($pageId);
		if (!$page) $this->error('Stránka nenalezena.');

		$parent = null;
		if ($parentId !== null) {
			$parent = $this->pageManager->getComponentById($parentId);
			if (!$parent || $parent->getPage()->getId() !== $pageId || $parent->getType() !== 'columns') {
				$this->error('Neplatná nadřazená komponenta.');
			}
			if (!in_array($type, self::CHILD_COMPONENT_TYPES, true)) {
				$this->error('Tento typ bloku nelze vložit jako sloupec.', IResponse::S400_BadRequest);
			}
			if (count($parent->getActiveChildren()) >= self::MAX_COLUMNS) {
				$this->flashMessage('Komponenta může mít nejvýše ' . self::MAX_COLUMNS . ' sloupce.', 'warning');
				$this->redirect('this');
			}
		}

		$defaultContent = match($type) {
			'hero'          => ['heading' => '', 'subheading' => '', 'button_text' => '', 'button_url' => '', 'image_url' => ''],
			'heading'       => ['text' => '', 'level' => 'h2', 'align' => 'start'],
			'text_block'    => ['content' => ''],
			'image'         => ['url' => '', 'alt' => '', 'caption' => ''],
			'columns'       => [],
			'cta'           => ['heading' => '', 'body' => '', 'button_text' => '', 'button_url' => '', 'button_style' => 'primary'],
			'contact_form'  => ['heading' => '', 'action_url' => '', 'button_text' => 'Odeslat', 'email_placeholder' => 'Váš e-mail…', 'message_placeholder' => 'Vaše zpráva…'],
			default         => [],
		};

		// max+1 within the sibling group (children of the same parent, or page roots);
		// count() could duplicate an existing order after deletions
		$siblings = $parent !== null ? $parent->getActiveChildren() : $page->getRootComponents();
		$maxSortOrder = -1;
		foreach ($siblings as $existing) {
			$maxSortOrder = max($maxSortOrder, $existing->getSortOrder());
		}

		$component = new PageComponent();
		$component->setPage($page);
		$component->setType($type);
		$component->setContent($defaultContent);
		$component->setSortOrder($maxSortOrder + 1);
		// 'sm' is the explicit default spacing shown pre-selected in the editor — new components start with it
		// rather than the ambiguous "use whatever the type happens to hardcode" null previously defaulted to.
		$component->setSpacingTop('sm');
		$component->setSpacingBottom('sm');
		$component->markCreated($this->getUserEntity());
		if ($parent !== null) {
			$parent->addChild($component);
		}

		$page->addComponent($component);
		$this->pageManager->save($page);

		$this->flashMessage('Komponenta přidána.', 'success');
		$this->redirect('this');
	}

	public function handleSaveComponent(int $componentId): void
	{
		$this->checkSameSiteRequest();

		$component = $this->pageManager->getComponentById($componentId);
		if (!$component) {
			$this->error('Komponenta nenalezena.');
		}

		$post = $this->getHttpRequest()->getPost();
		$type = $component->getType();

		$content = match($type) {
			'hero' => [
				'heading'     => $post['heading'] ?? '',
				'subheading'  => $post['subheading'] ?? '',
				'button_text' => $post['button_text'] ?? '',
				'button_url'  => $post['button_url'] ?? '',
				'image_url'   => $post['image_url'] ?? '',
			],
			'heading' => [
				'text'  => $post['text'] ?? '',
				'level' => in_array($post['level'] ?? '', ['h2', 'h3', 'h4', 'h5'], true) ? $post['level'] : 'h2',
				'align' => in_array($post['align'] ?? '', ['start', 'center', 'end'], true) ? $post['align'] : 'start',
			],
			'text_block' => [
				'content' => $this->richTextSanitizer->sanitize($post['content'] ?? ''),
			],
			'image' => [
				'url'     => $post['url'] ?? '',
				'alt'     => $post['alt'] ?? '',
				'caption' => $post['caption'] ?? '',
			],
			'columns' => [],
			'cta' => [
				'heading'      => $post['heading'] ?? '',
				'body'         => $this->richTextSanitizer->sanitize($post['body'] ?? ''),
				'button_text'  => $post['button_text'] ?? '',
				'button_url'   => $post['button_url'] ?? '',
				'button_style' => in_array($post['button_style'] ?? '', ['primary', 'secondary', 'outline-primary', 'outline-secondary'], true) ? $post['button_style'] : 'primary',
			],
			'contact_form' => [
				'heading'             => $post['heading'] ?? '',
				'action_url'          => $post['action_url'] ?? '',
				'button_text'         => $post['button_text'] ?? '',
				'email_placeholder'   => $post['email_placeholder'] ?? '',
				'message_placeholder' => $post['message_placeholder'] ?? '',
			],
			default => $component->getContent(),
		};

		// The editor no longer offers a blank "use type default" choice, so any posted value that isn't
		// one of the four real options (missing field, tampered request, …) falls back to 'sm', not null.
		$spacingTop = $post['spacingTop'] ?? '';
		$spacingBottom = $post['spacingBottom'] ?? '';
		$component->setContent($content);
		$component->setIsVisible(isset($post['isVisible']));
		$component->setSpacingTop(isset(PageComponent::SPACING_VALUES[$spacingTop]) ? $spacingTop : 'sm');
		$component->setSpacingBottom(isset(PageComponent::SPACING_VALUES[$spacingBottom]) ? $spacingBottom : 'sm');
		$component->markEdited($this->getUserEntity());
		$this->pageManager->save($component->getPage());

		$this->flashMessage('Komponenta uložena.', 'success');
		$this->redirect('this');
	}

	public function handleMoveComponent(int $componentId, string $direction): void
	{
		$this->checkSameSiteRequest();

		$offset = match ($direction) {
			'up'    => -1,
			'down'  => 1,
			default => null,
		};
		if ($offset === null) {
			$this->error('Neplatný směr přesunu.', IResponse::S400_BadRequest);
		}

		$component = $this->pageManager->getComponentById($componentId);
		if (!$component) {
			$this->error('Komponenta nenalezena.');
		}

		// Moves happen within the sibling group: children of the same parent, or page roots.
		// Both lists come ordered by sortOrder (OrderBy mappings on the collections).
		$parent = $component->getParent();
		$components = $parent !== null
			? $parent->getActiveChildren()
			: $component->getPage()->getRootComponents();
		$index = array_search($component, $components, true);
		$target = (int) $index + $offset;

		if ($index !== false && $target >= 0 && $target < count($components)) {
			[$components[$index], $components[$target]] = [$components[$target], $components[$index]];
			// Renumber the whole sequence — also normalizes any legacy duplicate sort orders
			foreach ($components as $order => $c) {
				$c->setSortOrder($order);
			}
			$this->pageManager->save($component->getPage());
		}

		$this->redirect('this');
	}

	public function handleDeleteComponent(int $componentId): void
	{
		$this->checkSameSiteRequest();

		$component = $this->pageManager->getComponentById($componentId);
		if ($component) {
			// Soft delete — a deleted container takes its columns with it
			$deletedBy = $this->getUserEntity();
			$component->markDeleted($deletedBy);
			foreach ($component->getActiveChildren() as $child) {
				$child->markDeleted($deletedBy);
			}
			$this->pageManager->save($component->getPage());
			$this->flashMessage('Komponenta smazána.', 'success');
		}
		$this->redirect('this');
	}

	// Same SameSite-cookie check Nette\Application\UI\Form does internally, for the raw HTML forms in edit.latte which carry no CSRF token.
	private function checkSameSiteRequest(): void
	{
		if (!$this->getHttpRequest()->isSameSite()) {
			$this->error('Neplatný požadavek (CSRF ochrana).', IResponse::S403_Forbidden);
		}
	}
}