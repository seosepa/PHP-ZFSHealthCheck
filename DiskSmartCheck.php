<?php

/**
 * parse S.M.A.R.T. info for the disks found by smartctl
 *
 * @author  Stefan Konig <github@seosepa.net>
 * @package seoSepa\PHP-ZFSHealthCheck
 */
class DiskSmartCheck
{
    /**
     * @var $smartCtlPath string
     */
    protected $smartCtlPath = '/usr/local/sbin/smartctl';
    /**
     * @var $reallocatedSectorThreshold int
     */
    protected $reallocatedSectorThreshold = 0; // if defects are greater then this value

    /**
     * @var $disks array
     */
    protected $disks = array();

    /**
     * Perform smartctl check for all disks
     */
    public function run()
    {
        $this->debugLog('>> Starting DiskSmartCheck');
        if (!$this->smartMonToolsInstalled()) {
            $this->sendFail('smartctl is not installed on this server',
                'smartctl is not installed on this server, using path: ' . $this->smartCtlPath);
            return;
        }
        $this->debugLog('smartctl is installed at ' . $this->smartCtlPath);
        if (!$this->getDisks()) {
            $this->sendFail(
                'unable to find disks to smart check',
                'smartctl did find any disks or exited not properly'
            );
            return;
        }

        foreach ($this->disks as $disk) {
            $this->smartCheckDisk($disk);
        }

        $this->debugLog('Finished DiskSmartCheck');
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
     * Make sure SmartMonTools are installed on this server
     *
     * @return boolean
     */
    protected function smartMonToolsInstalled()
    {
        exec($this->smartCtlPath . ' --version', $output, $returnCode);
        if ($returnCode !== 0) {
            $this->debugLog('smartctl output: ' . implode(' ', $output));
            return false;
        }
        return true;
    }

    /**
     * Use proc_open in case the command zpool status hangs.
     *
     * @return boolean
     */
    protected function getDisks()
    {
        $disks = array();
        // get disks in zpools
        $this->debugLog('smartctl scanning for devices');
        exec($this->smartCtlPath . ' --scan', $output, $exitcode);
        if ($exitcode !== 0) {
            $this->debugLog('smartctl died :(');
            return false;
        }

        foreach ($output as $disk) {
            if (preg_match('/\/dev\/\w{4}/', $disk, $matches)) {
                $disks[] = $matches[0];
                $this->debugLog('disk found: ' . $matches[0]);
                continue;
            }
        }

        if (count($disks) == 0) {
            $this->debugLog('no disks found, smartctl output: ' . print_r($output,true));
            return false;
        }

        $this->disks = $disks;

        return true;
    }

    /**
     * parse the zpool status output
     *
     * @param string $disk
     */
    protected function smartCheckDisk($disk)
    {
        $this->debugLog('checking: ' . $disk);
        exec($this->smartCtlPath . ' -H -A ' . $disk, $output, $exitcode);

        if ($exitcode !== 0) {
            $this->sendFail(
                "Disk {$disk} does not support smart",
                'smartctl output:' . print_r($output, true)
            );
            return;
        }

        foreach ($output as $line) {
            if (preg_match('/SMART overall-health self-assessment test result: (\w+)/', $line, $overall)) {
                if ($overall[1] != "PASSED") {
                    $this->sendFail(
                        "Disk {$disk} health status is now: " . $overall[1],
                        'smartctl output:' . print_r($output, true)
                    );
                    break;
                }
            }

            preg_match('/Reallocated_Sector_Ct .+ (\d+)$/', $line, $sectors);
            if (count($sectors) > 0 && intval($sectors[1]) > $this->reallocatedSectorThreshold) {
                $this->sendFail(
                    "Disk {$disk} has a reallocated sector count of: " . $overall[1],
                    'smartctl output:' . print_r($output, true)
                );
                break;
            }
        }
        $this->debugLog($disk . ' PASSED');
    }
}

// if wrapperClass does not exist, assume this script is run standalone
if (!class_exists('CheckAllWrapper')) {
    require_once('FailHandler.php');
    $diskSmartCheck = new DiskSmartCheck();
    $diskSmartCheck->run();
}
