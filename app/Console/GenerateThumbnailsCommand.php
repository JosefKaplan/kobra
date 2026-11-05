<?php

declare(strict_types=1);

namespace App\Console;

use App\Helpers\Thumbnailer;
use App\Models\UploadBrowser;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** Thumbnails for existing uploads; new ones get theirs when uploaded. The hosting has no CLI, so run it locally and upload www/thumbs/. */
#[AsCommand(name: 'app:generate-thumbs', description: 'Vygeneruje chybějící náhledy obrázků do www/thumbs/')]
final class GenerateThumbnailsCommand extends Command
{
	public function __construct(
		private readonly Thumbnailer $thumbnailer,
	) {
		parent::__construct();
	}

	protected function configure(): void
	{
		$this->addOption('force', null, InputOption::VALUE_NONE, 'Vytvořit znovu i existující náhledy (např. po změně velikosti)');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$result = $this->thumbnailer->generateAll((bool) $input->getOption('force'));
		foreach ($result['failed'] as $path) {
			$output->writeln('<error>Náhled se nepovedl:</error> www/uploads/' . $path);
		}
		$output->writeln(sprintf(
			'Náhledy: %d (%s), obrázky, které náhled nepotřebují (malé, GIF, extrémní panorama): %d.',
			$result['ok'],
			UploadBrowser::formatSize($result['bytes']),
			$result['unneeded'],
		));

		return $result['failed'] === [] ? Command::SUCCESS : Command::FAILURE;
	}
}
