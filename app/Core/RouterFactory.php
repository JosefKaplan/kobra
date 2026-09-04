<?php declare(strict_types=1);

namespace App\Core;

use Nette;
use Nette\Application\Routers\RouteList;


class RouterFactory
{
	use Nette\StaticClass;

	public static function createRouter(): RouteList
	{
		$router = new RouteList;

		$router->withModule('Admin')
			->addRoute('admin/<presenter>/<action>[/<id>]', 'User:default');

		$router->withModule('Front')
			->addRoute('<link>', 'Page:default')
			->addRoute('<presenter>/<action>[/<id>]', 'Home:default');

		return $router;
	}
}
