<?php

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/**
 * Fremius Integration
 */
function server_info_fs() {
    global $server_info_fs;

    if ( ! isset( $server_info_fs ) ) {
        // Include Freemius SDK.
        require_once dirname(__FILE__) . '/freemius/start.php';

        $server_info_fs = fs_dynamic_init( array(
            'id'                  => '2860',
            'slug'                => 'server-info',
            'type'                => 'plugin',
            'public_key'          => 'pk_6e7a210fbe9898524cf4df3c6d6fb',
            'is_premium'          => false,
            'has_addons'          => false,
            'has_paid_plans'      => false,
            'menu'                => array(
                'slug'           => 'server_info_display',
                'account'        => false,
                'support'        => true,
                'contact'        => false,
                'parent'         => array(
                    'slug' => 'options-general.php',
                ),
            ),
        ) );
    }

    return $server_info_fs;
}

/**
 * Init Freemius
 */
server_info_fs();
do_action( 'server_info_fs_loaded' );

include_once PLUGIN_DIR . 'classes/classes.php';

include_once PLUGIN_DIR . 'actions/actions.php';
