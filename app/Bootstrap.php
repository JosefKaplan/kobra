<?php declare(strict_types=1);

namespace App;

use Nette;
use Nette\Bootstrap\Configurator;


class Bootstrap
{
	private readonly Configurator $configurator;
	private readonly string $rootDir;


	public function __construct()
	{
		$this->rootDir = dirname(__DIR__);
		$this->configurator = new Configurator;
		$this->configurator->setTempDirectory($this->rootDir . '/temp');
		// Nette derives %wwwDir% from the entry script's directory, so it's www/ under www/index.php but
		// bin/ under bin/console. Services that build filesystem paths from it (ImageUploader's
		// %wwwDir%/uploads in services.neon) must get the same answer from both — pin it explicitly.
		$this->configurator->addStaticParameters(['wwwDir' => $this->rootDir . '/www']);
	}


	public function bootWebApplication(): Nette\DI\Container
	{
		$this->initializeEnvironment();
		$this->setupContainer();
		return $this->configurator->createContainer();
	}


	public function initializeEnvironment(): void
	{
		//$this->configurator->setDebugMode('secret@23.75.345.200'); // zapnout pro remote IP
		$this->configurator->enableTracy($this->rootDir . '/log');

		$this->configurator->createRobotLoader()
			->addDirectory(__DIR__)
			->register();
	}


	private function setupContainer(): void
	{
		$configDir = $this->rootDir . '/config';
		$this->configurator->addConfig($configDir . '/common.neon');
		$this->configurator->addConfig($configDir . '/services.neon');

		// Machine-specific overrides (DB credentials, debug mode…) — not committed to git
		if (is_file($configDir . '/local.neon')) {
			$this->configurator->addConfig($configDir . '/local.neon');
		}
	}
}
