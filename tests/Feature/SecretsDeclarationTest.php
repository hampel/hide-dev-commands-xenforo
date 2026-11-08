<?php namespace Tests\Feature;

use Hampel\HideDevCommands\Listener;
use Tests\TestCase;

/**
 * The add-on declares that it has no secrets. That stays true only while it has no options, so the
 * second test fails the day one is added, which is when the declaration has to be reconsidered.
 * Every test asserts once, outside any loop: an assertion inside a loop over nothing asserts
 * nothing.
 */
class SecretsDeclarationTest extends TestCase
{
	public function test_it_declares_this_add_on_only_and_declares_nothing(): void
	{
		$declarations = [];
		Listener::adminApiRedaction($declarations);

		$this->assertSame(['Hampel/HideDevCommands' => []], $declarations);
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
			$listener = json_decode(file_get_contents($file), true);
			if (isset($listener['event_id']))
			{
				$registered[] = $listener['event_id'] . ' ' . $listener['callback_method'];
			}
		}

		$this->assertContains('hampel_admin_api_redaction adminApiRedaction', $registered);
	}
}
