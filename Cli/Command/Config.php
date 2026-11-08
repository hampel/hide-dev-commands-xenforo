<?php namespace Hampel\HideDevCommands\Cli\Command;

use Hampel\HideDevCommands\Cli\CapturesConsole;
use Hampel\HideDevCommands\Cli\RendersReport;
use Hampel\HideDevCommands\CommandHider;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Which modes are on, and which command families are hidden as a result. It reads and prints; it
 * writes nothing. hide-dev-commands:validate is the command that proves the hiding still works.
 */
class Config extends Command
{
	use CapturesConsole;
	use RendersReport;

	/**
	 * @return void
	 */
	protected function configure()
	{
		$this
			->setName('hide-dev-commands:config')
			->setDescription('Show which development command families are hidden, and why')
			->addOption('commands', null, InputOption::VALUE_NONE, 'List each hidden command by name');
	}

	protected function errorLogPrefix(): string
	{
		return 'HideDevCommands: ';
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$this->report = $output;

		$this->heading('Hide Development Commands');
		$addOn = \XF::app()->addOnManager()->getById('Hampel/HideDevCommands');
		$this->detail('add-on', $addOn ? (string) $addOn->getJson()['version_string'] : '');
		$this->detail('XenForo', \XF::$version);

		$this->heading('Modes');
		foreach (['development', 'designer'] AS $mode)
		{
			$this->detail("{$mode} mode", (CommandHider::modeEnabled($mode) ? 'on' : 'off') . ' <fg=cyan>(config.php)</>');
		}

		$this->heading('Commands');
		$console = CommandHider::console() ?? $this->getApplication();
		if ($console === null)
		{
			$this->detail('console', '');

			return 0;
		}

		$listed = [];
		foreach (CommandHider::families($console) AS $prefix => $commands)
		{
			$family = CommandHider::FAMILIES[$prefix];
			$hidden = array_filter($commands, function (Command $command)
			{
				return $command->isHidden();
			});

			$summary = !$commands && !is_dir(CommandHider::familyDirectory($prefix))
				? $this->muted('not in XenForo ' . \XF::$version)
				: $this->summary(count($commands), count($hidden), $family['mode']);
			$this->detail($family['label'], $summary);
			$listed = array_merge($listed, array_keys($hidden));
		}

		if ($input->getOption('commands') && $listed)
		{
			$this->heading('Hidden');
			foreach ($listed AS $name)
			{
				$this->report->writeln("  {$name}");
			}
		}

		return 0;
	}

	private function summary(int $total, int $hidden, string $mode): string
	{
		if ($total === 0)
		{
			return 'none registered';
		}

		$on = CommandHider::modeEnabled($mode);

		if ($on && $hidden === 0)
		{
			return "{$total}, shown " . $this->muted("({$mode} mode is on)");
		}

		if (!$on && $hidden === $total)
		{
			return "{$total}, hidden and blocked " . $this->muted("({$mode} mode is off)");
		}

		if (!$on && $hidden === 0)
		{
			return "{$total}, shown although {$mode} mode is off - see hide-dev-commands:validate";
		}

		return "{$total}, {$hidden} hidden";
	}
}
