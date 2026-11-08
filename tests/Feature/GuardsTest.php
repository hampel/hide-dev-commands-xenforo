<?php namespace Tests\Feature;

use Hampel\HideDevCommands\Cli\Command\Config;
use Hampel\HideDevCommands\Cli\Command\Validate;
use Hampel\HideDevCommands\CommandHider;
use Symfony\Component\Console\Application;
use Tests\Fixtures\DesignerGuardedCommand;
use Tests\Fixtures\DevGuardedCommand;
use Tests\Fixtures\InheritedGuardCommand;
use Tests\TestCase;

/**
 * Add-on commands outside core's families that guard themselves with RequiresDevModeTrait or
 * RequiresDesignerModeTrait, against fixtures registered beside every real command.
 */
class GuardsTest extends TestCase
{
	private const DEV = 'XF\\Cli\\Command\\Development\\RequiresDevModeTrait';
	private const DESIGNER = 'XF\\Cli\\Command\\Designer\\RequiresDesignerModeTrait';

	public function test_the_guard_is_found_on_the_class_and_through_a_parent(): void
	{
		$found = [
			CommandHider::guardOf(new DevGuardedCommand()),
			CommandHider::guardOf(new InheritedGuardCommand()),
			CommandHider::guardOf(new DesignerGuardedCommand()),
			CommandHider::guardOf(new Config()),
		];

		$this->assertSame([self::DEV, self::DEV, self::DESIGNER, null], $found);
	}

	public function test_core_commands_stay_in_their_families_not_the_guard_groups(): void
	{
		$families = CommandHider::families($this->console());

		$this->assertArrayNotHasKey('xf-dev:import', $families[self::DEV]);
		$this->assertArrayHasKey('xf-dev:import', $families['XF\\Cli\\Command\\Development\\']);
	}

	public function test_development_mode_off_hides_and_blocks_both_dev_guarded_commands(): void
	{
		$this->setModes(false, true);
		$console = $this->console();
		CommandHider::apply();

		$state = [];
		foreach (['fixture:dev-guarded', 'fixture:inherited-guard', 'fixture:designer-guarded'] AS $name)
		{
			$command = $console->find($name);
			$state[$name] = [$command->isHidden(), CommandHider::isBlocked($command)];
		}

		$this->assertSame([
			'fixture:dev-guarded' => [true, true],
			'fixture:inherited-guard' => [true, true],
			'fixture:designer-guarded' => [false, false],
		], $state);
	}

	public function test_designer_mode_off_hides_the_designer_guarded_command(): void
	{
		$this->setModes(true, false);
		$console = $this->console();
		CommandHider::apply();

		$this->assertTrue($console->find('fixture:designer-guarded')->isHidden());
		$this->assertFalse($console->find('fixture:dev-guarded')->isHidden());
	}

	public function test_a_hidden_guarded_command_still_refuses_by_its_exact_name(): void
	{
		$this->setModes(false, true);
		$console = $this->console();
		CommandHider::apply();

		[$code, $output] = $this->runIn($console, 'fixture:dev-guarded');

		$this->assertSame(1, $code);
		$this->assertSame('Development mode is not enabled', trim($output));
	}

	public function test_config_and_validate_report_the_guarded_commands(): void
	{
		$this->setModes(false, false);
		$console = $this->console();
		CommandHider::apply();

		[, $config] = $this->runIn($console, 'hide-dev-commands:config');
		[$code, $validate] = $this->runIn($console, 'hide-dev-commands:validate', ['--strict' => true]);

		$this->assertMatchesRegularExpression('/add-on, development \.+ \d+, hidden and blocked/', $config);
		$this->assertSame(0, $code, $validate);
		$this->assertMatchesRegularExpression('/\[ ok \] add-on, development\s+\d+ hidden/', $validate);
		$this->assertMatchesRegularExpression('/\[ ok \] add-on, designer\s+\d+ hidden/', $validate);
	}

	/**
	 * Most forums have no add-on command that uses either trait, which is nothing to warn about.
	 */
	public function test_no_guarded_command_is_a_skip_not_a_warning(): void
	{
		$this->setModes(true, true);
		$console = new Application('XenForo', CommandHider::xenForoVersion());
		$console->setAutoExit(false);
		$console->add(new Config());
		$console->add(new Validate());
		CommandHider::apply();

		[, $validate] = $this->runIn($console, 'hide-dev-commands:validate');
		[, $config] = $this->runIn($console, 'hide-dev-commands:config');

		$this->assertMatchesRegularExpression('/\[    \] add-on, development\s+no add-on command uses RequiresDevModeTrait/', $validate);
		$this->assertMatchesRegularExpression('/add-on, designer \.+ none installed/', $config);
	}

	private function console(): Application
	{
		$console = $this->forumConsole();
		$console->add(new DevGuardedCommand());
		$console->add(new InheritedGuardCommand());
		$console->add(new DesignerGuardedCommand());

		return $console;
	}
}
