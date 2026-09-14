<?php

declare(strict_types=1);

namespace App\Models;

use App\Entity\Upload;
use Doctrine\ORM\EntityManagerInterface;

class UploadManager
{
	public function __construct(
		private readonly EntityManagerInterface $em,
	) {
	}

	public function save(Upload $upload): void
	{
		$this->em->persist($upload);
		$this->em->flush();
	}
}