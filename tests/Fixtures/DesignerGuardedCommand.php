<?php namespace Tests\Fixtures;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use XF\Cli\Command\Designer\RequiresDesignerModeTrait;

/**
 * An add-on's command that guards itself the way core's designer commands do.
 */
class DesignerGuardedCommand extends Command
{
	use RequiresDesignerModeTrait;

	/**
	 * @return void
	 */
	protected function configure()
	{
		$this->setName('fixture:designer-guarded');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$output->writeln('ran');

		return 0;
	}
}
