<?php declare(strict_types=1);

namespace App\Modules\Front\Home;

use App\Modules\Front\BasePresenter;

final class HomePresenter extends BasePresenter
{
	public function renderDefault(): void
	{
		// When an admin picked a homepage (WordPress-style "static front page"),
		// render that CMS page; otherwise the template shows the default welcome hero.
		$homepage = $this->getHomepage();
		if ($homepage !== null && $homepage->isVisible()) {
			$this->template->page = $homepage;
		}
	}
}
