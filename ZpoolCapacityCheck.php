<?php

/**
 * Class to check if Zpool capacity is below a certain threshold.
 * ZFS is know to have degraded performance if the disk is over 80% capacity
 *
 * This script can be called standalone. (e.g. via cronjob)
 *
 * @author  Stefan Konig <github@seosepa.net>
 * @package seoSepa\PHP-ZFSHealthCheck
 */
class ZpoolCapacityCheck
{
    /**
     * @var $warningThreshold int
     */
    protected $warningThreshold = 75; // percent

    /**
     * @var $zpoolData array
     */
    protected $zpoolData = array();

    /**
     * Perform Check
     */
    public function run()
    {
        $this->debugLog('>> Starting zpool capacity check');
        $exitCode = $this->getZpoolData();
        if ($exitCode !== 0) {
            $outputData = print_r($this->zpoolData, true);
            $this->sendFail(
                'Error getting zpool list from system for capacity check',
                "exit code was: {$exitCode} with output: {$outputData}"
            );
            return;
        }
        $failArray = $this->parseZpoolData();

        foreach ($failArray as $failureMessage) {
            $this->sendFail($failureMessage['message'], $failureMessage['verbose']);
        }
        $this->debugLog('Finished zpool capacity check, ' . count($failArray) . ' failures send');
    }

    /**
     * send message to failHandler
     *
     * @param string $message
     * @param string $verboseMessage
     */
    protected function sendFail($message, $verboseMessage)
    {
        $failureHandler = new FailHandler();
        $failureHandler->sendNotice($message, $verboseMessage);
    }

    /**
     * send debugLog to central log function in FailHandler
     *
     * @param string $log
     */
    protected function debugLog($log)
    {
        $failureHandler = new FailHandler();
        $failureHandler->debugLog($log);
    }

    /**
     * Get zpool list from system
     *
     * @return int exitcode
     */
    protected function getZpoolData()
    {
        exec('/sbin/zpool list -H -o name,size,alloc,free,capacity', $output, $exitCode);
        $this->zpoolData = $output;
        foreach ($output as $zpool) {
            $this->debugLog('zpool list: ' . $zpool);
        }
        return $exitCode;
    }

    /**
     * Get zpool list from system
     *
     * @return array failures
     */
    protected function parseZpoolData()
    {
        $failArray = array();

        foreach ($this->zpoolData as $zpoolData) {
            $zpoolDataArray = preg_split('/\s+/', $zpoolData);
            $zpoolName      = isset($zpoolDataArray[0]) ? $zpoolDataArray[0] : false;
            $zpoolCapacity  = isset($zpoolDataArray[4]) ? $zpoolDataArray[4] : false;

            $this->debugLog("checking zpool {$zpoolName} with {$zpoolCapacity} capacity");

            if ($zpoolName === false || $zpoolCapacity === false) {
                $failArray[] = array(
                    'message' => 'Failed to parse capacity from zpool List',
                    'verbose' => 'zpool data:' . print_r($zpoolData, true),
                );
                continue;
            }

            $zpoolCapacity = intval(str_replace('%', '', $zpoolCapacity));
            if ($zpoolCapacity >= $this->warningThreshold) {
                $mappingArray = array(
                    'Name'     => $zpoolDataArray[0],
                    'Total'    => $zpoolDataArray[1],
                    'Used'     => $zpoolDataArray[2],
                    'Free'     => $zpoolDataArray[3],
                    'Capacity' => $zpoolDataArray[4],
                );
                $failArray[] = array(
                    'message' => "zpool {$zpoolName} has reached warning capacity of {$this->warningThreshold}%",
                    'verbose' => "Complete zpool data " . print_r(
                            $mappingArray,
                            true
                        ),
                );
            }
        }
        return $failArray;
    }
}

// if wrapperClass does not exist, assume this script is run standalone
if (!class_exists('CheckAllWrapper')) {
    require_once('FailHandler.php');
    $zpoolCapacityCheck = new ZpoolCapacityCheck();
    $zpoolCapacityCheck->run();
}
