<?php

/**
 * Class that Handles the failures from the different ZFS checks
 *
 * @author  Stefan Konig <github@seosepa.net>
 * @package seoSepa\PHP-ZFSHealthCheck
 */
class FailHandler
{
    protected $hostname = '';

    /**
     * Return pre-set hostname or exec "hostname -f" to get the FQDN from server when none is set
     *
     * @return string
     */
    public function getHostname()
    {
        $hostname = $this->hostname;
        if ($hostname == '') {
            $hostname = exec('hostname -f');
        }
        return $hostname;
    }

    /**
     * echo log for debugging
     *
     * @param string $log
     */
    public function debugLog($log)
    {
        $time = date('d-m-Y H:i:s');
        echo "[{$time}] {$log}" . PHP_EOL;
    }

    /**
     * Send the failNotice to the configured services
     *
     * @param string $message
     * @param string $verboseMessage
     */
    public function sendNotice($message, $verboseMessage)
    {
        $this->debugLog('SENDING FAILURE: ' . $message);
        $this->debugLog('VERBOSE: ' . $verboseMessage);

        $message = '[ZFS-HealthCheck] Failed: ' . $message;
        $verboseMessage = $message . PHP_EOL . $verboseMessage;

        // add your more notification services if needed (e.g. push notification)
        $this->sendMail($message,$verboseMessage);
        $this->addSyslog($message);
    }

    /**
     * Send mail notification
     *
     * @param string $message
     * @param string $verboseMessage
     */
    protected function sendMail($message, $verboseMessage)
    {
        $notificationAddresses = array(
            'example@example.net',
        );

        foreach ($notificationAddresses as $notificationAddress) {
            $this->debugLog('SENDING MAIL TO ' . $notificationAddress);
            mail($notificationAddress, $message, $verboseMessage,
                "From: ZFSHealthCheck {$this->getHostname()} <zfscheck@" . $this->getHostname() . ">\r\n");
        }
    }

    /**
     * Add message to system log
     *
     * @param string $message
     */
    protected function addSyslog($message)
    {
        syslog(LOG_ALERT, $message);
    }
}
