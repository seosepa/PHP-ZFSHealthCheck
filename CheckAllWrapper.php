<?php
/**
 * script that calls all individual check scripts
 *
 * @author  Stefan Konig <github@seosepa.net>
 * @package seoSepa\PHP-ZFSHealthCheck
 */

require_once('DiskSmartCheck.php');
require_once('ZpoolCapacityCheck.php');
require_once('ZpoolStatusCheck.php');
require_once('FailHandler.php');

$wrapper = new CheckAllWrapper();
$wrapper->run();

class CheckAllWrapper
{
    /**
     * Perform all checks
     */
    public function run()
    {
        $failHandler = new FailHandler();

        $failHandler->debugLog('Starting ZFS Healthcheck Wrapper');
        try{
            $diskSmartCheck = new DiskSmartCheck();
            $diskSmartCheck->run();
        } catch (Exception $e) {
            $failHandler->sendNotice('DiskSmartCheck failed', 'Exception: ' . print_r($e,true));
        }

        try{
            $zpoolStatusCheck = new ZpoolStatusCheck();
            $zpoolStatusCheck->run();
        } catch (Exception $e) {
            $failHandler->sendNotice('Zpool Status check failed', 'Exception: ' . print_r($e,true));
        }

        try{
            $zpoolCapacityCheck = new ZpoolCapacityCheck();
            $zpoolCapacityCheck->run();
        } catch (Exception $e) {
            $failHandler->sendNotice('Zpool Capacity check failed', 'Exception: ' . print_r($e,true));
        }
        $failHandler->debugLog('Finished ZFS Healthcheck Wrapper');
    }
}
