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
	/**
	 * Container component types and what they may hold: the child types allowed under each
	 * (never another container), and the maximum number of active children (null = unlimited).
	 * A type listed as a child of exactly one container (masonry_item) can only ever be created
	 * under that container — it's not offered at page level and rejected anywhere else.
	 */
	private const CONTAINER_TYPES = [
		// More columns than this stop being columns on any screen.
		'columns' => ['children' => ['heading', 'text_block', 'cta', 'image'], 'max' => 4],
		'masonry' => ['children' => ['masonry_item'], 'max' => null],
	];

	/** Child types that exist only inside their container (see CONTAINER_TYPES) — no page-level counterpart. */
	private const CHILD_ONLY_TYPES = ['masonry_item'];

	/** Content keys of the `contact_info` component — every one a plain-text field of its editor form. */
	private const CONTACT_INFO_KEYS = [
		'heading', 'intro', 'email', 'phone', 'studio_address', 'billing_address',
		'company', 'ico', 'dic', 'register', 'social', 'links',
	];

	/**
	 * Containers whose dedicated screen has a bulk "one child per dropped image" dropzone
	 * (handleCreateMasonryItems): the child type to create and which of its content keys
	 * receives the uploaded URL.
	 */
	private const BULK_IMAGE_CHILDREN = [
		'masonry' => ['child' => 'masonry_item', 'urlKey' => 'main_image_url', 'one' => 'Dlaždice přidána.', 'many' => 'Přidáno dlaždic: %d.'],
	];

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
			->setConfirmation(new StringConfirmation('Opravdu smazat tuto stránku natrvalo, včetně všech bloků a jejich obrázků? Nelze vrátit.'));
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

		$this->pageManager->delete($id);
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
		$this->setupContainerScreen($id, 'columns');
	}

	/** Dedicated editing screen of one `masonry` container — its tiles are `masonry_item` child components. */
	public function actionMasonry(int $id): void
	{
		$this->setupContainerScreen($id, 'masonry');
	}

	private function setupContainerScreen(int $id, string $type): void
	{
		$component = $this->pageManager->getComponentById($id);
		if (!$component || $component->getType() !== $type) {
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
			if (!$parent || $parent->getPage()->getId() !== $pageId || !isset(self::CONTAINER_TYPES[$parent->getType()])) {
				$this->error('Neplatná nadřazená komponenta.');
			}
			$container = self::CONTAINER_TYPES[$parent->getType()];
			if (!in_array($type, $container['children'], true)) {
				$this->error('Tento typ bloku nelze vložit do této komponenty.', IResponse::S400_BadRequest);
			}
			if ($container['max'] !== null && count($parent->getOrderedChildren()) >= $container['max']) {
				$this->flashMessage('Komponenta může mít nejvýše ' . $container['max'] . ' vnořené bloky.', 'warning');
				$this->redirect('this');
			}
		} elseif ($this->isChildOnlyType($type)) {
			$this->error('Tento typ bloku lze vložit jen do nadřazené komponenty.', IResponse::S400_BadRequest);
		}

		$this->persistNewComponent($page, $parent, $type, self::defaultContent($type));

		$this->flashMessage('Komponenta přidána.', 'success');
		$this->redirect('this');
	}

	/**
	 * Bulk creation of `masonry_item` tiles behind the masonry screen's dropzone: the browser
	 * uploads the picked files one at a time through uploadImage! (same single-file endpoint as
	 * every other image widget, so PHP's request-wide post_max_size/max_file_uploads never
	 * apply) and then POSTs the resulting URLs here — one new tile per URL, added at the top
	 * of the grid in upload order.
	 */
	public function handleCreateMasonryItems(int $parentId): void
	{
		$this->createChildrenFromUploads($parentId, 'masonry');
	}

	private function createChildrenFromUploads(int $parentId, string $parentType): void
	{
		$this->checkSameSiteRequest();
		$bulk = self::BULK_IMAGE_CHILDREN[$parentType];

		$parent = $this->pageManager->getComponentById($parentId);
		if (!$parent || $parent->getType() !== $parentType) {
			$this->error('Komponenta nenalezena.');
		}

		$post = $this->getHttpRequest()->getPost();
		$urls = array_values(array_filter(array_map(
			fn ($url) => trim((string) $url),
			is_array($post['urls'] ?? null) ? $post['urls'] : [],
		), fn (string $url) => $url !== ''));

		// newest-first container: created back to front, so the batch reads in upload order at the top
		foreach ($parent->isNewestFirst() ? array_reverse($urls) : $urls as $url) {
			$content = self::defaultContent($bulk['child']);
			$content[$bulk['urlKey']] = $url;
			// One flush for the whole batch (below): all-or-nothing, and no per-child changeset pass
			$this->persistNewComponent($parent->getPage(), $parent, $bulk['child'], $content, flush: false);
		}
		if ($urls !== []) {
			$this->pageManager->save($parent->getPage());
			$this->flashMessage(count($urls) === 1 ? $bulk['one'] : sprintf($bulk['many'], count($urls)), 'success');
		}
		// Files the browser couldn't upload (wrong type, too big, server error) — re-shown here because
		// the redirect below would otherwise replace the page the dropzone reported them on.
		$failed = array_values(array_filter(array_map(
			fn ($message) => Strings::truncate(trim((string) $message), 200),
			is_array($post['failed'] ?? null) ? array_slice($post['failed'], 0, 20) : [],
		), fn (string $message) => $message !== ''));
		if ($failed !== []) {
			$this->flashMessage('Nepodařilo se nahrát: ' . implode(' · ', $failed), 'warning');
		}
		$this->redirect('this');
	}

	/** A type allowed only as a child of some container — never offered or accepted at page level. */
	private function isChildOnlyType(string $type): bool
	{
		return in_array($type, self::CHILD_ONLY_TYPES, true);
	}

	/** @return array<string, mixed> */
	private static function defaultContent(string $type): array
	{
		return match($type) {
			'hero'          => ['heading' => '', 'subheading' => '', 'button_text' => '', 'button_url' => '', 'image_url' => ''],
			'heading'       => ['text' => '', 'level' => 'h2', 'align' => 'start'],
			'text_block'    => ['content' => ''],
			'image'         => ['url' => '', 'alt' => '', 'caption' => ''],
			'columns'       => [],
			'masonry'       => [],
			'masonry_item'  => ['main_image_url' => '', 'main_image_alt' => '', 'heading' => '', 'text' => '', 'gallery' => []],
			'cta'           => ['heading' => '', 'body' => '', 'button_text' => '', 'button_url' => '', 'button_style' => 'primary'],
			'contact_form'  => ['heading' => '', 'action_url' => '', 'button_text' => 'Odeslat', 'email_placeholder' => 'Váš e-mail…', 'message_placeholder' => 'Vaše zpráva…'],
			'contact_info'  => array_fill_keys(self::CONTACT_INFO_KEYS, ''),
			default         => [],
		};
	}

	/**
	 * Builds and attaches one new component with the highest sort order of its sibling group
	 * (children of $parent, or the page's roots) — the end of the list, or its top in a newest-first
	 * container — and, unless $flush is false, saves the page. With $flush false the
	 * caller saves once for a whole batch — the sibling scan still sees the earlier additions,
	 * since addChild()/addComponent() update the in-memory collections immediately.
	 *
	 * @param array<string, mixed> $content
	 */
	private function persistNewComponent(Page $page, ?PageComponent $parent, string $type, array $content, bool $flush = true): PageComponent
	{
		// max+1 within the sibling group; count() could duplicate an existing order after deletions
		$siblings = $parent !== null ? $parent->getOrderedChildren() : $page->getRootComponents();
		$maxSortOrder = -1;
		foreach ($siblings as $existing) {
			$maxSortOrder = max($maxSortOrder, $existing->getSortOrder());
		}

		$component = new PageComponent();
		$component->setPage($page);
		$component->setType($type);
		$component->setContent($content);
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
		if ($flush) {
			$this->pageManager->save($page);
		}

		return $component;
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
			'masonry' => [],
			'masonry_item' => [
				'main_image_url' => trim((string) ($post['main_image_url'] ?? '')),
				'main_image_alt' => trim((string) ($post['main_image_alt'] ?? '')),
				'heading'        => trim((string) ($post['heading'] ?? '')),
				'text'           => $this->richTextSanitizer->sanitize((string) ($post['text'] ?? '')),
				// Gallery rows arrive as gallery[<index>][image_url|alt] (see masonry.js); indices may
				// have gaps after row removal, array_values() reindexes them. Their order is the
				// POST-body order — that's the only place gallery order lives.
				'gallery'        => array_values(array_filter(array_map(
					fn ($g) => [
						'image_url' => trim((string) ($g['image_url'] ?? '')),
						'alt'       => trim((string) ($g['alt'] ?? '')),
					],
					is_array($post['gallery'] ?? null) ? $post['gallery'] : [],
				), fn (array $g) => $g['image_url'] !== '')),
			],
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
			// all plain text (auto-escaped on render); `social`/`links` are "Text | URL" lines for LinkList
			'contact_info' => array_map(
				fn (string $key) => trim((string) ($post[$key] ?? '')),
				array_combine(self::CONTACT_INFO_KEYS, self::CONTACT_INFO_KEYS),
			),
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

		// Moves happen within the sibling group: children of the same parent, or page roots,
		// both in display order (ascending sortOrder, or descending in a newest-first container).
		$parent = $component->getParent();
		$components = $parent !== null
			? $parent->getOrderedChildren()
			: $component->getPage()->getRootComponents();
		$index = array_search($component, $components, true);
		$target = (int) $index + $offset;

		if ($index !== false && $target >= 0 && $target < count($components)) {
			$orders = array_map(fn (PageComponent $c) => $c->getSortOrder(), $components);
			if (count(array_unique($orders)) === count($orders)) {
				// distinct orders: the two neighbours swap their numbers, whichever way the list runs
				$other = $components[$target];
				$otherOrder = $other->getSortOrder();
				$other->setSortOrder($component->getSortOrder());
				$component->setSortOrder($otherOrder);
			} else {
				// legacy duplicate orders: renumber the whole group in display order
				[$components[$index], $components[$target]] = [$components[$target], $components[$index]];
				$last = count($components) - 1;
				foreach ($components as $position => $c) {
					$c->setSortOrder($parent?->isNewestFirst() ? $last - $position : $position);
				}
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
			// a container takes its children (columns, masonry tiles) with it, images included
			$this->pageManager->deleteComponent($component);
			$this->flashMessage('Komponenta smazána.', 'success');
		}
		$this->redirect('this');
	}
}