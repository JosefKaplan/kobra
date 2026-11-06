<?php declare(strict_types=1);

namespace App\Modules\Admin;

use App\Helpers\FormHelper;
use App\Helpers\ImageUploadException;
use App\Helpers\ImageUploader;
use App\Models\UploadCleaner;
use App\Models\UserManager;
use Nette\Application\BadRequestException;
use Nette\Application\UI\Presenter;
use Nette\DI\Attributes\Inject;
use Nette\Http\FileUpload;
use Nette\Http\IResponse;
use Nette\Schema\Elements\Type;
use Nette\Schema\Expect;
use Nette\Schema\Processor;
use Nette\Schema\Schema;
use Nette\Schema\ValidationException;

abstract class BasePresenter extends Presenter
{
	#[Inject]
	public UserManager $baseUserManager;

	#[Inject]
	public ImageUploader $imageUploader;

	#[Inject]
	public UploadCleaner $uploadCleaner;

	/** Set in presenters only admins may use; a plain user gets sent to the user list (their own profile). */
	protected bool $adminOnly = false;

	protected function startup(): void
	{
		parent::startup();

		if (!$this->getUser()->isLoggedIn()) {
			$this->redirect(':Front:Auth:login', ['backlink' => $this->storeRequest()]);
		}

		if ($this->adminOnly && !$this->getUser()->isInRole('admin')) {
			$this->flashMessage(FormHelper::ERR_ACCESS_DENIED, 'danger');
			$this->redirect(':Admin:User:default');
		}

		// no cron on the hosting — uploads never saved into content get cleared from here, once a day
		$this->uploadCleaner->sweepDaily();
	}

	/** The acting admin as a Doctrine entity, for the audit columns (created_by/edited_by). */
	protected function getUserEntity(): ?\App\Entity\User
	{
		return $this->baseUserManager->getById((int) $this->getUser()->getId());
	}

	/**
	 * AJAX endpoint behind the reusable image-upload widget (admin.js, `[data-image-upload]`).
	 * Like every handle*() signal, Nette rejects it unless same-origin (UI\AccessPolicy) — the CSRF guard.
	 */
	public function handleUploadImage(): void
	{
		$file = $this->getHttpRequest()->getFile('image');
		if (!$file instanceof FileUpload) {
			// over post_max_size PHP drops $_FILES and $_POST entirely — say so instead of "no file sent"
			$contentLength = (int) ($this->getHttpRequest()->getHeader('Content-Length') ?? 0);
			$postMax = ini_parse_quantity((string) ini_get('post_max_size'));
			if ($postMax > 0 && $contentLength > $postMax) {
				$this->sendJson(['error' => 'Soubor je příliš velký pro aktuální nastavení serveru (post_max_size v php.ini).']);
			}
			$this->sendJson(['error' => 'Nebyl nahrán žádný soubor.']);
		}

		try {
			$upload = $this->imageUploader->upload($file, $this->getUserEntity());
		} catch (ImageUploadException $e) {
			$this->sendJson(['error' => $e->getMessage()]);
		}

		$this->sendJson(['url' => $upload->getUrl()]);
	}

	/** A plain-text value: a string, trimmed; $default when not posted. */
	protected static function trimmedString(string $default = ''): Type
	{
		return Expect::string($default)->transform(fn (string $value): string => trim($value));
	}

	/**
	 * Rows of the shared image list editor (imageListEditor in Page/_componentEditor.latte), posted as
	 * <field>[<index>][image_url|alt(|link_url)]: the indices may be out of sequence or `new<n>` (masonry.js),
	 * only the body order counts. Trimmed, rows without an image dropped, re-indexed.
	 */
	protected static function imageListSchema(bool $withLink = false): Schema
	{
		$row = ['image_url' => self::trimmedString(), 'alt' => self::trimmedString()]
			+ ($withLink ? ['link_url' => self::trimmedString()] : []);

		return Expect::arrayOf(Expect::structure($row)->castTo('array'))
			->transform(fn (array $rows): array => array_values(array_filter($rows, fn (array $row): bool => $row['image_url'] !== '')));
	}

	/** Posted data normalized by $schema; a value of the wrong shape (a tampered request) is a 400, never stored. */
	protected function processPostedData(Schema $schema, mixed $data): mixed
	{
		try {
			return (new Processor)->process($schema, $data);
		} catch (ValidationException $e) {
			throw new BadRequestException('Neplatná data formuláře.', IResponse::S400_BadRequest, $e);
		}
	}
}
