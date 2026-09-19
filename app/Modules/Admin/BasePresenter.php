<?php declare(strict_types=1);

namespace App\Modules\Admin;

use App\Helpers\ImageUploadException;
use App\Helpers\ImageUploader;
use App\Models\UserManager;
use Nette\Application\UI\Presenter;
use Nette\DI\Attributes\Inject;
use Nette\Http\FileUpload;
use Nette\Http\IResponse;

abstract class BasePresenter extends Presenter
{
	#[Inject]
	public UserManager $baseUserManager;

	#[Inject]
	public ImageUploader $imageUploader;

	protected function startup(): void
	{
		parent::startup();

		if (!$this->getUser()->isLoggedIn()) {
			$this->redirect(':Front:Auth:login', ['backlink' => $this->storeRequest()]);
		}
	}

	/** The acting admin as a Doctrine entity, for the audit columns (created_by/edited_by/deleted_by). */
	protected function getUserEntity(): ?\App\Entity\User
	{
		return $this->baseUserManager->getById((int) $this->getUser()->getId());
	}

	/**
	 * AJAX endpoint behind the reusable image-upload widget (admin.js, `input[data-image-upload]`)
	 * — used by any admin field that holds an image URL (page components' images, a masonry
	 * tile's main image and gallery, a footer block's carousel), so it lives here, reachable as
	 * `uploadImage!` from every admin presenter, not tied to a specific component. Responds with JSON, never redirects.
	 */
	public function handleUploadImage(): void
	{
		$this->checkSameSiteRequest();

		$file = $this->getHttpRequest()->getFile('image');
		if (!$file instanceof FileUpload) {
			// A body over php.ini's post_max_size never reaches us as a file at all — PHP drops
			// $_FILES (and $_POST) wholesale, so without this check the admin would be told they
			// sent nothing. (The per-file upload_max_filesize case does arrive, as UPLOAD_ERR_INI_SIZE,
			// and is worded by ImageUploader.)
			$contentLength = (int) ($this->getHttpRequest()->getHeader('Content-Length') ?? 0);
			$postMax = self::iniBytes((string) ini_get('post_max_size'));
			if ($postMax > 0 && $contentLength > $postMax) {
				$this->sendJson(['error' => 'Soubor je příliš velký pro aktuální nastavení serveru (post_max_size v php.ini).']);
			}
			// sendJson() never actually returns (it throws Nette\Application\AbortException
			// internally) — PHPStan's Nette stubs know this, so $file is narrowed below.
			$this->sendJson(['error' => 'Nebyl nahrán žádný soubor.']);
		}

		try {
			$upload = $this->imageUploader->upload($file, $this->getUserEntity());
		} catch (ImageUploadException $e) {
			$this->sendJson(['error' => $e->getMessage()]);
		}

		$this->sendJson(['url' => $upload->getUrl()]);
	}

	/** php.ini shorthand ("8M", "512K", "1G", plain bytes) to bytes; 0 for empty/unlimited. */
	protected static function iniBytes(string $value): int
	{
		$value = trim($value);
		if ($value === '' || $value === '-1') {
			return 0;
		}
		$unit = strtolower(substr($value, -1));
		$number = (int) $value;
		return match ($unit) {
			'g' => $number * 1024 * 1024 * 1024,
			'm' => $number * 1024 * 1024,
			'k' => $number * 1024,
			default => $number,
		};
	}

	// Same SameSite-cookie check Nette\Application\UI\Form does internally, for the raw HTML forms in edit.latte which carry no CSRF token.
	/**
	 * CSRF guard for the raw (non-Nette-Form) POST/GET signals of the admin: the same
	 * SameSite-cookie check Nette\Application\UI\Form does internally.
	 */
	protected function checkSameSiteRequest(): void
	{
		if (!$this->getHttpRequest()->isSameSite()) {
			$this->error('Neplatný požadavek (CSRF ochrana).', IResponse::S403_Forbidden);
		}
	}
}
