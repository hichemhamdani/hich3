<?php

namespace WooBordereauGenerator\Admin\Shipping;

use WooBordereauGenerator\Functions;
use WP_Error;

class YalidineRequest {

	private $provider;

	public function __construct( $provider ) {
		$this->provider = $provider;
	}

	const GET = 'GET';
	const POST = 'POST';
	const PATCH = 'PATCH';
	const DELETE = 'DELETE';

	/**
	 * @param $url
	 * @param bool $cache
	 *
	 * @return mixed
	 * @throws \ErrorException
	 */
	public function get( $url, bool $cache = true )
	{
		if ( $cache ) {

			$cache_key = md5( $url . date( 'W' ) );
			$path      = Functions::get_path( $cache_key . ".json" );

			if ( file_exists( $path ) ) {

				$respose = json_decode( file_get_contents( $path ), true );

				if ( $respose == null || isset( $respose['error'] ) && is_array( $respose['error'] ) && count( $respose['error'] ) ) {
					$response = $this->fetchRequest( $url );
					// Only cache if response is valid and not null/empty
					if ( $response !== null && !empty( $response ) && !isset( $response['error'] ) ) {
						file_put_contents( $path, json_encode( $response ) );
					}
					return $response;
				}

				return $respose;
			} else {
				$response = $this->fetchRequest( $url );
				// Only cache if response is valid and not null/empty
				if ( $response !== null && !empty( $response ) && !isset( $response['error'] ) ) {
					file_put_contents( $path, json_encode( $response ) );
				}

				return $response;
			}
		} else {
			return $this->fetchRequest( $url );
		}
	}

	/**
	 * @param $url
	 * @param $data
	 * @param string $method
	 * @param bool $silence
	 *
	 * @return mixed
	 */
	public function post( $url, $data, string $method = YalidineRequest::POST, $silence = false ) {

		// Check quota before making request
		$quotaCheck = $this->checkQuota();
		if ( $quotaCheck['available'] === false ) {
			if ( ! $silence ) {
				$quotaType = str_replace( '-quota-left', '', $quotaCheck['quota_type'] );
				$message   = sprintf(
					'Rate limit exceeded for %s quota. Please try again in %s.',
					$quotaType,
					$this->formatWaitTime( $quotaCheck['wait_time'] )
				);

				wp_send_json( [
					'error'       => true,
					'message'     => $message,
					'quota_type'  => $quotaType,
					'retry_after' => $quotaCheck['wait_time']
				], 429 );
			}

			return null;
		}

		$ch      = curl_init();
		$headers = []; // Initialize headers array

		// Build base cURL options array
		$curlOptions = [
			CURLOPT_URL            => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTPHEADER     => [
				'X-API-ID: ' . get_option( $this->provider['slug'] . '_api_key' ),
				'X-API-TOKEN: ' . get_option( $this->provider['slug'] . '_api_token' ),
				'Content-Type: application/json'
			],
			CURLOPT_HEADERFUNCTION => function ( $ch, $header ) use ( &$headers ) {
				$len    = strlen( $header );
				$header = explode( ':', $header, 2 );
				if ( count( $header ) < 2 ) { // ignore invalid headers
					return $len;
				}
				$headers[ strtolower( trim( $header[0] ) ) ][] = trim( $header[1] );

				return $len;
			}
		];

		// Handle different HTTP methods
		if ( $method === YalidineRequest::POST ) {
			$curlOptions[ CURLOPT_POST ] = true;
		}

		if ( $method !== YalidineRequest::DELETE && ! empty( $data ) ) {
			$curlOptions[ CURLOPT_POSTFIELDS ] = json_encode( $data );
		}

		$curlOptions[ CURLOPT_CUSTOMREQUEST ] = $method;

		// Set all options at once
		curl_setopt_array( $ch, $curlOptions );

		// Execute request
		$result = curl_exec( $ch );

		// Check for cURL errors
		if ( ! $silence && curl_errno( $ch ) ) {
			$error_msg = curl_error( $ch );
			curl_close( $ch );
			wp_send_json( [
				'error'   => true,
				'message' => $error_msg
			], 401 );
		}

		// Set quota from headers
		$this->setQuota( $headers );

		// Close cURL resource
		curl_close( $ch );

		// Decode response
		$response = json_decode( $result, true );

		// Handle API errors
		if ( ! $silence && isset( $response['error'] ) ) {
			wp_send_json( [
				'error'   => true,
				'message' => $response['error']['message']
			], $response['error']['code'] );
		}

		return $response;
	}

	/**
	 * Check the Quota for each request
	 * @return array ['available' => bool, 'wait_time' => int, 'quota_type' => string]
	 */
	private function checkQuota() {
		$quotaData = $this->loadQuotaData();

		if ( empty( $quotaData ) ) {
			// No quota data, assume quota is available
			return [ 'available' => true, 'wait_time' => 0, 'quota_type' => null ];
		}

		$currentTime = time();
		$waitTimes   = [];

		foreach ( $quotaData as $header => $data ) {
			// Check if we're in a new period
			$periodLength       = $this->getPeriodLength( $header );
			$currentPeriodStart = $this->getPeriodStart( $currentTime, $periodLength );

			// If we're in a new period, the quota should have reset
			if ( isset( $data['period_start'] ) && $currentPeriodStart > $data['period_start'] ) {
				continue; // Quota has reset for this period
			}

			// Check if quota is depleted
			if ( $data['remaining'] <= 0 ) {
				if ( $currentTime < $data['reset_time'] ) {
					// Calculate wait time
					$waitTime             = $data['reset_time'] - $currentTime;
					$waitTimes[ $header ] = $waitTime;
				}
				// If current time >= reset time, the quota should be reset
			}
		}

		// If no wait times, quota is available
		if ( empty( $waitTimes ) ) {
			return [ 'available' => true, 'wait_time' => 0, 'quota_type' => null ];
		}

		// Find the limiting quota (the one with the longest wait time)
		$limitingQuota = '';
		$maxWaitTime   = 0;
		foreach ( $waitTimes as $quota => $waitTime ) {
			if ( $waitTime > $maxWaitTime ) {
				$maxWaitTime   = $waitTime;
				$limitingQuota = $quota;
			}
		}

		// Auto-wait for second and minute quotas
		if ( in_array( $limitingQuota, [ 'second-quota-left', 'minute-quota-left' ] ) ) {
			// Sleep for the required time
			sleep( $maxWaitTime );

			// After sleeping, quota should be available
			return [ 'available' => true, 'wait_time' => 0, 'quota_type' => null ];
		}

		// For hour and day quotas, return error information
		return [
			'available'  => false,
			'wait_time'  => $maxWaitTime,
			'quota_type' => $limitingQuota
		];
	}

	/**
	 * Set quota information from response headers
	 *
	 * @param array $headers
	 */
	private function setQuota( $headers ) {
		$quotaMapping = [
			'second-quota-left' => 1,
			'minute-quota-left' => 60,
			'hour-quota-left'   => 3600,
			'day-quota-left'    => 86400
		];

		// Load existing quota data
		$existingQuota = $this->loadQuotaData();
		$currentTime   = time();
		$quota         = [];

		foreach ( $quotaMapping as $header => $period ) {
			if ( isset( $headers[ $header ] ) ) {
				$remaining = (int) $headers[ $header ][0];

				// Check if we have existing data for this quota type
				if ( isset( $existingQuota[ $header ] ) ) {
					$existingResetTime   = $existingQuota[ $header ]['reset_time'];
					$existingPeriodStart = $existingQuota[ $header ]['period_start'] ?? null;

					// Calculate the current period start
					$currentPeriodStart = $this->getPeriodStart( $currentTime, $period );

					// If we're in a new period, update the reset time
					if ( $existingPeriodStart !== $currentPeriodStart || $currentTime >= $existingResetTime ) {
						$quota[ $header ] = [
							'remaining'    => $remaining,
							'reset_time'   => $currentPeriodStart + $period,
							'period_start' => $currentPeriodStart,
							'last_update'  => $currentTime
						];
					} else {
						// We're still in the same period, keep the existing reset time
						$quota[ $header ] = [
							'remaining'    => $remaining,
							'reset_time'   => $existingResetTime,
							'period_start' => $existingPeriodStart,
							'last_update'  => $currentTime
						];
					}
				} else {
					// First time setting this quota
					$periodStart      = $this->getPeriodStart( $currentTime, $period );
					$quota[ $header ] = [
						'remaining'    => $remaining,
						'reset_time'   => $periodStart + $period,
						'period_start' => $periodStart,
						'last_update'  => $currentTime
					];
				}
			}
		}

		// Save the quota data
		$this->saveQuotaData( $quota );
	}

	/**
	 * Get the start of the current period
	 *
	 * @param int $timestamp
	 * @param int $period
	 *
	 * @return int
	 */
	private function getPeriodStart( $timestamp, $period ) {
		switch ( $period ) {
			case 1: // Second
				return $timestamp;
			case 60: // Minute
				return $timestamp - ( $timestamp % 60 );
			case 3600: // Hour
				return $timestamp - ( $timestamp % 3600 );
			case 86400: // Day
				// Get start of day in server timezone
				return strtotime( 'today', $timestamp );
			default:
				return $timestamp;
		}
	}

	/**
	 * Get period length from header name
	 *
	 * @param string $header
	 *
	 * @return int
	 */
	private function getPeriodLength( $header ) {
		$mapping = [
			'second-quota-left' => 1,
			'minute-quota-left' => 60,
			'hour-quota-left'   => 3600,
			'day-quota-left'    => 86400
		];

		return $mapping[ $header ] ?? 1;
	}

	/**
	 * Save quota data to file
	 *
	 * @param array $quota
	 */
	private function saveQuotaData( $quota ) {
		$upload_dir = wp_upload_dir();
		$directory  = $upload_dir['basedir'] . '/wc-bordereau-generator';

		if ( ! is_dir( $directory ) ) {
			wp_mkdir_p( $directory );
		}

		$filename = $directory . '/' . $this->provider['slug'] . '_rate_quota.json';
		file_put_contents( $filename, json_encode( $quota, JSON_PRETTY_PRINT ) );
	}

	/**
	 * Load quota data from file
	 * @return array
	 */
	private function loadQuotaData() {
		$upload_dir = wp_upload_dir();
		$directory  = $upload_dir['basedir'] . '/wc-bordereau-generator';
		$filename   = $directory . '/' . $this->provider['slug'] . '_rate_quota.json';

		if ( ! file_exists( $filename ) ) {
			return [];
		}

		$data = json_decode( file_get_contents( $filename ), true );

		return is_array( $data ) ? $data : [];
	}

	/**
	 * Get detailed quota status (for debugging)
	 * @return array
	 */
	public function getQuotaStatus() {
		$quotaData   = $this->loadQuotaData();
		$currentTime = time();
		$status      = [];

		foreach ( $quotaData as $header => $data ) {
			$periodLength       = $this->getPeriodLength( $header );
			$currentPeriodStart = $this->getPeriodStart( $currentTime, $periodLength );
			$inNewPeriod        = isset( $data['period_start'] ) && $currentPeriodStart > $data['period_start'];

			$isAvailable    = $data['remaining'] > 0 || $currentTime >= $data['reset_time'] || $inNewPeriod;
			$timeUntilReset = max( 0, $data['reset_time'] - $currentTime );

			$status[ $header ] = [
				'remaining'        => $data['remaining'],
				'available'        => $isAvailable,
				'in_new_period'    => $inNewPeriod,
				'reset_in_seconds' => $timeUntilReset,
				'reset_at'         => date( 'Y-m-d H:i:s', $data['reset_time'] ),
				'period_start'     => date( 'Y-m-d H:i:s', $data['period_start'] ?? 0 ),
				'last_update'      => date( 'Y-m-d H:i:s', $data['last_update'] ?? 0 )
			];
		}

		return $status;
	}

	/**
	 * Format wait time into human-readable format
	 *
	 * @param int $seconds
	 *
	 * @return string
	 */
	private function formatWaitTime( $seconds ) {
		if ( $seconds < 60 ) {
			return $seconds . ' second' . ( $seconds !== 1 ? 's' : '' );
		} elseif ( $seconds < 3600 ) {
			$minutes = ceil( $seconds / 60 );

			return $minutes . ' minute' . ( $minutes !== 1 ? 's' : '' );
		} elseif ( $seconds < 86400 ) {
			$hours = ceil( $seconds / 3600 );

			return $hours . ' hour' . ( $hours !== 1 ? 's' : '' );
		} else {
			$days = ceil( $seconds / 86400 );

			return $days . ' day' . ( $days !== 1 ? 's' : '' );
		}
	}

	/**
	 * @param $url
	 *
	 * @return array
	 */
	public function fetchRequest( $url ) {

		// Check quota before making request
		$quotaCheck = $this->checkQuota();

		if ( $quotaCheck['available'] === false ) {
			$quotaType = str_replace( '-quota-left', '', $quotaCheck['quota_type'] );
			$message   = sprintf(
				'Rate limit exceeded for %s quota. Please try again in %s.',
				$quotaType,
				$this->formatWaitTime( $quotaCheck['wait_time'] )
			);
			error_log( $message );
		}

		try {
			$curl    = curl_init();
			$headers = []; // Initialize headers array

			// Set the header function BEFORE executing the request
			curl_setopt_array( $curl, array(
				CURLOPT_URL            => $url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING       => '',
				CURLOPT_MAXREDIRS      => 10,
				CURLOPT_TIMEOUT        => 50,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_HEADER         => true, // Keep this true to get headers
				CURLOPT_SSL_VERIFYHOST => false,
				CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST  => 'GET',
				CURLOPT_HTTPHEADER     => array(
					'X-API-ID: ' . get_option( $this->provider['slug'] . '_api_key' ),
					'X-API-TOKEN: ' . get_option( $this->provider['slug'] . '_api_token' ),
					"Content-Type: application/json"
				),
				CURLOPT_HEADERFUNCTION => function ( $curl, $header ) use ( &$headers ) {
					$len    = strlen( $header );
					$header = explode( ':', $header, 2 );
					if ( count( $header ) < 2 ) // ignore invalid headers
					{
						return $len;
					}

					$headers[ strtolower( trim( $header[0] ) ) ][] = trim( $header[1] );

					return $len;
				}
			) );

			// Execute the request
			$result = curl_exec( $curl );

			if ( $result === false ) {
				$error_msg = curl_error( $curl );
				$error_no  = curl_errno( $curl );
				curl_close( $curl );
				error_log( "cURL Error #{$error_no}: {$error_msg}" );
			}

			// Get header size
			$header_size = curl_getinfo( $curl, CURLINFO_HEADER_SIZE );
			// Separate the headers and body
			$header = substr( $result, 0, $header_size );
			$body   = substr( $result, $header_size );

			// Close curl resource
			curl_close( $curl );

			// After curl_exec, update quota
			$this->setQuota( $headers );

			// Decode only the body part
			$response = json_decode( $body, true );


			if ( isset( $response['error'] ) ) {
				new WP_Error( $response['error']['code'], $response['error']['message'] );
				error_log( "Ajax Error: " . $response['error']['message'] );
			}

			return $response;
		} catch ( \ErrorException $exception ) {
			new WP_Error( 400, $exception->getMessage() );
			error_log( $exception );
		}
	}
}