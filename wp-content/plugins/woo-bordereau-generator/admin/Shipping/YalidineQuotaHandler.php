<?php

namespace WooBordereauGenerator\Admin\Shipping;

class YalidineQuotaHandler
{
    private $quotaFile;
    private $provider;

    public function __construct($provider) {
        $this->provider = $provider;
        $upload_dir = wp_upload_dir();
        $directory = $upload_dir['basedir'] . '/wc-bordereau-generator';
        if (!is_dir($directory)) {
            wp_mkdir_p($directory);
        }
        $this->quotaFile = $directory . '/' . $provider['slug'] . '_quota.json';
    }

    public function updateQuota($headers) {
        $quotaData = $this->getQuotaData();
        $currentTime = time();

        // Update quota data from headers
        $quotaTypes = [
            'second' => ['header' => 'x-second-quota-left', 'reset' => $currentTime + 1],
            'minute' => ['header' => 'x-minute-quota-left', 'reset' => $currentTime + 60],
            'hour' => ['header' => 'x-hour-quota-left', 'reset' => $currentTime + 3600],
            'day' => ['header' => 'x-day-quota-left', 'reset' => strtotime('tomorrow midnight')],
        ];

        foreach ($quotaTypes as $type => $info) {
            if (isset($headers[strtolower($info['header'])][0])) {
                $quotaData[$type] = [
                    'remaining' => (int)$headers[strtolower($info['header'])][0],
                    'reset_at' => $info['reset']
                ];
            }
        }

        file_put_contents($this->quotaFile, json_encode($quotaData));
    }

    public function canMakeRequest() {
        $quotaData = $this->getQuotaData();
        $currentTime = time();

        // Check all quota types
        foreach ($quotaData as $type => $data) {
            // If quota data exists and hasn't reset yet
            if (isset($data['reset_at']) && $currentTime < $data['reset_at']) {
                // If no remaining quota
                if ($data['remaining'] <= 0) {
                    $waitTime = $data['reset_at'] - $currentTime;
                    return [
                        'can_request' => false,
                        'wait_time' => $waitTime,
                        'quota_type' => $type
                    ];
                }
            }
        }

        return ['can_request' => true];
    }

    private function getQuotaData() {
        if (file_exists($this->quotaFile)) {
            $data = json_decode(file_get_contents($this->quotaFile), true);
            return is_array($data) ? $data : [];
        }
        return [];
    }

    public function waitForQuota() {
        $result = $this->canMakeRequest();
        if (!$result['can_request']) {
            // Add a small buffer to ensure the quota has reset
            sleep($result['wait_time'] + 1);
            return $this->waitForQuota(); // Recursively check again
        }
        return true;
    }
}