<?php declare(strict_types=1);

namespace App\Modules\Front;

use App\Entity\Page;
use App\Models\PageManager;
use App\Models\SettingsManager;
use Nette\Application\UI\Presenter;

abstract class BasePresenter extends Presenter
{
	#[\Nette\DI\Attributes\Inject]
	public PageManager $pageManager;

	#[\Nette\DI\Attributes\Inject]
	public SettingsManager $settingsManager;

	protected function beforeRender(): void
	{
		parent::beforeRender();

		// The selected homepage is reachable via "Úvod", so it is left out of the page menu
		$homepage = $this->getHomepage();
		$this->template->navPages = array_filter(
			$this->pageManager->findVisible(),
			fn (Page $p) => $homepage === null || $p->getId() !== $homepage->getId(),
		);
	}

	protected function getHomepage(): ?Page
	{
		$id = $this->settingsManager->get(SettingsManager::HOMEPAGE_PAGE_ID);

		return $id !== null ? $this->pageManager->getById((int) $id) : null;
	}

	protected function checkNotAuthenticated(): void
	{
		if ($this->getUser()->isLoggedIn()) {
			$this->redirect(':Admin:User:default');
		}
	}
}
