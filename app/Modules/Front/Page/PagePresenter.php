<?php declare(strict_types=1);

namespace App\Modules\Front\Page;

use App\Modules\Front\BasePresenter;

class PagePresenter extends BasePresenter
{
    public function renderDefault(string $link): void
    {
        $page = $this->pageManager->getByLink($link);
        if (!$page || !$page->isVisible()) {
            $this->error('Stránka nenalezena');
        }

        $homepage = $this->getHomepage();
        if ($homepage !== null && $homepage->getId() === $page->getId()) {
            $this->redirectPermanent(':Front:Home:default');
        }

        $this->template->page = $page;
    }
}
