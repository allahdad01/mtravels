<?php
/**
 * Landing Page Data Fetching
 * Centralized data retrieval for index.php
 */

/**
 * Generic database fetch with caching
 * 
 * @param PDO $pdo Database connection
 * @param string $cacheKey Cache key
 * @param string $sql SQL query
 * @param array $params Query parameters
 * @param bool $fetchOne Fetch single row instead of all
 * @return mixed Cached or fetched data
 */
function fetchWithCache($pdo, $cacheKey, $sql, $params = [], $fetchOne = false) {
    // Try cache first
    if ($cached = getCachedData($cacheKey)) {
        logDebug("Cache HIT: $cacheKey");
        return $cached;
    }
    
    logDebug("Cache MISS: $cacheKey");
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        $result = $fetchOne ? $stmt->fetch(PDO::FETCH_ASSOC) : $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Cache the result
        setCachedData($cacheKey, $result);
        
        return $result;
    } catch (PDOException $e) {
        return $fetchOne ? null : [];
    }
}

/**
 * Fetch platform settings
 */
function getPlatformSettings($pdo) {
    $cacheKey = getCacheKey('platform_settings');
    
    if ($cached = getCachedData($cacheKey)) {
        return $cached;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT `key`, `value` FROM platform_settings ORDER BY id");
        $stmt->execute();
        $settings = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['key']] = $row['value'];
        }
        
        setCachedData($cacheKey, $settings);
        return $settings;
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Fetch all plans and normalize them
 */
function getPlans($pdo) {
    $cacheKey = getCacheKey('plans');
    
    if ($cached = getCachedData($cacheKey)) {
        return $cached;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT id, name, description, features, price, max_users, trial_days FROM plans WHERE status = 'active' ORDER BY price ASC");
        $stmt->execute();
        $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        setCachedData($cacheKey, $plans);
        return $plans;
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Normalize plans: add Umrah if missing, sort by defined order
 */
function normalizePlans($plans) {
    // Check if Umrah plan exists
    $umrahExists = false;
    foreach ($plans as $plan) {
        if (strtolower($plan['name']) === 'umrah') {
            $umrahExists = true;
            break;
        }
    }
    
    // Add Umrah if missing
    if (!$umrahExists) {
        $umrahPlan = [
            'id' => null,
            'name' => 'Umrah',
            'description' => 'Dedicated Umrah management plan with comprehensive family and member management features',
            'features' => json_encode(['family_management', 'member_management', 'id_card_generation', 'agreement_generation', 'cancellation_generation', 'refund_processing', 'payment_processing', 'multi_currency']),
            'price' => 2000,
            'max_users' => 10,
            'trial_days' => 14
        ];
        $plans[] = $umrahPlan;
    }
    
    // Sort by defined plan order
    usort($plans, function($a, $b) {
        $aOrder = PLAN_ORDER[strtolower($a['name'])] ?? 99;
        $bOrder = PLAN_ORDER[strtolower($b['name'])] ?? 99;
        return $aOrder <=> $bOrder;
    });
    
    return $plans;
}

/**
 * Fetch featured destinations
 */
function getDestinations($pdo, $tenant_id, $limit = DEFAULT_DESTINATIONS_LIMIT) {
    $cacheKey = getCacheKey('destinations', [$tenant_id, $limit]);
    
    return fetchWithCache(
        $pdo,
        $cacheKey,
        "SELECT id, name, short_description, image, rating, price 
         FROM destinations 
         WHERE tenant_id = ? AND featured = 1 AND active = 1 
         ORDER BY rating DESC, created_at DESC 
         LIMIT ?",
        [$tenant_id, $limit]
    );
}

/**
 * Fetch testimonials
 */
function getTestimonials($pdo, $tenant_id, $limit = null) {
    $cacheKey = getCacheKey('testimonials', [$tenant_id, $limit]);
    
    if ($cached = getCachedData($cacheKey)) {
        return $cached;
    }
    
    try {
        $sql = "SELECT id, name, photo, testimonial, rating, position 
                FROM testimonials 
                WHERE tenant_id = ? AND active = 1 
                ORDER BY rating DESC, created_at DESC";
        $params = [$tenant_id];
        
        if ($limit !== null) {
            $sql .= " LIMIT ?";
            $params[] = $limit;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $testimonials = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        setCachedData($cacheKey, $testimonials);
        return $testimonials;
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Fetch active deals
 */
function getDeals($pdo, $tenant_id, $limit = DEFAULT_DEALS_LIMIT) {
    $cacheKey = getCacheKey('deals', [$tenant_id, $limit]);
    
    return fetchWithCache(
        $pdo,
        $cacheKey,
        "SELECT id, title, description, image, old_price, new_price, discount, start_date, end_date 
         FROM deals 
         WHERE tenant_id = ? AND active = 1 AND end_date >= CURDATE() 
         ORDER BY discount DESC, created_at DESC 
         LIMIT ?",
        [$tenant_id, $limit]
    );
}

/**
 * Fetch published blog posts
 */
function getBlogPosts($pdo, $tenant_id, $limit = DEFAULT_BLOG_POSTS_LIMIT) {
    $cacheKey = getCacheKey('blog_posts', [$tenant_id, $limit]);
    
    return fetchWithCache(
        $pdo,
        $cacheKey,
        "SELECT id, title, slug, excerpt, featured_image, created_at 
         FROM blog_posts 
         WHERE tenant_id = ? AND status = 'published' 
         ORDER BY created_at DESC 
         LIMIT ?",
        [$tenant_id, $limit]
    );
}

/**
 * Fetch all landing page data
 * Centralizes data fetching for the landing page
 * 
 * @param PDO $pdo Database connection
 * @return array All landing page data
 */
function fetchLandingPageData($pdo) {
    $startTime = microtime(true);
    
    try {
        $data = [
            'settings' => getPlatformSettings($pdo),
            'plans' => normalizePlans(getPlans($pdo)),
            'destinations' => getDestinations($pdo, DEFAULT_TENANT_ID),
            'testimonials' => getTestimonials($pdo, DEFAULT_TENANT_ID, DEFAULT_TESTIMONIALS_LIMIT),
            'deals' => getDeals($pdo, DEFAULT_TENANT_ID),
            'blog_posts' => getBlogPosts($pdo, DEFAULT_TENANT_ID)
        ];
        
        getElapsedTime('Landing page data fetch', $startTime);
        
        return $data;
    } catch (Exception $e) {
        // Return fallback data structure
        return [
            'settings' => [],
            'plans' => [],
            'destinations' => [],
            'testimonials' => [],
            'deals' => [],
            'blog_posts' => []
        ];
    }
}
