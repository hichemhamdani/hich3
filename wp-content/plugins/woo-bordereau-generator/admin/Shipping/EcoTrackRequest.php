<?php

namespace WooBordereauGenerator\Admin\Shipping;

use WP_Error;

/**
 * EcoTrackRequest Class
 * Handles API requests to EcoTrack with rate limiting
 */
class EcoTrackRequest
{
    /**
     * @var array
     */
    private $provider;

    /**
     * @var array
     */
    private $cache = [];
    
    /**
     * @var int Cache lifetime in seconds (default: 5 minutes)
     */
    private $cacheLifetime = 300;
    
    /**
     * @var string Cache transient prefix
     */
    private $cachePrefix = 'ecotrack_cache_';
    
    /**
     * @var int Maximum wait time in seconds (default: 30 seconds)
     */
    private $maxWaitTime = 30;
    
    /**
     * @var string Option name for storing request queue
     */
    private $queueOptionName = 'ecotrack_request_queue';

    /**
     * Rate limiting properties
     */
    private $minuteLimit = 50;
    private $hourLimit = 1500;
    private $dayLimit = 15000;
    private $minuteUsage = 0;
    private $hourUsage = 0;
    private $dayUsage = 0;
    private $minuteReset = 0;
    private $hourReset = 0;
    private $dayReset = 0;
    private $retryAfter = 0;

    /**
     * @param array $provider
     */
    public function __construct(array $provider)
    {
        $this->provider = $provider;
    }

    /**
     * Make a GET request
     *
     * @param string $url
     * @param bool $useCache
     * @param int $cacheLifetime Override default cache lifetime in seconds
     * @return array|mixed
     */
    public function get($url, $useCache = true, $cacheLifetime = null)
    {
        // Generate a cache key for this URL
        $cacheKey = $this->cachePrefix . md5($url);
        
        // Check if we have a cached response in transients
        if ($useCache) {
            // First check memory cache
            if (isset($this->cache[$url])) {
                return $this->cache[$url];
            }
            
            // Then check WordPress transients
            $cachedResponse = get_transient($cacheKey);
            if ($cachedResponse !== false) {
                // Store in memory cache too for faster subsequent access
                $this->cache[$url] = $cachedResponse;
                return $cachedResponse;
            }
        }
        
        // Check if we're approaching rate limits before making the request
        $waitTime = $this->shouldThrottle();
        if ($waitTime > 0) {
            // Log that we're throttling requests
            $logger = wc_get_logger();
            $logger->info(
                'Proactively throttling EcoTrack API request to avoid rate limit. Waiting ' . $waitTime . ' seconds.', 
                array('source' => 'ecotrack-api')
            );
            
            // Wait before proceeding
            sleep($waitTime);
        }

        $response = $this->fetchRequest($url, [], 'GET');

        // Cache the response if caching is enabled and the response doesn't contain an error
        if ($useCache && (!isset($response['error']) || !$response['error'])) {
            $this->cache[$url] = $response;
            
            // Also cache in WordPress transients for persistence
            $lifetime = $cacheLifetime ?: $this->cacheLifetime;
            set_transient($cacheKey, $response, $lifetime);
        }

        return $response;
    }

    /**
     * Make a POST request
     *
     * @param string $url
     * @param array $data
     * @param bool $formEncoded Set to true to use form-urlencoded instead of JSON
     * @return array|mixed
     */
    public function post($url, $data = [], $formEncoded = false)
    {
        return $this->fetchRequest($url, $data, 'POST', $formEncoded);
    }

    /**
     * Fetch the request with rate limiting
     *
     * @param string $url
     * @param array $data
     * @param string $method
     * @param bool $formEncoded
     * @return array|mixed
     */
    private function fetchRequest($url, $data = [], $method = 'GET', $formEncoded = false)
    {
        // Check if we need to wait due to rate limiting
        $waitTime = $this->getWaitTime();
        if ($waitTime > 0) {
            sleep($waitTime);
        }

        $args = [
            'headers' => [],
            'method' => $method,
            'timeout' => 45,
        ];

        // Set content type based on encoding type
        if ($formEncoded) {
            $args['headers']['Content-Type'] = 'application/x-www-form-urlencoded';
        } else {
            $args['headers']['Content-Type'] = 'application/json';
        }

        // Add authorization header if api_token exists
        if (isset($this->provider['api_token'])) {
            $args['headers']['Authorization'] = "Bearer " . $this->provider['api_token'];
        }

        if ($method === 'POST') {
            if ($formEncoded) {
                $args['body'] = http_build_query($data);
            } else {
                $args['body'] = wp_json_encode($data);
                $args['data_format'] = 'body';
            }
            $request = wp_safe_remote_post($url, $args);
        } else {
            $request = wp_safe_remote_get($url, $args);
        }

        // Handle errors
        if (is_wp_error($request)) {
            return [
                'error' => true,
                'message' => $request->get_error_message()
            ];
        }

        // Get response code
        $response_code = wp_remote_retrieve_response_code($request);

        // Handle rate limiting
        if ($response_code === 429) {
            // Get retry-after header
            $retry_after = wp_remote_retrieve_header($request, 'Retry-After');
            $reset_time = wp_remote_retrieve_header($request, 'X-RateLimit-Reset');
            $limit = wp_remote_retrieve_header($request, 'X-RateLimit-Limit');
            $remaining = wp_remote_retrieve_header($request, 'X-RateLimit-Remaining');

            // Update rate limit information
            if ($retry_after) {
                $this->retryAfter = (int)$retry_after;
            }

            // Create human-readable error message
            $error_message = 'API rate limit exceeded. Rate limits: 50/minute, 1500/hour, 15000/day. ';

            if ($retry_after) {
                $minutes = floor($retry_after / 60);
                $seconds = $retry_after % 60;
                $retry_human = '';

                if ($minutes > 0) {
                    $retry_human .= $minutes . ' minute' . ($minutes > 1 ? 's' : '');
                }

                if ($seconds > 0) {
                    if ($retry_human) {
                        $retry_human .= ' and ';
                    }
                    $retry_human .= $seconds . ' second' . ($seconds > 1 ? 's' : '');
                }

                $error_message .= 'Please try again in ' . $retry_human . '. ';
            }

            if ($reset_time) {
                $reset_date = date('Y-m-d H:i:s', $reset_time);
                $error_message .= 'Rate limit will reset at ' . $reset_date . '. ';
            }

            if ($limit) {
                $error_message .= 'Daily limit: ' . $limit . ' requests. ';
            }

            if ($remaining !== null) {
                $error_message .= 'Remaining: ' . $remaining . ' requests.';
            }

            // Log the rate limit information
            $logger = wc_get_logger();
            $logger->warning($error_message, array('source' => 'ecotrack-api'));

            return [
                'error' => true,
                'message' => $error_message
            ];
        }

        // Update rate limit information
        $this->updateRateLimits($request);

        // Get response body
        $body = wp_remote_retrieve_body($request);
        $response = json_decode($body, true);

        // Check for "Too Many Attempts" message which indicates rate limiting
        if (is_array($response) && isset($response['message']) && $response['message'] === 'Too Many Attempts.') {
            // This is a rate limit error
            $retry_after = wp_remote_retrieve_header($request, 'Retry-After') ?: 60; // Default to 60 seconds if not provided
            $reset_time = wp_remote_retrieve_header($request, 'X-RateLimit-Reset');

            // Create human-readable error message
            $error_message = 'API rate limit exceeded. Rate limits: 50/minute, 1500/hour, 15000/day. ';

            $minutes = floor($retry_after / 60);
            $seconds = $retry_after % 60;
            $retry_human = '';

            if ($minutes > 0) {
                $retry_human .= $minutes . ' minute' . ($minutes > 1 ? 's' : '');
            }

            if ($seconds > 0) {
                if ($retry_human) {
                    $retry_human .= ' and ';
                }
                $retry_human .= $seconds . ' second' . ($seconds > 1 ? 's' : '');
            }

            $error_message .= 'Please try again in ' . ($retry_human ?: '1 minute') . '.';

            // Log the rate limit information
            $logger = wc_get_logger();
            $logger->warning('Rate limit hit: ' . $response['message'] . ' ' . $error_message, array('source' => 'ecotrack-api'));

            // Update rate limit information
            $this->retryAfter = (int)$retry_after;

            return [
                'error' => true,
                'message' => $error_message
            ];
        }

        // If response is not an array, return the original body
        if (!is_array($response)) {
            return [
                'error' => true,
                'message' => 'Invalid response format'
            ];
        }

        return $response;
    }

    /**
     * Update rate limit information from response headers
     *
     * @param array|\WP_Error $request
     */
    private function updateRateLimits($request)
    {
        if (is_wp_error($request)) {
            return;
        }

        // Get rate limit headers - using the new header format with separate day, hour, and minute limits
        
        // Day limits
        $dayLimit = wp_remote_retrieve_header($request, 'X-RateLimit-Limit-Day');
        if ($dayLimit) {
            $this->dayLimit = (int)$dayLimit;
        }
        
        $dayRemaining = wp_remote_retrieve_header($request, 'X-RateLimit-Remaining-Day');
        if ($dayRemaining !== null && $dayRemaining !== '') {
            $this->dayUsage = $this->dayLimit - (int)$dayRemaining;
        } else {
            $this->dayUsage++;
        }
        
        $dayReset = wp_remote_retrieve_header($request, 'X-RateLimit-Reset-Day');
        if ($dayReset) {
            $this->dayReset = (int)$dayReset;
        }
        
        // Hour limits
        $hourLimit = wp_remote_retrieve_header($request, 'X-RateLimit-Limit-Hour');
        if ($hourLimit) {
            $this->hourLimit = (int)$hourLimit;
        }
        
        $hourRemaining = wp_remote_retrieve_header($request, 'X-RateLimit-Remaining-Hour');
        if ($hourRemaining !== null && $hourRemaining !== '') {
            $this->hourUsage = $this->hourLimit - (int)$hourRemaining;
        } else {
            $this->hourUsage++;
        }
        
        $hourReset = wp_remote_retrieve_header($request, 'X-RateLimit-Reset-Hour');
        if ($hourReset) {
            $this->hourReset = (int)$hourReset;
        }
        
        // Minute limits (using the default X-RateLimit headers for minute/default rate limiting)
        $minuteLimit = wp_remote_retrieve_header($request, 'X-RateLimit-Limit');
        if ($minuteLimit) {
            $this->minuteLimit = (int)$minuteLimit;
        }
        
        $minuteRemaining = wp_remote_retrieve_header($request, 'X-RateLimit-Remaining');
        if ($minuteRemaining !== null && $minuteRemaining !== '') {
            $this->minuteUsage = $this->minuteLimit - (int)$minuteRemaining;
        } else {
            $this->minuteUsage++;
        }
        
        // If no specific minute reset provided, set it to current time + 60 seconds
        $current_time = time();
        $this->minuteReset = $current_time + 60;

        // Handle Retry-After header if present
        $retry_after = wp_remote_retrieve_header($request, 'Retry-After');
        if ($retry_after) {
            $this->retryAfter = (int)$retry_after;

            // Log human-readable rate limit information when rate limit is hit
            if ((int)$retry_after > 0) {
                // Use day reset time for logging purposes
                $reset = wp_remote_retrieve_header($request, 'X-RateLimit-Reset-Day') ?: 
                         wp_remote_retrieve_header($request, 'X-RateLimit-Reset');
                $this->logRateLimitInfo($retry_after, $reset);
            }
        }
    }

    /**
     * Log human-readable rate limit information
     *
     * @param int $retryAfter Seconds to wait before next request
     * @param int $resetTimestamp Unix timestamp when rate limit resets
     */
    private function logRateLimitInfo($retryAfter, $resetTimestamp)
    {
        $logger = wc_get_logger();

        // Convert retry after to minutes and seconds
        $minutes = floor($retryAfter / 60);
        $seconds = $retryAfter % 60;
        $retry_human = '';

        if ($minutes > 0) {
            $retry_human .= $minutes . ' minute' . ($minutes > 1 ? 's' : '');
        }

        if ($seconds > 0) {
            if ($retry_human) {
                $retry_human .= ' and ';
            }
            $retry_human .= $seconds . ' second' . ($seconds > 1 ? 's' : '');
        }

        // Convert reset timestamp to human-readable date/time
        $reset_date = '';
        if ($resetTimestamp) {
            $reset_date = date('Y-m-d H:i:s', $resetTimestamp);
        }

        $message = 'EcoTrack API rate limit hit. ';
        $message .= 'Waiting for ' . $retry_human . ' before next request. ';

        if ($reset_date) {
            $message .= 'Rate limit will reset at ' . $reset_date . '.';
        }

        $logger->warning($message, array('source' => 'ecotrack-api'));
    }

    /**
     * Make a DELETE request
     *
     * @param string $url
     * @return array|mixed
     */
    public function delete($url)
    {
        return $this->fetchRequest($url, [], 'DELETE');
    }

    /**
     * Calculate how long to wait before making the next request
     *
     * @return int Seconds to wait
     */
    private function getWaitTime()
    {
        // If retry-after is set, return that
        if ($this->retryAfter > 0) {
            $wait = $this->retryAfter;
            $this->retryAfter = 0;
            return $wait;
        }

        // Check minute limit
        if ($this->minuteUsage >= $this->minuteLimit) {
            return max(1, $this->minuteReset - time());
        }

        // Check hour limit
        if ($this->hourUsage >= $this->hourLimit) {
            return max(1, $this->hourReset - time());
        }

        // Check day limit
        if ($this->dayUsage >= $this->dayLimit) {
            return max(1, $this->dayReset - time());
        }

        return 0;
    }
    
    /**
     * Determine if we should throttle requests to avoid hitting rate limits
     * This is a proactive measure to prevent hitting rate limits
     *
     * @param float $threshold Percentage threshold (0.0 to 1.0) of rate limit at which to start throttling
     * @return int Seconds to wait before making the next request (capped at maxWaitTime)
     */
    private function shouldThrottle($threshold = 0.8)
    {
        $current_time = time();
        
        // Check if we're already at the limit for minute-level (only wait for minute-level limits)
        if ($this->minuteUsage >= $this->minuteLimit) {
            $waitTime = max(1, min($this->maxWaitTime, $this->minuteReset - $current_time));
            return $waitTime;
        }
        
        // Check if we're approaching minute limit (80% by default)
        $minuteThreshold = floor($this->minuteLimit * $threshold);
        if ($this->minuteUsage >= $minuteThreshold) {
            // Calculate a proportional delay based on how close we are to the limit
            $usageRatio = $this->minuteUsage / $this->minuteLimit;
            $timeUntilReset = max(1, $this->minuteReset - $current_time);
            $waitTime = ceil($timeUntilReset * ($usageRatio - $threshold) / (1 - $threshold));
            return min($this->maxWaitTime, $waitTime);
        }
        
        // For hour and day limits, we'll use a queue system instead of making users wait
        // Check if we're approaching hour or day limits
        $hourThreshold = floor($this->hourLimit * $threshold);
        $dayThreshold = floor($this->dayLimit * $threshold);
        
        if ($this->hourUsage >= $hourThreshold || $this->dayUsage >= $dayThreshold) {
            // Add request to queue if we're approaching hour/day limits
            $this->addToRequestQueue();
            
            // Apply a small delay (1-3 seconds) to help distribute requests
            return min(3, rand(1, 3));
        }
        
        // Process queue if we have capacity
        if ($this->processRequestQueue()) {
            // If we processed a queued request, add a small delay
            return 1;
        }
        
        return 0;
    }
    
    /**
     * Add current request to the queue for later processing
     * This is used when approaching hour/day limits
     */
    private function addToRequestQueue()
    {
        // Get current queue
        $queue = get_option($this->queueOptionName, array());
        
        // Add timestamp to queue to track when it was added
        $queue[] = array(
            'timestamp' => time(),
            'provider_id' => isset($this->provider['id']) ? $this->provider['id'] : 'unknown'
        );
        
        // Limit queue size to prevent it from growing too large
        if (count($queue) > 1000) {
            $queue = array_slice($queue, -1000);
        }
        
        // Save queue
        update_option($this->queueOptionName, $queue);
        
        // Log that request was queued
        $logger = wc_get_logger();
        $logger->info(
            'EcoTrack API request queued due to approaching hour/day rate limits. Queue size: ' . count($queue),
            array('source' => 'ecotrack-api')
        );
    }
    
    /**
     * Process a request from the queue if we have capacity
     * 
     * @return bool True if a request was processed
     */
    private function processRequestQueue()
    {
        // Only process queue if we're well below minute threshold
        if ($this->minuteUsage > floor($this->minuteLimit * 0.5)) {
            return false;
        }
        
        // Get current queue
        $queue = get_option($this->queueOptionName, array());
        
        // If queue is empty, nothing to process
        if (empty($queue)) {
            return false;
        }
        
        // Process oldest request in queue
        array_shift($queue);
        
        // Save updated queue
        update_option($this->queueOptionName, $queue);
        
        // Log that a request was processed from queue
        $logger = wc_get_logger();
        $logger->info(
            'Processed EcoTrack API request from queue. Remaining queue size: ' . count($queue),
            array('source' => 'ecotrack-api')
        );
        
        return true;
    }
}
