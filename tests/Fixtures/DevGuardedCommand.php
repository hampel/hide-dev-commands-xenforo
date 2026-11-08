<?php namespace Tests\Fixtures;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use XF\Cli\Command\Development\RequiresDevModeTrait;

/**
 * An add-on's command that guards itself the way core's development commands do.
 */
class DevGuardedCommand extends Command
{
	use RequiresDevModeTrait;

	/**
	 * @return void
	 */
	protected function configure()
	{
		$this->setName('fixture:dev-guarded');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$output->writeln('ran');

		return 0;
	}
}
