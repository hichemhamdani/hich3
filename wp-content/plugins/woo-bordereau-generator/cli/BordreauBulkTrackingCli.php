<?php

namespace WooBordereauGenerator\Cli;

use WooBordereauGenerator\Admin\BordereauGeneratorAdmin;

class BordreauBulkTrackingCli
{
    /**
     * @since 2.11.0
     * @param $args
     * @param $assoc_args
     * @return void
     */
    public function __invoke($args, $assoc_args) {


        $provider = null;
        if (isset($assoc_args['provider'])) {
            $provider = $assoc_args['provider'];
        }

        try {
            \WP_CLI::log("Start Getting tracking info...");
            $start = microtime(true);

            $admin = new BordereauGeneratorAdmin(WC_BORDEREAU_POST_TYPE, WC_BORDEREAU_GENERATOR_VERSION);
            $admin->bordereau_tracking_check(true, array_filter(explode(',', $provider)));
            $time_elapsed_secs = microtime(true) - $start;

            \WP_CLI::success("Command executed successfully in $time_elapsed_secs sec.");

        } catch (\ErrorException $exception) {
            \WP_CLI::error($exception->getMessage());
        }


    }
}