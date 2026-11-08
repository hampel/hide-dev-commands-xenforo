<?php namespace Tests\Fixtures;

/**
 * The guard arrives through the parent class, where class_uses() on this class cannot see it.
 */
class InheritedGuardCommand extends DevGuardedCommand
{
	/**
	 * @return void
	 */
	protected function configure()
	{
		$this->setName('fixture:inherited-guard');
	}
}
