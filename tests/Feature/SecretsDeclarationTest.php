<?php namespace Tests\Feature;

use Hampel\HideDevCommands\CommandHider;
use Hampel\HideDevCommands\Listener;
use Tests\TestCase;

/**
 * The add-on declares that it has no secrets, and claims its one config.php key so the Admin API
 * shows it. That stays true only while it has no options, so the second test fails the day one is
 * added, which is when the declaration has to be reconsidered.
 * Every test asserts once, outside any loop: an assertion inside a loop over nothing asserts
 * nothing.
 */
class SecretsDeclarationTest extends TestCase
{
	public function test_it_declares_this_add_on_only_and_claims_its_key_with_no_secrets(): void
	{
		$declarations = [];
		Listener::adminApiRedaction($declarations);

		$this->assertSame(['Hampel/HideDevCommands' => ['config' => ['hideDevCommands' => []]]], $declarations);
	}

	public function test_the_claimed_key_is_the_one_the_add_on_reads(): void
	{
		$this->setConfig('hideDevCommands', ['hide' => ['xf:addon-list']]);

		$this->assertSame(['xf:addon-list'], CommandHider::configuredPatterns());
	}

	public function test_the_add_on_still_has_no_options_to_declare(): void
	{
		$options = array_values(array_filter(
			glob(dirname(__DIR__, 2) . '/_output/options/*.json') ?: [],
			function (string $file)
			{
				return basename($file) !== '_metadata.json';
			}
		));

		$this->assertSame([], $options, 'an option was added: decide whether it is a secret, and declare it');
	}

	public function test_the_listener_is_registered_for_the_event(): void
	{
		$registered = [];
		foreach (glob(dirname(__DIR__, 2) . '/_output/code_event_listeners/*.json') ?: [] AS $file)
		{
			$contents = file_get_contents($file);
			$listener = $contents === false ? null : json_decode($contents, true);
			if (is_array($listener) && is_string($listener['event_id'] ?? null) && is_string($listener['callback_method'] ?? null))
			{
				$registered[] = $listener['event_id'] . ' ' . $listener['callback_method'];
			}
		}

		$this->assertContains('hampel_admin_api_redaction adminApiRedaction', $registered);
	}
}
