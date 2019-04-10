<?php
/**
 * Plugin Name: Products Migration
 */

if ( ! defined('ABSPATH') ) exit;

/**
 * pm stands for Products Migration
 *
 * 
 * Add Plugin's page to admin menu
 */
function pm_setup_menu() {
    # add_menu_page($page_title, $menu_title, $capability, $menu_slug, $function, $icon_url, $position);
    add_menu_page('Products Migration', 'Products Migration', 'manage_options', 'products-migration', 'pm_init', plugins_url('assets/icon/pm.svg', __FILE__), 10);
}
add_action('admin_menu', 'pm_setup_menu');


/**
 * Admin Page
 */
function pm_init() {
    global $wpdb;





    ?>
    <div class="wrap">
        <h1>Products Migration Info</h1>
    </div>
    <?php
}