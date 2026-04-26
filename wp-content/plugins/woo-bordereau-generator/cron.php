<?php
if (php_sapi_name() !== 'cli') {
	die('This script can only be run from the command line');
}

// ***** FLUSH + buffering OFF settings *****
while (ob_get_level() > 0) { ob_end_flush(); }
ob_implicit_flush(true);
ini_set('output_buffering', '0');
ini_set('zlib.output_compression', 'Off');

// Output function to write directly to stdout
function std_echo($msg) {
	fwrite(STDOUT, $msg);
	fflush(STDOUT);
}

// Ignore user aborts
ignore_user_abort(true);

// Prevent if POST or AJAX or cron (not CLI cron), guard only once
if (!empty($_POST) || defined('DOING_AJAX') || defined('DOING_CRON')) {
	die();
}

// Set up WP
if (!defined('ABSPATH')) {
	if (file_exists(__DIR__ . '/../../../wp/wp-load.php')) {
		require_once __DIR__ . '/../../../wp/wp-load.php';
	} else {
		require_once __DIR__ . '/../../../wp-load.php';
	}
	require_once __DIR__ . '/vendor/autoload.php';
}
define('DOING_CRON', true);

wp_raise_memory_limit('cron');
ini_set('memory_limit', '-1');

$start_time = date('Y-m-d H:i:s');
std_echo("[START] Cron job started at: {$start_time}\n");

// Run your task
use WooBordereauGenerator\Admin\BordereauGeneratorAdmin;
$admin = new BordereauGeneratorAdmin(WC_BORDEREAU_POST_TYPE, WC_BORDEREAU_GENERATOR_VERSION);
$admin->bordereau_tracking_check_optimized(true);

$end_time = date('Y-m-d H:i:s');
std_echo("[END] Cron job finished at: {$end_time}\n");
$execution_time = strtotime($end_time) - strtotime($start_time);
std_echo("[INFO] Total execution time: {$execution_time} seconds\n");