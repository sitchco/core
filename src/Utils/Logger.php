<?php

namespace Sitchco\Utils;

use Kint\Kint;
use Sitchco\Support\DateTime;

/**
 * Usage:
 *   Logger::log('user synced'); // Default LogLevel INFO
 *   Logger::error(['action' => 'cron', 'status' => 'failed']);
 *
 * All calls write to error_log by default. For WP-Cron and CLI commands, error_log output
 * goes to stdout instead of the Apache error log, making it hard to find. Enable
 * SITCHCO_LOG_FILE to write to a persistent log file for these cases.
 *
 * Configuration (local-config.php or wp-config.php):
 *   const SITCHCO_LOG_LEVEL = 'DEBUG';   // DEBUG (default for local), INFO (default), WARNING, ERROR
 *   const SITCHCO_LOG_FILE  = true;      // Also write to wp-content/uploads/logs/{date}.log
 *
 * Cleanup: Log files are not removed automatically. After disabling SITCHCO_LOG_FILE,
 * delete the uploads/logs/ directory from the server.
 */
class Logger
{
    private static ?LogLevel $resolvedLevel = null;

    public static function log(mixed $value, LogLevel $level = LogLevel::INFO): void
    {
        if (!$level->meetsThreshold(self::getMinimumLevel())) {
            return;
        }
        $message = stripcslashes(json_encode($value, JSON_PRETTY_PRINT));
        error_log("[{$level->value}] {$message}");
        if (defined('SITCHCO_LOG_FILE') && SITCHCO_LOG_FILE) {
            self::writeToFile($level, $message);
        }
    }

    public static function debug(mixed $value): void
    {
        self::log($value, LogLevel::DEBUG);
    }

    public static function warning(mixed $value): void
    {
        self::log($value, LogLevel::WARNING);
    }

    public static function error(mixed $value): void
    {
        self::log($value, LogLevel::ERROR);
    }

    public static function dump(mixed $d, bool $return = false): ?string
    {
        if (wp_get_environment_type() !== 'local') {
            return null;
        }

        self::log($d, LogLevel::DEBUG);

        if (!did_action('wp_body_open')) {
            return null;
        }

        if (class_exists(Kint::class)) {
            Kint::$display_called_from = false;
            Kint::$expanded = true;
            Kint::$return = $return;

            return Kint::dump($d);
        }

        $output = '<pre>' . print_r($d, true) . '</pre>';
        return $return ? $output : print $output;
    }

    private static function getMinimumLevel(): LogLevel
    {
        return self::$resolvedLevel ??=
            (defined('SITCHCO_LOG_LEVEL') ? LogLevel::tryFrom(SITCHCO_LOG_LEVEL) : null) ??
            (wp_get_environment_type() === 'local' ? LogLevel::DEBUG : LogLevel::INFO);
    }

    private static function writeToFile(LogLevel $level, string $message): void
    {
        $now = new DateTime();
        $logDir = wp_upload_dir()['basedir'] . '/logs';
        if (!is_dir($logDir)) {
            wp_mkdir_p($logDir);
            file_put_contents($logDir . '/.htaccess', "Options -Indexes\nDeny from all\n");
            file_put_contents($logDir . '/index.php', "<?php\n// Silence is golden.\n");
        }
        $filename = $logDir . '/' . $now->format('Y-m-d') . '.log';
        $entry = "[{$now->format('Y-m-d H:i:s')}] [{$level->value}] {$message}\n";
        file_put_contents($filename, $entry, FILE_APPEND | LOCK_EX);
    }
}
