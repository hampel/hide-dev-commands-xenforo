<?php namespace Hampel\HideDevCommands;

use XF\AddOn\AbstractSetup;

class Setup extends AbstractSetup
{
    public function install(array $stepParams = [])
    {
        // nothing to do
    }

    public function upgrade(array $stepParams = [])
    {
        // nothing to do
    }

    /**
     * @param int $previousVersion
     * @param array<string, mixed> $stateChanges
     *
     * @return void
     */
    public function postUpgrade($previousVersion, array &$stateChanges)
    {
        if (\XF::$versionId >= 2030000) { // XF 2.3+
            $this->enqueuePostUpgradeCleanUp();
        }
    }

    public function uninstall(array $stepParams = [])
    {
        // nothing to do
    }
}