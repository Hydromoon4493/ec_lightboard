<?php
declare(strict_types=1);

/*
 * Author: Sam Vossen 2026.10.15
 *
 * This file contains the Lightboard database connection and
 * database-related helper functions.
 *
 * Responsibilities:
 * - Load the Lightboard configuration.
 * - Define the available image sources.
 * - Create and reuse the PDO database connection.
 * - Reconnect automatically when MySQL closes an idle connection.
 * - Provide a helper for accessing image-source configuration.
 *
 * This file does NOT handle image processing, ESP32 flashing,
 * HTML rendering, or API requests.
 */

$CFG = require_once __DIR__ . '/../config.php';


/* ============================================================
 * IMAGE SOURCES
 * ============================================================
 *
 * Defines the database tables used by the Lightboard gallery.
 *
 * p = preset displays
 * c = custom user uploads
 */
const SOURCES = [
    'p' => [
        'table' => 'presetDisplay',
        'id' => 'pDisplayID',
        'title' => 'Presets',
        'deletable' => false
    ],

    'c' => [
        'table' => 'customDisplay',
        'id' => 'cDisplayID',
        'title' => 'Custom uploads',
        'deletable' => true
    ],
];


/* ============================================================
 * DATABASE CONNECTION
 * ============================================================
 *
 * Creates a PDO connection using the settings from config.php.
 *
 * The connection is stored in a static variable so multiple
 * calls to db() during the same request reuse the same PDO
 * connection instead of creating a new connection each time.
 *
 * Passing true to db(true) resets the existing connection.
 */
function db(bool $reset = false): PDO
{
    global $CFG;

    static $pdo = null;

    /*
     * Reset the connection when requested.
     *
     * This is useful after MySQL reports that an existing
     * connection has gone away.
     */
    if ($reset) {
        $pdo = null;
    }

    /*
     * Reuse an existing connection.
     */
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $d = $CFG['db'];

    /*
     * Build the MySQL DSN from the configuration.
     */
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $d['host'],
        $d['port'],
        $d['name']
    );

    /*
     * Create the PDO connection.
     */
    $pdo = new PDO(
        $dsn,
        $d['user'],
        $d['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    return $pdo;
}


/* ============================================================
 * DATABASE RETRY
 * ============================================================
 *
 * Executes a database operation and automatically retries it
 * once if MySQL reports that the connection has gone away.
 *
 * MySQL error codes handled:
 *
 * 2006 = MySQL server has gone away
 * 2013 = Lost connection to MySQL server during query
 */
function dbRetry(callable $operation): mixed
{
    try {
        return $operation(db());

    } catch (PDOException $e) {

        $mysqlCode = (int)($e->errorInfo[1] ?? 0);

        /*
         * Only reconnect for the specific MySQL connection
         * errors that this application is designed to recover
         * from.
         */
        if ($mysqlCode !== 2006 && $mysqlCode !== 2013) {
            throw $e;
        }

        /*
         * Reset the old PDO connection.
         */
        db(true);

        /*
         * Create a new connection and retry the operation once.
         */
        return $operation(db());
    }
}


/* ============================================================
 * IMAGE SOURCE HELPER
 * ============================================================
 *
 * Returns the configuration for a Lightboard image source.
 *
 * Example:
 *
 *     $source = src('p');
 *
 * returns the configuration for preset displays.
 */
function src(string $key): array
{
    if (!isset(SOURCES[$key])) {
        throw new RuntimeException('Unknown image source.');
    }

    return SOURCES[$key];
}
