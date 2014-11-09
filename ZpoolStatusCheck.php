<?php

/**
 * Check if zpool is still healthy.
 *
 * @author  Stefan Konig <github@seosepa.net>
 * @package seoSepa\PHP-ZFSHealthCheck
 */
class ZpoolStatusCheck
{
    /**
     * @var $zpoolStatusData array
     */
    protected $zpoolStatusData = array();

    /**
     * Perform zpool status check
     */
    public function run()
    {
        $this->debugLog('>> Starting zpool status check');

        // return on fail
        if (!$this->getZpoolStatus()) {
            return;
        }
        $failArray = $this->parseZpoolStatus();

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
     * Use proc_open in case the command zpool status hangs.
     */
    protected function getZpoolStatus()
    {
        $descriptorspec = array(
            0 => STDIN,
            1 => array("pipe", "w"),
            2 => array("pipe", "w")
        );

        $this->debugLog('proc_open (/sbin/zpool status)');
        $process = proc_open('/sbin/zpool status 2>&1', $descriptorspec, $pipes);

        // poll status of the process 5 times
        for ($i = 0; $i < 5; $i++) {
            sleep(1);
            $this->debugLog('polling process status');
            $status = proc_get_status($process);
            if ($status['running'] == false) {
                break;
            }
        }

        $status = proc_get_status($process);
        $this->debugLog('process still running: ' . var_export($status['running'],true));

        // this indicates a problem with the zpool
        if ($status['running'] == true) {
            $this->sendFail('zpool status command timeout', 'process status: ' . print_r($status, true));
            // force the process to stop
            proc_terminate($process, 9);
            return false;
        }

        $this->debugLog('zpool status output:');
        while (($line = fgets($pipes[1])) !== false) {
            $this->zpoolStatusData[] = $line;
            $this->debugLog('    ' . trim($line));
        }

        proc_close($process);
        $this->debugLog('process closed');
        return true;
    }

    /**
     * parse the zpool status output
     */
    protected function parseZpoolStatus()
    {
        $failArray = array();
        $zpoolName = '';
        foreach ($this->zpoolStatusData as $output) {
            // Check if output is the zpool name, if so, set it.
            if (preg_match('/^\s+pool:\s+(.+)$/', $output, $matches)) {
                $zpoolName = $matches[1];
                continue;
            }

            // Check state
            $zpoolState = '';
            if (preg_match('/^\s+state:\s+(.+)$/', $output, $matches)) {
                $zpoolState = $matches[1];
                $this->debugLog("zpool '{$zpoolName}' is currently {$zpoolState}");
            }
            $zpoolStatus = '';
            if (preg_match('/^\s+status:\s+(.+)$/', $output, $matches)) {
                $zpoolStatus = $matches[1];
            }

            if ($zpoolState != 'ONLINE' && $zpoolState != '') {
                $failArray[] = array(
                    'message' => "zpool {$zpoolName} is currently {$zpoolState}",
                    'verbose' => "Complete zpool data: " . print_r(
                            $this->zpoolStatusData,
                            true
                        ),
                );
            }

            // this usually means there is action required for this zpool
            if ($zpoolStatus != '') {
                $failArray[] = array(
                    'message' => "zpool {$zpoolName} has state: {$zpoolState}",
                    'verbose' => "Complete zpool data: " . print_r(
                            $this->zpoolStatusData,
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
    $zpoolStatusCheck = new ZpoolStatusCheck();
    $zpoolStatusCheck->run();
}
