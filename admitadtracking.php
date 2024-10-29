<?php

declare(strict_types=1);

const ADMITAD_TRACKING_PLUGIN_PATH = __DIR__;

/**
 * Plugin Name: Admitad Tracking
 * Description: Integration with CPA network Admitad
 * Version: 2.0.0
 * Author: Admitad
 * Author URI: https://admitad.com
 * Text Domain: admitadtracking
 * Domain Path: /languages
 * License: GPL2.
 */

include_once 'admitadtracking.class.php';
include_once 'autoloader.php';

call_user_func(function () {
    load_plugin_textdomain('admitadtracking', false, dirname(plugin_basename(__FILE__)) . '/languages');

    $plugin = new AdmitadTrackingPlugin();
    add_action('init', [$plugin, 'init']);
    add_action('admin_init', [$plugin, 'adminInit']);
    add_action('woocommerce_checkout_update_order_meta', [$plugin, 'handleOrderCreate']);
    add_action('wp_login', [$plugin, 'onLogin']);

    add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
        $settings_link = '<a href="options-general.php?page=admitadtracking">' . __('Settings', 'admitadtracking') . '</a>';
        array_unshift($links, $settings_link);

        return $links;
    });
    add_action('admin_menu', [$plugin, 'adminMenu']);

    add_action('woocommerce_checkout_order_processed', function ($orderId) {
        if (!session_id()) {
            session_start();
        }

        $_SESSION['order_id'] = $orderId;
    });

    add_action('wp_enqueue_scripts', [$plugin, 'registerScripts']);
});
