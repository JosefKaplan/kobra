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
use Nette\Application\BadRequestException;
use Nette\Application\UI\Form;
use Nette\DI\Attributes\Inject;
use Nette\Http\IResponse;
use Nette\Schema\Elements\Structure;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
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

	// Options of the editor's selects (value => label; the first is the default): contentSchema() accepts
	// exactly these values and _componentEditor.latte lists them, so the two can't drift apart.
	public const HEADING_LEVELS = ['h2' => 'H2 – Hlavní nadpis', 'h3' => 'H3 – Podnadpis', 'h4' => 'H4 – Menší nadpis', 'h5' => 'H5 – Malý nadpis'];
	public const HEADING_ALIGNMENTS = ['start' => 'Vlevo', 'center' => 'Na střed', 'end' => 'Vpravo'];
	public const BUTTON_STYLES = ['primary' => 'Primary', 'secondary' => 'Secondary', 'outline-primary' => 'Outline Primary', 'outline-secondary' => 'Outline Secondary'];

	#[Inject]
	public PageManager $pageManager;

	#[Inject]
	public SettingsManager $settingsManager;

	#[Inject]
	public RichTextSanitizer $richTextSanitizer;

	#[Inject]
	public LayoutBlockManager $layoutBlockManager;

	protected bool $adminOnly = true;

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

		$this->persistNewComponent($page, $parent, $type, $this->defaultContent($type));

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
		$bulk = self::BULK_IMAGE_CHILDREN[$parentType];

		$parent = $this->pageManager->getComponentById($parentId);
		if (!$parent || $parent->getType() !== $parentType) {
			$this->error('Komponenta nenalezena.');
		}

		$post = $this->getHttpRequest()->getPost();
		$nonEmpty = fn (array $values): array => array_values(array_filter($values, fn (string $value): bool => $value !== ''));
		$urls = $this->processPostedData(Expect::listOf(self::trimmedString())->transform($nonEmpty), $post['urls'] ?? []);
		// Files the browser couldn't upload (wrong type, too big, server error) — re-shown here because
		// the redirect below would otherwise replace the page the dropzone reported them on.
		// Validated with the URLs, before anything is saved: a rejected request must create no tiles.
		$failed = $this->processPostedData(
			Expect::listOf(self::trimmedString()->transform(fn (string $message): string => Strings::truncate($message, 200)))
				->transform(fn (array $messages): array => $nonEmpty(array_slice($messages, 0, 20))),
			$post['failed'] ?? [],
		);

		$defaults = $this->defaultContent($bulk['child']);
		// newest-first container: created back to front, so the batch reads in upload order at the top
		foreach ($parent->isNewestFirst() ? array_reverse($urls) : $urls as $url) {
			$content = $defaults;
			$content[$bulk['urlKey']] = $url;
			// One flush for the whole batch (below): all-or-nothing, and no per-child changeset pass
			$this->persistNewComponent($parent->getPage(), $parent, $bulk['child'], $content, flush: false);
		}
		if ($urls !== []) {
			$this->pageManager->save($parent->getPage());
			$this->flashMessage(count($urls) === 1 ? $bulk['one'] : sprintf($bulk['many'], count($urls)), 'success');
		}
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

	/**
	 * Each type's content keys with their defaults and how a posted value is normalized — the one
	 * source of a new component's default content (defaultContent()) and of what handleSaveComponent()
	 * accepts. Plain text is trimmed, rich text sanitized; null for an unknown type.
	 */
	private function contentSchema(string $type): ?Structure
	{
		$text = self::trimmedString(...);
		$richText = fn (): Schema => Expect::string('')->transform(fn (string $html): string => $this->richTextSanitizer->sanitize($html));
		$choice = fn (array $options): Schema => Expect::anyOf(...array_keys($options))->firstIsDefault();

		$fields = match ($type) {
			'hero'         => ['heading' => $text(), 'subheading' => $text(), 'button_text' => $text(), 'button_url' => $text(), 'image_url' => $text()],
			'heading'      => [
				'text'  => $text(),
				'level' => $choice(self::HEADING_LEVELS),
				'align' => $choice(self::HEADING_ALIGNMENTS),
			],
			'text_block'   => ['content' => $richText()],
			'image'        => ['url' => $text(), 'alt' => $text(), 'caption' => $text()],
			'columns', 'masonry' => [],
			'masonry_item' => [
				'main_image_url' => $text(),
				'main_image_alt' => $text(),
				'heading'        => $text(),
				'text'           => $richText(),
				'gallery'        => self::imageListSchema(),
			],
			'cta'          => [
				'heading'      => $text(),
				'body'         => $richText(),
				'button_text'  => $text(),
				'button_url'   => $text(),
				'button_style' => $choice(self::BUTTON_STYLES),
			],
			'contact_form' => [
				'heading'             => $text(),
				'action_url'          => $text(),
				'button_text'         => $text('Odeslat'),
				'email_placeholder'   => $text('Váš e-mail…'),
				'message_placeholder' => $text('Vaše zpráva…'),
			],
			// all plain text (auto-escaped on render); `social`/`links` are "Text | URL" lines for LinkList
			'contact_info' => array_fill_keys(self::CONTACT_INFO_KEYS, $text()),
			default        => null,
		};

		return $fields !== null ? Expect::structure($fields)->castTo('array') : null;
	}

	/** @return array<string, mixed> */
	private function defaultContent(string $type): array
	{
		// an unknown type (a tampered or stale link) is never created
		$schema = $this->contentSchema($type) ?? throw new BadRequestException('Neznámý typ bloku.', IResponse::S400_BadRequest);

		return $this->processPostedData($schema, []);
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
		$component = $this->pageManager->getComponentById($componentId);
		if (!$component) {
			$this->error('Komponenta nenalezena.');
		}

		$post = $this->getHttpRequest()->getPost();
		$schema = $this->contentSchema($component->getType());
		$content = $schema !== null
			// the form also posts the generic fields below — only the type's own keys go into the content
			? $this->processPostedData($schema, array_intersect_key($post, $schema->getShape()))
			: $component->getContent();

		// Generic to every type. A missing value is 'sm', the editor's default — a masonry tile's form has no
		// spacing selects; never null, since the editor offers no blank "use type default" choice.
		$spacing = Expect::anyOf(...array_keys(PageComponent::SPACING_OPTIONS))->default('sm');
		['spacingTop' => $spacingTop, 'spacingBottom' => $spacingBottom] = $this->processPostedData(
			Expect::structure(['spacingTop' => $spacing, 'spacingBottom' => $spacing])->castTo('array'),
			array_intersect_key($post, ['spacingTop' => true, 'spacingBottom' => true]),
		);
		$component->setContent($content);
		$component->setIsVisible(isset($post['isVisible']));
		$component->setSpacingTop($spacingTop);
		$component->setSpacingBottom($spacingBottom);
		$component->markEdited($this->getUserEntity());
		$this->pageManager->save($component->getPage());

		$this->flashMessage('Komponenta uložena.', 'success');
		$this->redirect('this');
	}

	public function handleMoveComponent(int $componentId, string $direction): void
	{
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
		$component = $this->pageManager->getComponentById($componentId);
		if ($component) {
			// a container takes its children (columns, masonry tiles) with it, images included
			$this->pageManager->deleteComponent($component);
			$this->flashMessage('Komponenta smazána.', 'success');
		}
		$this->redirect('this');
	}
}