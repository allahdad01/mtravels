<?php
// Redis-based session handling for improved performance and scalability

require_once __DIR__ . '/../vendor/autoload.php';

use Predis\Client;

// Prevent direct access
if (count(get_included_files()) == 1) {
    header("HTTP/1.0 403 Forbidden");
    exit("Direct access to this file is not allowed.");
}

class RedisSessionHandler implements SessionHandlerInterface {
    private $redis;
    private $ttl;

    public function __construct($ttl = 3600) {
        $this->ttl = $ttl;

        try {
            $this->redis = new Client([
                'scheme' => 'tcp',
                'host'   => '127.0.0.1',
                'port'   => 6379,
                'database' => 1, // Use database 1 for sessions
                'read_write_timeout' => 0,
            ]);

            // Test connection
            $this->redis->ping();
        } catch (Exception $e) {
            error_log("Redis session connection failed: " . $e->getMessage());
            throw $e; // Re-throw to let the application handle it
        }
    }

    public function open($savePath, $sessionName) {
        return true;
    }

    public function close() {
        return true;
    }

    public function read($sessionId) {
        try {
            $data = $this->redis->get("session:$sessionId");
            return $data ?: '';
        } catch (Exception $e) {
            error_log("Redis session read error: " . $e->getMessage());
            return '';
        }
    }

    public function write($sessionId, $data) {
        try {
            return $this->redis->setex("session:$sessionId", $this->ttl, $data);
        } catch (Exception $e) {
            error_log("Redis session write error: " . $e->getMessage());
            return false;
        }
    }

    public function destroy($sessionId) {
        try {
            return $this->redis->del(["session:$sessionId"]) > 0;
        } catch (Exception $e) {
            error_log("Redis session destroy error: " . $e->getMessage());
            return false;
        }
    }

    public function gc($maxlifetime) {
        // Redis handles expiration automatically
        return true;
    }
}

// Initialize Redis session handler
try {
    $sessionHandler = new RedisSessionHandler();
    session_set_save_handler($sessionHandler, true);

    // Configure session settings
    ini_set('session.gc_maxlifetime', 3600); // 1 hour
    ini_set('session.cookie_lifetime', 3600);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 0); // Set to 1 for HTTPS
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_only_cookies', 1);

    // Start session
    session_start();

} catch (Exception $e) {
    // Fallback to default PHP session handling
    error_log("Failed to initialize Redis sessions: " . $e->getMessage() . " - Using default PHP sessions");
    session_start();
}
?>