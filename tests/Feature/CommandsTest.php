<?php namespace Tests\Feature;

use Hampel\HideDevCommands\Cli\Command\Config;
use Hampel\HideDevCommands\Cli\Command\Validate;
use Hampel\HideDevCommands\CommandHider;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\TestCase;

/**
 * hide-dev-commands:config - what is hidden - and hide-dev-commands:validate - whether the hiding
 * still works on this XenForo.
 */
class CommandsTest extends TestCase
{
	/**
	 * XenForo loads every add-on's command classes to list them, so one that cannot load takes down
	 * every command in cmd.php, not only its own.
	 */
	public function test_every_command_class_is_one_xenforo_can_load()
	{
		$commands = $this->assertConsoleCommandsLoad();
		sort($commands);

		$this->assertSame([Config::class, Validate::class], $commands);
	}

	public function test_no_command_extends_a_base_class_missing_from_xenforo_2_2_or_redefines_run()
	{
		$problems = [];
		foreach ([Config::class, Validate::class] AS $class)
		{
			if (in_array('XF\Cli\Command\AbstractCommand', class_parents($class), true))
			{
				$problems[] = "{$class} extends XF\\Cli\\Command\\AbstractCommand";
			}
			if ((new \ReflectionMethod($class, 'run'))->getDeclaringClass()->getName() !== Command::class)
			{
				$problems[] = "{$class} redefines run()";
			}
		}

		$this->assertSame([], $problems);
	}

	public function test_config_reports_each_family()
	{
		$this->setModes(false, true);
		$console = $this->forumConsole();
		CommandHider::apply();

		[$code, $output] = $this->runIn($console, 'hide-dev-commands:config', ['--commands' => true]);

		$this->assertSame(0, $code);
		$this->assertMatchesRegularExpression('/development mode \.+ off \(config\.php\)/', $output);
		$this->assertMatchesRegularExpression('/xf-dev:\* \.+ \d+, hidden and blocked/', $output);
		$this->assertMatchesRegularExpression('/xf-designer:\* \.+ \d+, shown/', $output);
		$this->assertMatchesRegularExpression('/^  xf-dev:info$/m', $output);
	}

	public function test_config_says_so_when_the_mode_is_off_and_nothing_was_hidden()
	{
		$this->setModes(false, true);
		$console = $this->forumConsole();

		[, $output] = $this->runIn($console, 'hide-dev-commands:config');

		$this->assertMatchesRegularExpression('/xf-dev:\* \.+ \d+, shown although development mode is off/', $output);
	}

	public function test_config_changes_nothing()
	{
		$this->setModes(false, false);
		$console = $this->forumConsole();

		$this->runIn($console, 'hide-dev-commands:config');

		$this->assertFalse(CommandHider::applied());
		$this->assertFalse($console->find('xf-dev:info')->isHidden());
	}

	public function test_validate_passes_with_both_modes_on()
	{
		$this->setModes(true, true);
		$console = $this->forumConsole();
		CommandHider::apply();

		[$code, $output] = $this->runIn($console, 'hide-dev-commands:validate', ['--strict' => true]);

		$this->assertSame(0, $code, $output);
		$this->assertStringContainsString('[    ] xf-dev:info', $output);
		$this->assertStringContainsString('[    ] blocked', $output);
	}

	public function test_validate_proves_the_block_with_both_modes_off()
	{
		$this->setModes(false, false);
		$console = $this->forumConsole();
		CommandHider::apply();

		[$code, $output] = $this->runIn($console, 'hide-dev-commands:validate', ['--strict' => true]);

		$this->assertSame(0, $code, $output);
		$this->assertMatchesRegularExpression('/\[ ok \] xf-dev:\*\s+\d+ hidden/', $output);
		$this->assertMatchesRegularExpression('/\[ ok \] xf-dev:info\s+refuses when named exactly/', $output);
		$this->assertMatchesRegularExpression('/\[ ok \] blocked\s+\d+ refuse in place of their own code/', $output);
	}

	public function test_unattended_is_accepted_and_changes_nothing()
	{
		$this->setModes(false, false);
		$console = $this->forumConsole();
		CommandHider::apply();

		[, $attended] = $this->runIn($console, 'hide-dev-commands:validate');
		[$code, $unattended] = $this->runIn($console, 'hide-dev-commands:validate', ['--unattended' => true]);

		$this->assertSame(0, $code);
		$this->assertSame($attended, $unattended);
	}

	public function test_validate_fails_when_the_listener_never_ran()
	{
		$this->setModes(false, true);
		$console = $this->forumConsole();

		[$code, $output] = $this->runIn($console, 'hide-dev-commands:validate');
		[$strictCode] = $this->runIn($console, 'hide-dev-commands:validate', ['--strict' => true]);

		$this->assertSame([1, 1], [$code, $strictCode]);
		$this->assertStringContainsString('[fail] listener ran', $output);
		$this->assertMatchesRegularExpression('/\[fail\] xf-dev:\*\s+0 of \d+ hidden/', $output);
		$this->assertMatchesRegularExpression('/\[fail\] xf-dev:info\s+ran when named exactly/', $output);
		$this->assertMatchesRegularExpression('/\[fail\] blocked\s+\d+ of \d+ would run their own code/', $output);
	}

	/**
	 * A console holding only this add-on's commands is what a XenForo that had moved its own would
	 * look like: nothing broken, and nothing hidden either.
	 */
	public function test_validate_warns_when_no_family_is_found()
	{
		$this->setModes(true, true);
		$console = $this->consoleWith(new Config(), new Validate());
		CommandHider::apply();

		[$code, $output] = $this->runIn($console, 'hide-dev-commands:validate');
		[$strictCode] = $this->runIn($console, 'hide-dev-commands:validate', ['--strict' => true]);

		$this->assertSame([0, 2], [$code, $strictCode]);
		$this->assertMatchesRegularExpression('/\[warn\] xf-dev:\*\s+nothing registered/', $output);
	}

	public function test_a_check_that_throws_does_not_end_the_run()
	{
		$this->fakesErrors();
		$this->setModes(true, true);
		$console = new class ('XenForo', \XF::$version) extends Application
		{
			public function all(?string $namespace = null): array
			{
				throw new \RuntimeException('probe failure');
			}
		};
		$console->add(new Validate());
		CommandHider::apply($this->consoleWith());

		$tester = new CommandTester($console->get('hide-dev-commands:validate'));
		$code = $tester->execute([]);
		$output = $tester->getDisplay();

		$this->assertSame(1, $code);
		$this->assertMatchesRegularExpression('/\[fail\] families\s+RuntimeException: probe failure/', $output);
		$this->assertStringContainsString('xf-dev:info', $output, 'the checks after it still ran');
		$this->assertExceptionLogged(\RuntimeException::class, 'HideDevCommands: ');
	}

	private function consoleWith(Command ...$commands): Application
	{
		$console = new Application('XenForo', \XF::$version);
		$console->setAutoExit(false);
		foreach ($commands AS $command)
		{
			$console->add($command);
		}

		return $console;
	}
}
