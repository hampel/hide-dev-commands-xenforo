<?php namespace Hampel\HideDevCommands\Cli\Command;

use Hampel\HideDevCommands\Cli\CapturesConsole;
use Hampel\HideDevCommands\Cli\RendersReport;
use Hampel\HideDevCommands\CommandHider;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Whether the hiding still works on this XenForo. It depends on internals no XenForo release
 * promises to keep - commands registered before the app starts, Symfony calling setApplication()
 * from add(), the app_cli_start_end event, and core keeping its commands in the namespaces this
 * matches - so this is the command to run after every XenForo upgrade.
 *
 * It sends nothing and writes nothing, so --unattended is accepted, for the command line a monitor
 * runs against every add-on, and changes nothing.
 *
 * Exit code: 1 if any check failed, 0 otherwise - a warning does not fail. --strict makes a warning
 * exit 2 and changes nothing else.
 */
class Validate extends Command
{
	use CapturesConsole;
	use RendersReport;

	/**
	 * @return void
	 */
	protected function configure()
	{
		$this
			->setName('hide-dev-commands:validate')
			->setDescription('Check that development commands are hidden and blocked when their mode is off')
			->addOption('unattended', null, InputOption::VALUE_NONE,
				'Accepted for a monitor\'s command line; this command sends nothing, so it changes nothing')
			->addOption('strict', null, InputOption::VALUE_NONE,
				'Exit 2 when there are warnings and no failures, instead of 0');
	}

	protected function errorLogPrefix(): string
	{
		return 'HideDevCommands: ';
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$this->report = $output;

		$this->checkSection('Mechanism');
		$console = $this->probe('console captured', function ()
		{
			return $this->captured();
		});
		$this->probe('listener ran', function ()
		{
			$this->listenerRan();
		});

		$this->checkSection('Commands');
		if ($console === null)
		{
			$this->checkSkip('families', 'no console application to inspect');
		}
		else
		{
			$families = $this->probe('families', function () use ($console)
			{
				return CommandHider::families($console);
			});
			foreach ($families ?? [] AS $prefix => $commands)
			{
				$this->family($prefix, $commands);
			}
		}

		$this->checkSection('Blocking');
		if ($console === null)
		{
			$this->checkSkip('blocked', 'no console application to inspect');
			$this->checkSkip('xf-dev:info', 'no console application to inspect');
		}
		else
		{
			$this->probe('blocked', function () use ($families)
			{
				$this->blocked($families ?? []);
			});
			$this->probe('xf-dev:info', function () use ($console)
			{
				$this->refusesByName($console);
			});
		}

		$this->report->writeln('');

		return $this->checkExitCode((bool) $input->getOption('strict'));
	}

	private function captured(): ?Application
	{
		$console = CommandHider::console();
		if ($console === null)
		{
			$this->checkFail('console captured',
				'not passed to setApplication() - has Symfony\'s Application::add() changed?');

			return null;
		}

		$this->checkOk('console captured', 'through setApplication()');

		return $console;
	}

	private function listenerRan(): void
	{
		if (CommandHider::applied())
		{
			$this->checkOk('listener ran', 'app_cli_start_end');

			return;
		}

		$this->checkFail('listener ran',
			'app_cli_start_end did not reach this add-on - is the listener enabled, and is the event still fired?');
	}

	/**
	 * @param array<string, Command> $commands
	 */
	private function family(string $prefix, array $commands): void
	{
		$label = CommandHider::FAMILIES[$prefix]['label'];
		$mode = CommandHider::FAMILIES[$prefix]['mode'];
		$total = count($commands);

		if ($total === 0)
		{
			if (is_dir(CommandHider::familyDirectory($prefix)))
			{
				$this->checkWarn($label, "nothing registered under {$prefix} - has XenForo moved these commands?");
			}
			else
			{
				$this->checkSkip($label, 'not in XenForo ' . \XF::$version);
			}

			return;
		}

		if (CommandHider::modeEnabled($mode))
		{
			$this->checkOk($label, "{$total} shown, {$mode} mode is on");

			return;
		}

		$hidden = count(array_filter($commands, function (Command $command)
		{
			return $command->isHidden();
		}));

		if ($hidden === $total)
		{
			$this->checkOk($label, "{$total} hidden, {$mode} mode is off");

			return;
		}

		$this->checkFail($label, "{$hidden} of {$total} hidden, although {$mode} mode is off");
	}

	/**
	 * Every command hidden because its mode is off also runs the refusal in place of its own
	 * execute(). This is read from the command, not proved by running it: on XenForo 2.2 the only
	 * development command with no guard of its own is xf-dev:metadata-fix, which rewrites a file
	 * if the block has failed.
	 *
	 * @param array<string, array<string, Command>> $families
	 */
	private function blocked(array $families): void
	{
		$total = 0;
		$open = [];

		foreach ($families AS $prefix => $commands)
		{
			if (CommandHider::modeEnabled(CommandHider::FAMILIES[$prefix]['mode']))
			{
				continue;
			}

			foreach ($commands AS $name => $command)
			{
				$total++;
				if (!CommandHider::isBlocked($command))
				{
					$open[] = $name;
				}
			}
		}

		if ($total === 0)
		{
			$this->checkSkip('blocked', 'every mode is on, so nothing is blocked');

			return;
		}

		if (!$open)
		{
			$this->checkOk('blocked', "{$total} refuse in place of their own code");

			return;
		}

		$this->checkFail('blocked', count($open) . " of {$total} would run their own code, such as {$open[0]}");
	}

	/**
	 * xf-dev:info is one of the two development commands with no guard of its own, so it is the one
	 * that shows whether the block works. It only reads and prints, so running it is harmless even
	 * if the block has failed, and its output goes to a buffer that is thrown away.
	 */
	private function refusesByName(Application $console): void
	{
		if (CommandHider::modeEnabled('development'))
		{
			$this->checkSkip('xf-dev:info', 'development mode is on, so nothing is blocked');

			return;
		}

		if (!$console->has('xf-dev:info'))
		{
			$this->checkSkip('xf-dev:info', 'not registered on this XenForo');

			return;
		}

		$buffer = new BufferedOutput();
		$code = $console->get('xf-dev:info')->run(new ArrayInput(['command' => 'xf-dev:info']), $buffer);

		if ($code === 1 && strpos($buffer->fetch(), 'Development mode is not enabled') !== false)
		{
			$this->checkOk('xf-dev:info', 'refuses when named exactly');

			return;
		}

		$this->checkFail('xf-dev:info', "ran when named exactly, exit {$code}");
	}
}
