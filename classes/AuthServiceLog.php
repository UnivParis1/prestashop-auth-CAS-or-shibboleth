<?php

class AuthServiceLog
{
    public static $log_in_database = false;
    
    public static function addLog($message, $severity = 1, $error_code = null, $object_type = null, $object_id = null, $allow_duplicate = false, $id_employee = null)
    {
        $backtrace = debug_backtrace();
        if (isset($backtrace[1])) {
            $backtrace_message = $backtrace[1]['class'] . '::' . $backtrace[1]['function'] . '(line ' . $backtrace[0]['line'].')';
        }

        if (self::$log_in_database) {
            if (is_string($message)) {
                $message .= ' <> ' . $backtrace_message;
            }
            PrestaShopLogger::addLog($message, $severity, $error_code, $object_type, $object_id, $allow_duplicate, $id_employee);
        } else {
            $logger = new FileLogger();
            $logger->setFilename(_PS_ROOT_DIR_ . '/modules/uppsauthservice/logs/' . @date('Ymd-H') . '.log');
            if (isset($backtrace_message)) {
                $logger->log($backtrace_message, $severity);
            }
            $logger->log($message, $severity);
        }
    }
}
