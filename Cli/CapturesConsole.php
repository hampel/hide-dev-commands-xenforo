<?php namespace Hampel\HideDevCommands\Cli;

use Hampel\HideDevCommands\CommandHider;
use Symfony\Component\Console\Application;

/**
 * Symfony's Application::add() calls setApplication() on every command it registers, which is the
 * only point at which an add-on can reach XenForo's console application. Each of this add-on's
 * commands carries this, so either one being registered is enough.
 *
 * The parameter is declared the way both XenForo lines accept: 2.3's console types it
 * `?Application`, 2.2's fork leaves it implicitly nullable.
 */
trait CapturesConsole
{
	/**
	 * @return void
	 */
	public function setApplication(?Application $application = null)
	{
		CommandHider::capture($application);

		parent::setApplication($application);
	}
}
