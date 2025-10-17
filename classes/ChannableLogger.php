<?php
/**
 * 2007-2025 patworx.de
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade AmazonPay to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 *  @author    patworx multimedia GmbH <service@patworx.de>
 *  @copyright 2007-2025 patworx multimedia GmbH
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}
class ChannableLogger
{
    /**
     * @var ChannableLogger
     */
    public static $instance = false;

    /**
     * @var array
     */
    private static $loglevels = [
        1 => 'ERROR',
        2 => 'INFO',
        3 => 'DEBUG',
    ];

    /**
     * loglevel = 1 ... logs only errors
     * loglevel = 2 ... logs more information
     * loglevel = 3 ... debug level, everything logged
     *
     * @var int
     */
    private $loglevel = 1;

    /**
     * AmazonPayLogger constructor.
     */
    public function __construct()
    {
        $this->loglevel = (int) Configuration::get('CHANNABLE_LOGLEVEL');
    }

    /**
     * Add message to Logfile, if level critical also to PS-log table
     *
     * @param $message
     * @param int $loglevel
     * @param Exception $exception|false
     * @param array $dataarray
     */
    public function addLog(
        $message,
        $loglevel = 3,
        $exception = false,
        $dataarray = []
    ) {
        if ($this->loglevel >= $loglevel) {
            $backtrace = debug_backtrace();
            $fileinfo = '';
            $callsinfo = '';
            if (!empty($backtrace[0]) && is_array($backtrace[0])) {
                $fileinfo = $backtrace[0]['file'] . ': ' . $backtrace[0]['line'];
                for ($x = 1; $x < 5; ++$x) {
                    if (!empty($backtrace[$x]) && is_array($backtrace[$x])) {
                        $callsinfo .= "\r\n" . $backtrace[$x]['file'] . ': ' . $backtrace[$x]['line'];
                    }
                }
            }
            $logstr = date('Y-m-d H:i:s');
            $logstr .= ' [' . self::$loglevels[$loglevel] . '] ';
            $logstr .= $message;
            $logstr .= ' - ' . $fileinfo;
            $logstr .= "\r\n";
            if ($callsinfo != '') {
                $logstr .= 'Backtrace :';
                $logstr .= $callsinfo . "\r\n";
            }
            $this->writeToLogfile($logstr);
            if ($loglevel == 1) {
                PrestaShopLogger::addLog($message, 3, null, 'Channable');
            }
            if ($exception) {
                $exceptionlog = 'Exception thrown: ';
                $exceptionlog .= $exception->getCode() . ': ' . $exception->getMessage() . ' - ';
                $exceptionlog .= $exception->getFile() . ': ' . $exception->getLine();
                $exceptionlog .= "\r\n";
                $this->writeToLogfile($exceptionlog);
            }
            if (sizeof($dataarray) > 0) {
                $arraylog = 'Data-Array :';
                $arraylog .= "\r\n";
                $arraylog .= print_r($dataarray, true);
                $arraylog .= "\r\n";
                $this->writeToLogfile($arraylog);
            }
        }
    }

    /**
     * Write Logfile for debugging purposes
     *
     * @param string $string
     */
    protected function writeToLogfile($string)
    {
        file_put_contents($this->getLogFileName(), $string, FILE_APPEND);
    }

    /**
     * @return string
     */
    protected function getLogDir()
    {
        if (is_dir(_PS_ROOT_DIR_ . '/log')) {
            return _PS_ROOT_DIR_ . '/log/';
        } elseif (is_dir(_PS_ROOT_DIR_ . '/app/logs')) {
            return _PS_ROOT_DIR_ . '/app/logs/';
        } else {
            return _PS_ROOT_DIR_ . '/var/logs/';
        }
    }

    /**
     * @return string
     */
    protected function getLogFileName()
    {
        return $this->getLogDir() . 'channable.log';
    }

    /**
     * @return ChannableLogger
     */
    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }
}
