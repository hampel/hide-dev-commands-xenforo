<?php namespace Tests;

use Hampel\HideDevCommands\CommandHider;
use Hampel\Testing\TestCase as BaseTestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use XF\Cli\Runner;

abstract class TestCase extends BaseTestCase
{
	/**
	 * @var string $rootDir path to the XenForo root directory, relative to the addon path
	 *
	 * No trailing slash!
	 */
	protected $rootDir = '../../../..';

	/**
	 * @var string[] $addonsToLoad load only this addon, so that another addon's vendor tree and
	 * listeners cannot affect the run
	 */
	protected $addonsToLoad = ['Hampel/HideDevCommands'];

	protected function setUp(): void
	{
		parent::setUp();

		CommandHider::reset();
	}

	protected function tearDown(): void
	{
		CommandHider::reset();

		parent::tearDown();
	}

	/**
	 * A console with every command cmd.php would register, registered the way XF\Cli\Runner does
	 * it - this add-on's commands capture it as a side effect, exactly as they do on a forum.
	 */
	protected function forumConsole(): Application
	{
		$console = new Application('XenForo', \XF::$version);
		$console->setAutoExit(false);
		$console->setCatchExceptions(false);

		$runner = new class extends Runner
		{
			public function register(Application $console): void
			{
				$this->registerCommands($console);
			}
		};
		$runner->register($console);

		return $console;
	}

	protected function setModes(bool $development, bool $designer): void
	{
		foreach (['development' => $development, 'designer' => $designer] AS $mode => $enabled)
		{
			$current = $this->app()->config($mode);
			$this->setConfig($mode, array_replace(is_array($current) ? $current : [], ['enabled' => $enabled]));
		}
	}

	/**
	 * Run a command by name inside the given console, so it sees every other command - which
	 * runConsoleCommand() cannot do, as it adds the one command to an application of its own.
	 *
	 * @param array<string, mixed> $input
	 *
	 * @return array{0: int, 1: string} the exit code and the output
	 */
	protected function runIn(Application $console, string $name, array $input = []): array
	{
		$tester = new CommandTester($console->find($name));
		$code = $tester->execute(['command' => $name] + $input);

		return [$code, $tester->getDisplay()];
	}
}
