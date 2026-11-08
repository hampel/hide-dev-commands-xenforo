<?php namespace Tests\Feature;

use Hampel\HideDevCommands\CommandHider;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Exception\NamespaceNotFoundException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

/**
 * The hiding and blocking, against every command this XenForo really registers.
 */
class HidingTest extends TestCase
{
	public function test_registering_the_commands_captures_the_console(): void
	{
		$console = $this->forumConsole();

		$this->assertSame($console, CommandHider::console());
	}

	public function test_each_family_is_found_under_its_namespace(): void
	{
		$families = CommandHider::families($this->forumConsole());

		$problems = [];
		foreach ($families AS $prefix => $commands)
		{
			if (!$commands)
			{
				$problems[] = "nothing under {$prefix}";
			}
		}

		$this->assertSame([], $problems);
		$this->assertArrayHasKey('xf-dev:info', $families['XF\\Cli\\Command\\Development\\']);
		$this->assertArrayHasKey('xf-dev:metadata-fix', $families['XF\\Cli\\Command\\Development\\']);
	}

	public function test_an_alias_is_counted_once(): void
	{
		$families = CommandHider::families($this->forumConsole());

		$this->assertArrayNotHasKey('xf-make:command', $families['XF\\Cli\\Command\\Make\\']);
		$this->assertArrayHasKey('xf-make:cli-command', $families['XF\\Cli\\Command\\Make\\']);
	}

	public function test_both_modes_on_hides_nothing(): void
	{
		$this->setModes(true, true);
		$console = $this->forumConsole();

		$this->assertSame([], $this->hiddenByApply($console));
	}

	public function test_development_mode_off_hides_dev_and_make_and_nothing_else(): void
	{
		$this->setModes(false, true);
		$console = $this->forumConsole();
		$hidden = $this->hiddenByApply($console);

		$expected = $this->familyNames($console, ['XF\\Cli\\Command\\Development\\', 'XF\\Cli\\Command\\Make\\']);

		$this->assertGreaterThan(100, count($expected), 'the families should be found before this means anything');
		$this->assertSame($expected, $hidden);
	}

	public function test_designer_commands_follow_designer_mode_not_development_mode(): void
	{
		$this->setModes(true, false);
		$console = $this->forumConsole();
		$hidden = $this->hiddenByApply($console);

		$this->assertSame($this->familyNames($console, ['XF\\Cli\\Command\\Designer\\']), $hidden);
	}

	public function test_no_other_command_is_hidden_with_both_modes_off(): void
	{
		$this->setModes(false, false);
		$console = $this->forumConsole();
		$hidden = $this->hiddenByApply($console);

		$this->assertSame($this->familyNames($console, array_keys(CommandHider::FAMILIES)), $hidden);
		$this->assertFalse($console->find('xf:addon-list')->isHidden());
		$this->assertFalse($console->find('hide-dev-commands:validate')->isHidden());
	}

	public function test_list_shows_none_of_them(): void
	{
		$this->setModes(false, false);
		$console = $this->forumConsole();
		CommandHider::apply();

		$output = new BufferedOutput();
		$console->run(new ArrayInput(['command' => 'list', '--raw' => true]), $output);
		$listed = $output->fetch();

		$this->assertDoesNotMatchRegularExpression('/^xf-(dev|make|designer):/m', $listed);
		$this->assertMatchesRegularExpression('/^xf:addon-list /m', $listed);
	}

	public function test_a_namespace_listing_finds_nothing(): void
	{
		$this->setModes(false, false);
		$console = $this->forumConsole();
		CommandHider::apply();

		$this->expectException(NamespaceNotFoundException::class);

		$console->run(new ArrayInput(['command' => 'list', 'namespace' => 'xf-dev']), new BufferedOutput());
	}

	public function test_an_abbreviation_does_not_reach_a_hidden_command(): void
	{
		$this->setModes(false, false);
		$console = $this->forumConsole();
		CommandHider::apply();

		$this->expectException(CommandNotFoundException::class);

		$console->find('xf-dev:inf');
	}

	/**
	 * XF\Cli\Runner resolves the typed name before anything is hidden, and Symfony resolves it
	 * again when the command runs. Hidden by then, the abbreviation fails the second lookup, and
	 * the runner logs that as a server error.
	 */
	public function test_the_command_an_abbreviation_is_running_refuses_rather_than_vanishing(): void
	{
		$this->setModes(false, true);
		$console = $this->forumConsole();
		CommandHider::apply(null, 'xf-dev:inf');

		[$code, $output] = $this->runIn($console, 'xf-dev:inf');

		$this->assertSame(1, $code);
		$this->assertSame('Development mode is not enabled', trim($output));
		$this->assertTrue($console->find('xf-dev:import')->isHidden(), 'only the command being run is left visible');
	}

	public function test_an_unguarded_command_refuses_by_its_exact_name(): void
	{
		$this->setModes(false, true);
		$console = $this->forumConsole();
		CommandHider::apply();

		[$code, $output] = $this->runIn($console, 'xf-dev:info');

		$this->assertSame(1, $code);
		$this->assertSame('Development mode is not enabled', trim($output));
	}

	public function test_a_guarded_command_still_refuses_with_its_own_message(): void
	{
		$this->setModes(true, false);
		$console = $this->forumConsole();
		CommandHider::apply();

		[$code, $output] = $this->runIn($console, 'xf-designer:export', ['designer-mode' => 'none']);

		$this->assertSame(1, $code);
		$this->assertSame('Designer mode is not enabled', trim($output));
	}

	public function test_without_a_captured_console_nothing_happens(): void
	{
		$this->setModes(false, false);

		$this->assertSame(0, CommandHider::apply());
		$this->assertFalse(CommandHider::applied());
	}

	/**
	 * The commands apply() hid - core already hides a few of its own, such as
	 * xf:import-child-process, and those are not this add-on's doing.
	 *
	 * @return string[]
	 */
	private function hiddenByApply(Application $console): array
	{
		$before = $this->hiddenNames($console);
		$count = CommandHider::apply();
		$hidden = array_values(array_diff($this->hiddenNames($console), $before));

		$this->assertSame(count($hidden), $count, 'apply() should report what it hid');

		return $hidden;
	}

	/**
	 * @return string[]
	 */
	private function hiddenNames(Application $console): array
	{
		$names = [];
		foreach ($console->all() AS $name => $command)
		{
			if ($command->isHidden() && $name === $command->getName())
			{
				$names[] = $name;
			}
		}
		sort($names);

		return $names;
	}

	/**
	 * @param string[] $prefixes
	 *
	 * @return string[]
	 */
	private function familyNames(Application $console, array $prefixes): array
	{
		$names = [];
		foreach (CommandHider::families($console) AS $prefix => $commands)
		{
			if (in_array($prefix, $prefixes, true))
			{
				$names = array_merge($names, array_keys($commands));
			}
		}
		sort($names);

		return $names;
	}
}
