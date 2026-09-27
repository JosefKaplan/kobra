<?php declare(strict_types=1);

namespace App\Modules\Admin;

use App\Helpers\ImageUploadException;
use App\Helpers\ImageUploader;
use App\Models\UploadCleaner;
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

	#[Inject]
	public UploadCleaner $uploadCleaner;

	protected function startup(): void
	{
		parent::startup();

		if (!$this->getUser()->isLoggedIn()) {
			$this->redirect(':Front:Auth:login', ['backlink' => $this->storeRequest()]);
		}

		// no cron on the hosting — uploads never saved into content get cleared from here, once a day
		$this->uploadCleaner->sweepDaily();
	}

	/** The acting admin as a Doctrine entity, for the audit columns (created_by/edited_by). */
	protected function getUserEntity(): ?\App\Entity\User
	{
		return $this->baseUserManager->getById((int) $this->getUser()->getId());
	}

	/** AJAX endpoint behind the reusable image-upload widget (admin.js, `[data-image-upload]`). */
	public function handleUploadImage(): void
	{
		$this->checkSameSiteRequest();

		$file = $this->getHttpRequest()->getFile('image');
		if (!$file instanceof FileUpload) {
			// over post_max_size PHP drops $_FILES and $_POST entirely — say so instead of "no file sent"
			$contentLength = (int) ($this->getHttpRequest()->getHeader('Content-Length') ?? 0);
			$postMax = self::iniBytes((string) ini_get('post_max_size'));
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

	/** CSRF guard for the raw (non-Nette-Form) POST/GET signals of the admin. */
	protected function checkSameSiteRequest(): void
	{
		if (!$this->getHttpRequest()->isSameSite()) {
			$this->error('Neplatný požadavek (CSRF ochrana).', IResponse::S403_Forbidden);
		}
	}
}
