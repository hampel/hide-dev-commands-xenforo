<?php namespace Hampel\HideDevCommands;

use Symfony\Component\Console\Input\ArgvInput;

class Listener
{
	/**
	 * By the time this fires every command is registered and none has run, so it is the one point
	 * at which a listener can change what `list` shows and what a named command does. The console
	 * application is not passed to the event; this add-on's own commands captured it during
	 * registration. The command name is read from argv the way XF\Cli\Runner reads it.
	 */
	public static function appCliStartEnd(\XF\Cli\App $app)
	{
		CommandHider::apply(null, (new ArgvInput())->getFirstArgument());
	}
}
