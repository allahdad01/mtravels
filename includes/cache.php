<?php
// Redis-based caching system for improved performance
require_once __DIR__ . '/../vendor/autoload.php';

use Predis\Client;

class RedisCache {
    private static $instance = null;
    private $redis = null;
    private $fallbackCache = null;

    private function __construct() {
        try {
            // Redis configuration - adjust these settings as needed
            $this->redis = new Client([
                'scheme' => 'tcp',
                'host'   => '127.0.0.1',
                'port'   => 6379,
                'database' => 0,
                'read_write_timeout' => 0,
            ]);

            // Test connection
            $this->redis->ping();
        } catch (Exception $e) {
            // Fallback to file-based caching if Redis is not available
            error_log("Redis connection failed: " . $e->getMessage() . " - Falling back to file cache");
            $this->redis = null;
            $this->initializeFallbackCache();
        }
    }

    private function initializeFallbackCache() {
        $this->fallbackCache = [
            'cache_dir' => __DIR__ . '/../cache/',
            'cache_ttl' => 3600, // 1 hour
        ];

        if (!is_dir($this->fallbackCache['cache_dir'])) {
            mkdir($this->fallbackCache['cache_dir'], 0755, true);
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function get($key) {
        if ($this->redis) {
            try {
                $data = $this->redis->get($key);
                return $data ? unserialize($data) : false;
            } catch (Exception $e) {
                error_log("Redis get error: " . $e->getMessage());
                return $this->fallbackGet($key);
            }
        } else {
            return $this->fallbackGet($key);
        }
    }

    public function set($key, $data, $ttl = 3600) {
        if ($this->redis) {
            try {
                return $this->redis->setex($key, $ttl, serialize($data));
            } catch (Exception $e) {
                error_log("Redis set error: " . $e->getMessage());
                return $this->fallbackSet($key, $data);
            }
        } else {
            return $this->fallbackSet($key, $data);
        }
    }

    public function delete($key) {
        if ($this->redis) {
            try {
                return $this->redis->del([$key]);
            } catch (Exception $e) {
                error_log("Redis delete error: " . $e->getMessage());
                return $this->fallbackDelete($key);
            }
        } else {
            return $this->fallbackDelete($key);
        }
    }

    public function clear() {
        if ($this->redis) {
            try {
                return $this->redis->flushdb();
            } catch (Exception $e) {
                error_log("Redis clear error: " . $e->getMessage());
                return $this->fallbackClear();
            }
        } else {
            return $this->fallbackClear();
        }
    }

    private function fallbackGet($key) {
        if (!$this->fallbackCache) return false;

        $file = $this->fallbackCache['cache_dir'] . md5($key) . '.cache';
        if (file_exists($file) && (time() - filemtime($file)) < $this->fallbackCache['cache_ttl']) {
            return unserialize(file_get_contents($file));
        }
        return false;
    }

    private function fallbackSet($key, $data) {
        if (!$this->fallbackCache) return false;

        $file = $this->fallbackCache['cache_dir'] . md5($key) . '.cache';
        return file_put_contents($file, serialize($data)) !== false;
    }

    private function fallbackDelete($key) {
        if (!$this->fallbackCache) return false;

        $file = $this->fallbackCache['cache_dir'] . md5($key) . '.cache';
        if (file_exists($file)) {
            return unlink($file);
        }
        return true;
    }

    private function fallbackClear() {
        if (!$this->fallbackCache) return false;

        $files = glob($this->fallbackCache['cache_dir'] . '*.cache');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        return true;
    }
}

// Global cache instance
$cache = RedisCache::getInstance();

// Helper functions for backward compatibility
function getCacheKey($prefix, $params = []) {
    return $prefix . '_' . md5(serialize($params));
}

function getCachedData($key) {
    global $cache;
    return $cache->get($key);
}

function setCachedData($key, $data, $ttl = 3600) {
    global $cache;
    return $cache->set($key, $data, $ttl);
}

function clearCache() {
    global $cache;
    return $cache->clear();
}
?>