<?php

declare(strict_types=1);

namespace App\Models;

use App\Entity\Setting;
use Doctrine\ORM\EntityManagerInterface;

class SettingsManager
{
	/** Which CMS page is served as the homepage (WordPress: "static front page") */
	public const HOMEPAGE_PAGE_ID = 'homepage_page_id';

	public function __construct(
		private readonly EntityManagerInterface $em,
	) {
	}

	public function get(string $name, ?string $default = null): ?string
	{
		$setting = $this->em->getRepository(Setting::class)->findOneBy(['name' => $name]);

		return $setting?->getValue() ?? $default;
	}

	public function set(string $name, ?string $value): void
	{
		$setting = $this->em->getRepository(Setting::class)->findOneBy(['name' => $name]);

		if ($value === null) {
			if ($setting !== null) {
				$this->em->remove($setting);
				$this->em->flush();
			}
			return;
		}

		if ($setting === null) {
			$setting = new Setting();
			$setting->setName($name);
			$this->em->persist($setting);
		}

		$setting->setValue($value);
		$this->em->flush();
	}
}
