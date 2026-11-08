<?php namespace Hampel\HideDevCommands;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Hides XenForo's development, make and designer commands from cmd.php when the mode each one
 * needs is off, and makes each refuse to run by its exact name - and any add-on's command that
 * guards itself with the same traits.
 *
 * No XenForo hook offers the console application: cmd.php builds the runner with a plain
 * `new Runner()`, commands are registered before the app starts and so before any listener is
 * loaded, and the CLI events are passed only the app. What does reach it is Symfony itself, which
 * calls setApplication() on every command it registers. This add-on's own commands override that
 * method to hand the application to capture(), and the app_cli_start_end listener calls apply(),
 * when every command is registered and none has run.
 *
 * Nothing here is persisted. If any link in that chain breaks, the commands are simply shown again.
 */
class CommandHider
{
	/**
	 * Class prefix => the config.php mode a command under it needs, and the label to show for it.
	 * Matched by class rather than by command name, because a name is free text and the namespace
	 * is where XenForo keeps them. Each family follows the mode its own guard trait checks:
	 * RequiresDevModeTrait for Development and Make, RequiresDesignerModeTrait for Designer.
	 */
	public const FAMILIES = [
		'XF\\Cli\\Command\\Development\\' => ['mode' => 'development', 'label' => 'xf-dev:*'],
		'XF\\Cli\\Command\\Make\\' => ['mode' => 'development', 'label' => 'xf-make:*'],
		'XF\\Cli\\Command\\Designer\\' => ['mode' => 'designer', 'label' => 'xf-designer:*'],
	];

	/**
	 * Guard trait => the mode it checks, and the label to show for the commands that use it. A
	 * command outside the families that uses one of these refuses to run without that mode, so it
	 * is a development command too, whichever add-on it belongs to. Both traits are in every
	 * XenForo from 2.2.0.
	 */
	public const GUARDS = [
		'XF\\Cli\\Command\\Development\\RequiresDevModeTrait' => ['mode' => 'development', 'label' => 'add-on, development'],
		'XF\\Cli\\Command\\Designer\\RequiresDesignerModeTrait' => ['mode' => 'designer', 'label' => 'add-on, designer'],
	];

	/** @var Application|null */
	private static $console = null;

	/** @var bool */
	private static $applied = false;

	public static function capture(?Application $console = null): void
	{
		if ($console !== null)
		{
			self::$console = $console;
		}
	}

	public static function console(): ?Application
	{
		return self::$console;
	}

	/**
	 * Whether apply() has run in this process, whatever it found to hide.
	 */
	public static function applied(): bool
	{
		return self::$applied;
	}

	/**
	 * Forget the captured application, for a test that wants to start from nothing.
	 */
	public static function reset(): void
	{
		self::$console = null;
		self::$applied = false;
	}

	/**
	 * Where core keeps a family's classes. Not every supported XenForo has every family - 2.2 and
	 * 2.3.0 have no Make commands - so an empty family is only suspicious where this exists.
	 */
	public static function familyDirectory(string $prefix): string
	{
		$source = \XF::getSourceDirectory();

		return (is_string($source) ? $source : '') . '/' . str_replace('\\', '/', rtrim($prefix, '\\'));
	}

	/**
	 * XenForo's version string, which \XF declares without a type.
	 */
	public static function xenForoVersion(): string
	{
		return is_string(\XF::$version) ? \XF::$version : '';
	}

	/**
	 * Whether a command will run this add-on's refusal rather than its own execute(). Symfony keeps
	 * what setCode() was given in a private property, in 2.3's console and 2.2's fork alike.
	 */
	public static function isBlocked(Command $command): bool
	{
		$code = new \ReflectionProperty(Command::class, 'code');
		if (PHP_VERSION_ID < 80100)
		{
			// needed below 8.1, a no-op from 8.1, deprecated from 8.5
			$code->setAccessible(true);
		}

		return $code->getValue($command) !== null;
	}

	/**
	 * The mode and label for a key of families(): a class prefix from FAMILIES or a trait from
	 * GUARDS.
	 *
	 * @return array{mode: string, label: string}
	 */
	public static function group(string $key): array
	{
		return self::FAMILIES[$key] ?? self::GUARDS[$key];
	}

	/**
	 * The guard trait a command uses, if any - on its own class, a parent class, or a trait it
	 * uses. class_uses() reports only the traits a class declares itself, so it has to be walked.
	 */
	public static function guardOf(Command $command): ?string
	{
		$classes = array_merge([get_class($command)], array_values(class_parents($command) ?: []));
		$seen = [];

		while ($classes)
		{
			$class = array_shift($classes);
			if (isset($seen[$class]))
			{
				continue;
			}
			$seen[$class] = true;

			foreach (class_uses($class) ?: [] AS $trait)
			{
				if (isset(self::GUARDS[$trait]))
				{
					return $trait;
				}
				$classes[] = $trait;
			}
		}

		return null;
	}

	public static function modeEnabled(string $mode): bool
	{
		$config = \XF::config($mode);

		return is_array($config) && !empty($config['enabled']);
	}

	/**
	 * Every registered command in each family, once each - Application::all() lists an alias as a
	 * second entry for the same object. Core's families come first, by class prefix; then any
	 * other command that uses a guard trait, by the trait.
	 *
	 * @return array<string, array<string, Command>> class prefix or guard trait => command name => command
	 */
	public static function families(Application $console): array
	{
		$families = array_fill_keys(array_merge(array_keys(self::FAMILIES), array_keys(self::GUARDS)), []);
		$seen = [];

		foreach ($console->all() AS $command)
		{
			$id = spl_object_id($command);
			if (isset($seen[$id]))
			{
				continue;
			}
			$seen[$id] = true;

			$class = get_class($command);
			foreach (self::FAMILIES AS $prefix => $family)
			{
				if (strpos($class, $prefix) === 0)
				{
					$families[$prefix][$command->getName()] = $command;
					continue 2;
				}
			}

			$guard = self::guardOf($command);
			if ($guard !== null)
			{
				$families[$guard][$command->getName()] = $command;
			}
		}

		foreach ($families AS &$commands)
		{
			ksort($commands);
		}

		return $families;
	}

	/**
	 * Hide and block every command whose mode is off.
	 *
	 * $requested is what was typed as the command name, which XF\Cli\Runner has already resolved
	 * once, before the app started and so before anything was hidden; Symfony resolves it again
	 * when the command runs. A hidden command reached by an abbreviation would fail that second
	 * lookup with "does not exist", which the runner logs as a server error. So the command it
	 * resolves to is blocked but left visible: it refuses as it would without this add-on, and
	 * `list` is not the command running, so no listing shows it.
	 *
	 * @return int how many commands were hidden
	 */
	public static function apply(?Application $console = null, ?string $requested = null): int
	{
		$console = $console ?? self::$console;
		if ($console === null)
		{
			return 0;
		}

		self::$applied = true;
		$running = self::resolve($console, $requested);
		$hidden = 0;

		foreach (self::families($console) AS $key => $commands)
		{
			$mode = self::group($key)['mode'];
			if (self::modeEnabled($mode))
			{
				continue;
			}

			foreach ($commands AS $command)
			{
				$command->setCode(self::refusal($mode));
				if ($command !== $running)
				{
					$command->setHidden(true);
					$hidden++;
				}
			}
		}

		return $hidden;
	}

	private static function resolve(Application $console, ?string $name): ?Command
	{
		if ($name === null || $name === '')
		{
			return null;
		}

		try
		{
			return $console->find($name);
		}
		catch (\Throwable $e)
		{
			return null;
		}
	}

	/**
	 * What a blocked command runs instead of its execute(): the same words its guard trait uses, so
	 * a guarded command and an unguarded one refuse alike. A guarded command refuses in its own
	 * run() before reaching this, so in practice it answers for xf-dev:info and xf-dev:metadata-fix.
	 */
	private static function refusal(string $mode): \Closure
	{
		$message = ucfirst($mode) . ' mode is not enabled';

		return function (InputInterface $input, OutputInterface $output) use ($message)
		{
			$output->writeln("<error>{$message}</error>");

			return 1;
		};
	}
}
