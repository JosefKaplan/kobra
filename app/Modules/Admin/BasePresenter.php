<?php declare(strict_types=1);

namespace App\Modules\Admin;

use App\Models\UserManager;
use Nette\Application\UI\Presenter;
use Nette\DI\Attributes\Inject;

abstract class BasePresenter extends Presenter
{
	#[Inject]
	public UserManager $baseUserManager;

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
}
