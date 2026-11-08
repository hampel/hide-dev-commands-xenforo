<?php namespace Tests\Feature;

use Hampel\HideDevCommands\CommandHider;
use Tests\TestCase;

/**
 * The commands an administrator lists under $config['hideDevCommands']['hide']: hidden from the
 * list whatever the modes, and never blocked.
 */
class ConfiguredHideTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		$this->setModes(true, true);
	}

	public function test_listed_names_and_patterns_are_hidden_and_nothing_else_is(): void
	{
		$this->hide(['xf:addon-list', 'xf-rebuild:*']);
		$console = $this->forumConsole();
		CommandHider::apply();

		$this->assertTrue($console->find('xf:addon-list')->isHidden());
		$this->assertTrue($console->find('xf-rebuild:attachment-thumbnails')->isHidden());
		$this->assertFalse($console->find('xf:addon-install')->isHidden());
		$this->assertFalse($console->find('xf-dev:import')->isHidden());
	}

	public function test_a_listed_command_is_hidden_but_not_blocked(): void
	{
		$this->hide(['hide-dev-commands:config']);
		$console = $this->forumConsole();
		CommandHider::apply();

		[$code, $output] = $this->runIn($console, 'hide-dev-commands:config');

		$this->assertTrue($console->find('hide-dev-commands:config')->isHidden());
		$this->assertFalse(CommandHider::isBlocked($console->find('hide-dev-commands:config')));
		$this->assertSame(0, $code);
		$this->assertStringContainsString('Hidden by config.php', $output);
	}

	public function test_the_command_being_run_is_left_visible(): void
	{
		$this->hide(['xf:addon-*']);
		$console = $this->forumConsole();
		CommandHider::apply(null, 'xf:addon-list');

		$this->assertFalse($console->find('xf:addon-list')->isHidden());
		$this->assertTrue($console->find('xf:addon-install')->isHidden());
	}

	/**
	 * Found in a 2.3.13 sandbox: config listed by name and then run read "1 matched, 0 hidden",
	 * because it is the command running and so is left visible.
	 */
	public function test_config_counts_itself_as_hidden_when_it_is_listed(): void
	{
		$this->hide(['hide-dev-commands:config']);
		$console = $this->forumConsole();
		CommandHider::apply(null, 'hide-dev-commands:config');

		[, $output] = $this->runIn($console, 'hide-dev-commands:config');

		$this->assertMatchesRegularExpression('/hide-dev-commands:config \.+ 1 matched, 1 hidden/', $output);
	}

	public function test_a_single_string_is_taken_as_a_list_of_one(): void
	{
		$this->setConfig(CommandHider::CONFIG_KEY, ['hide' => 'xf:addon-list']);
		$console = $this->forumConsole();
		CommandHider::apply();

		$this->assertTrue($console->find('xf:addon-list')->isHidden());
	}

	public function test_a_command_hidden_by_both_its_mode_and_the_list_is_counted_once(): void
	{
		$this->setModes(false, true);
		$this->hide(['xf-dev:*']);
		$console = $this->forumConsole();
		$before = $this->hiddenIds($console->all());

		$count = CommandHider::apply();

		$this->assertSame(count($this->hiddenIds($console->all())) - count($before), $count);
	}

	public function test_config_reports_each_entry(): void
	{
		$this->hide(['xf-rebuild:*', 'xf:no-such-*']);
		$console = $this->forumConsole();
		CommandHider::apply();

		[, $output] = $this->runIn($console, 'hide-dev-commands:config', ['--commands' => true]);

		$this->assertMatchesRegularExpression('/xf-rebuild:\* \.+ \d+ matched, \d+ hidden/', $output);
		$this->assertMatchesRegularExpression('/xf:no-such-\* \.+ matches no command/', $output);
		$this->assertMatchesRegularExpression('/^  xf-rebuild:attachment-thumbnails$/m', $output);
	}

	public function test_config_says_not_set_without_a_list(): void
	{
		$console = $this->forumConsole();
		CommandHider::apply();

		[, $output] = $this->runIn($console, 'hide-dev-commands:config');

		$this->assertMatchesRegularExpression('/hide list \.+ not set/', $output);
	}

	public function test_validate_passes_when_every_entry_hides_something(): void
	{
		$this->hide(['xf-rebuild:*']);
		$console = $this->forumConsole();
		CommandHider::apply();

		[$code, $output] = $this->runIn($console, 'hide-dev-commands:validate', ['--strict' => true]);

		$this->assertSame(0, $code, $output);
		$this->assertMatchesRegularExpression('/\[ ok \] hide list\s+\d+ hidden by 1 entry in config\.php/', $output);
	}

	public function test_validate_skips_without_a_list(): void
	{
		$console = $this->forumConsole();
		CommandHider::apply();

		[$code, $output] = $this->runIn($console, 'hide-dev-commands:validate', ['--strict' => true]);

		$this->assertSame(0, $code, $output);
		$this->assertStringContainsString('[    ] hide list', $output);
	}

	/**
	 * @return array<string, array{0: mixed, 1: string}>
	 */
	public static function warnings(): array
	{
		return [
			'an entry that matches nothing' => [['xf-rebuild:*', 'xf:no-such'], 'matches no command: xf:no-such'],
			'a value that is not a list of names' => [[42], 'should be a list of command names or patterns'],
			'an empty list' => [[], 'is empty'],
		];
	}

	/**
	 * @param mixed $hide
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('warnings')]
	public function test_validate_warns_about_the_list(mixed $hide, string $expected): void
	{
		$this->setConfig(CommandHider::CONFIG_KEY, ['hide' => $hide]);
		$console = $this->forumConsole();
		CommandHider::apply();

		[$code, $output] = $this->runIn($console, 'hide-dev-commands:validate');
		[$strictCode] = $this->runIn($console, 'hide-dev-commands:validate', ['--strict' => true]);

		$this->assertSame([0, 2], [$code, $strictCode], $output);
		$this->assertStringContainsString('[warn] hide list', $output);
		$this->assertStringContainsString($expected, $output);
	}

	public function test_validate_fails_when_listed_commands_are_shown(): void
	{
		$this->hide(['xf-rebuild:*']);
		$console = $this->forumConsole();

		[$code, $output] = $this->runIn($console, 'hide-dev-commands:validate');

		$this->assertSame(1, $code);
		$this->assertMatchesRegularExpression('/\[fail\] hide list\s+\d+ of \d+ listed commands are shown/', $output);
	}

	/**
	 * @param string[] $patterns
	 */
	private function hide(array $patterns): void
	{
		$this->setConfig(CommandHider::CONFIG_KEY, ['hide' => $patterns]);
	}

	/**
	 * Each hidden command once, by object: Application::all() lists an alias as a second entry.
	 *
	 * @param \Symfony\Component\Console\Command\Command[] $commands
	 *
	 * @return array<int, true>
	 */
	private function hiddenIds(array $commands): array
	{
		$ids = [];
		foreach ($commands AS $command)
		{
			if ($command->isHidden())
			{
				$ids[spl_object_id($command)] = true;
			}
		}

		return $ids;
	}
}
