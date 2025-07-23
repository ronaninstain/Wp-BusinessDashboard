<?php
/*
Plugin Name: Business Dashboard
Description: Business Dashboard with bulk import, company/employee linking, and course & category assignment
Version:     1.7
Author:      Shoive Hossain
*/

if (! defined('ABSPATH')) exit;

// Autoloader for PhpSpreadsheet
$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

// Constants
define('BD_PLUGIN_DIR',        plugin_dir_path(__FILE__));
define('BD_PLUGIN_URL',        plugin_dir_url(__FILE__));
define('BD_PLUGIN_ASSETS_URL', BD_PLUGIN_URL . 'assets/');

// Includes
require_once BD_PLUGIN_DIR . 'includes/class-bd-roles.php';
require_once BD_PLUGIN_DIR . 'includes/class-bd-import.php';
require_once BD_PLUGIN_DIR . 'includes/class-bd-company-admin.php';
require_once BD_PLUGIN_DIR . 'includes/class-bd-manage-companies.php';
require_once BD_PLUGIN_DIR . 'includes/class-bd-student-controller.php';
require_once BD_PLUGIN_DIR . 'includes/class-bd-dashboard.php';
require_once BD_PLUGIN_DIR . 'includes/class-bd-register-employees.php';

// Initialize custom roles
add_action('plugins_loaded', function () {
    new BD_Roles();
});

// Enqueue scripts/styles on our Manage Companies page
add_action('admin_enqueue_scripts', function ($hook) {
    // Assign course/category page
    if ('business-dashboard_page_bd-manage-companies' === $hook) {
        wp_enqueue_script('bd-assign-course',   BD_PLUGIN_ASSETS_URL . 'js/assign-course.js',   ['jquery'], '1.0', true);
        wp_enqueue_script('bd-assign-category', BD_PLUGIN_ASSETS_URL . 'js/assign-category.js', ['jquery'], '1.0', true);
        wp_localize_script('bd-assign-category', 'BD_Ajax', ['ajax_url' => admin_url('admin-ajax.php')]);
    }

    // Register Employees page ─ slug bd-register-employees
    if ('business-dashboard_page_bd-register-employees' === $hook) {
        wp_enqueue_script(
            'bd-register-employees',
            BD_PLUGIN_ASSETS_URL . 'js/register-employees.js',
            ['jquery'],
            '1.0',
            true
        );
        wp_localize_script('bd-register-employees', 'BD_Ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
        ]);
    }
});


// AJAX handlers for both single-course and category
add_action('wp_ajax_bd_assign_course',   [new BD_Student_Controller(), 'AssignStudent']);
add_action('wp_ajax_bd_assign_category', [new BD_Student_Controller(), 'AssignCategory']);

// Build admin menu
add_action('admin_menu', function () {
    add_menu_page(
        'Business Dashboard',
        'Business Dashboard',
        'manage_options',
        'business-dashboard',
        ['BD_Dashboard', 'dashboard_page'],
        'dashicons-dashboard',
        20
    );

    // Dashboard
    add_submenu_page(
        'business-dashboard',
        'Dashboard',
        'Dashboard',
        'manage_options',
        'business-dashboard',
        ['BD_Dashboard', 'dashboard_page']
    );

    // Bulk Import
    $imp = new BD_Import();
    add_submenu_page(
        'business-dashboard',
        'Bulk Import Users',
        'Bulk Import',
        'manage_options',
        'bd-bulk-import',
        [$imp, 'bulk_import_page']
    );

    // Manage Companies
    $cmp = new BD_Manage_Companies();
    add_submenu_page(
        'business-dashboard',
        'Manage Companies',
        'Manage Companies',
        'manage_options',
        'bd-manage-companies',
        [$cmp, 'manage_companies_page']
    );

    // Company Admin manager
    $ca = new BD_Company_Admin_Manager();
    $ca->register_page();

    // **Register Employees**
    $reg = new BD_Register_Employees();
    $reg->register_page();
});
