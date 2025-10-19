<?php
/**
 * Plugin Name:       Product Customizer for WooCommerce
 * Plugin URI:        https://github.com/Sapersteinindustrys/Overlay
 * Description:       A comprehensive product customizer for WooCommerce. Allows admins to create detailed customization options for products, which customers can use via a frontend modal.
 * Version:           3.1.9
 * Author:            Saperstein T. Industries
 * Author URI:        https://github.com/Sapersteinindustrys
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       product-customizer-for-woocommerce
 * Domain Path:       /languages
 * WC requires at least: 5.0
 * WC tested up to: 8.4
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

final class Product_Customizer_Plugin {

    /**
     * Plugin version.
     */
    public $version = '3.1.9';

    /**
     * The single instance of the class.
     */
    private static $_instance = null;

    /**
     * Main Plugin Instance.
     *
     * Ensures only one instance of the plugin is loaded or can be loaded.
     */
    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->define_constants();
        $this->declare_hpos_compatibility();
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Define Plugin Constants.
     */
    private function define_constants() {
        define('PC_PLUGIN_FILE', __FILE__);
        define('PC_PLUGIN_DIR', plugin_dir_path(PC_PLUGIN_FILE));
        define('PC_PLUGIN_URL', plugin_dir_url(PC_PLUGIN_FILE));
        define('PC_VERSION', $this->version);
        if (!defined('PC_LICENSE_API_BASE_URL')) {
            // Base URL for the remote licensing API (must match the registered REST namespace).
            define('PC_LICENSE_API_BASE_URL', 'https://lqe.zkn.mybluehost.me/website_d5192812/wp-json/overlay-licensing/v1');
        }
        if (!defined('PC_LICENSE_SITE_URL')) {
            // Site URL reported to the licensing API.
            define('PC_LICENSE_SITE_URL', 'https://lqe.zkn.mybluehost.me/website_d5192812');
        }
    }

    /**
     * Declare compatibility with WooCommerce High-Performance Order Storage.
     */
    private function declare_hpos_compatibility() {
        add_action('before_woocommerce_init', function () {
            if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', PC_PLUGIN_FILE, true);
            }
        });
    }

    /**
     * Include required core files.
     */
    public function includes() {
        // Core classes
        require_once PC_PLUGIN_DIR . 'src/admin/database.php';
        require_once PC_PLUGIN_DIR . 'src/admin/settings-manager.php';
        require_once PC_PLUGIN_DIR . 'src/admin/licensing-manager.php';
    require_once PC_PLUGIN_DIR . 'src/admin/library-manager.php';
        require_once PC_PLUGIN_DIR . 'src/admin/admin-dashboard.php';
        require_once PC_PLUGIN_DIR . 'src/admin/settings.php';
        require_once PC_PLUGIN_DIR . 'src/admin/product-options.php';

        // Setup wizard (admin)
        if (is_admin()) {
            $wizard_file = PC_PLUGIN_DIR . 'src/admin/setup-wizard.php';
            if (file_exists($wizard_file)) {
                require_once $wizard_file;
            }
        }

        // Frontend classes
        require_once PC_PLUGIN_DIR . 'src/frontend/class-pc-frontend.php';
        // frontend-manager removed from bootstrap to avoid duplicate hooks and assets

        // Initialize admin classes - removed duplicate instantiation
        // PC_Product_Options now initializes itself through hooks
    }

    /**
     * Hook into actions and filters.
     */
    private function init_hooks() {
        // Activation and Deactivation Hooks
        register_activation_hook(PC_PLUGIN_FILE, function(){
            // Install DB tables
            PC_Database::install();
            // Mark version & trigger activation redirect (use option for fallback if transients unavailable)
            update_option('pc_plugin_version', PC_VERSION);
            set_transient('pc_do_activation_redirect', 1, 30);
            update_option('pc_do_activation_redirect_fallback', 1);
            // Reset wizard completion so it always opens on fresh activation per requirement
            delete_option('pc_wizard_completed');
            update_option('pc_last_activation_time', time());
        });
        register_deactivation_hook(PC_PLUGIN_FILE, ['PC_Database', 'deactivate']);
        register_uninstall_hook(PC_PLUGIN_FILE, ['PC_Database', 'uninstall']);

        add_action('plugins_loaded', [$this, 'on_plugins_loaded']);
        // Register image sizes early
        add_action('init', function(){
            if (function_exists('add_image_size')) {
                // Square, hard-cropped swatch thumbnail
                add_image_size('pc_swatch_thumb', 120, 120, true);
            }
        });

        // Handle activation or update redirect to wizard
        add_action('admin_init', [$this, 'maybe_redirect_to_wizard']);
    }

    /**
     * Runs when plugins are loaded.
     */
    public function on_plugins_loaded() {
        $woocommerce_active = class_exists('WooCommerce');

        if (!$woocommerce_active) {
            add_action('admin_notices', [$this, 'woocommerce_missing_notice']);
        }

        // Initialize plugin components (with graceful fallback when WooCommerce is missing)
        $this->init_components($woocommerce_active);

        if ($woocommerce_active) {
            // Additional initialization for WooCommerce integration
            add_action('init', [$this, 'init_woocommerce_integration'], 20);
        }

        // Version change detection (update) -> trigger wizard redirect
        if (is_admin()) {
            $stored_version = get_option('pc_plugin_version');
            if ($stored_version !== PC_VERSION) {
                update_option('pc_plugin_version', PC_VERSION);
                // Set transient so next admin_init can redirect
                set_transient('pc_do_activation_redirect', 1, 30);
            }
        }
    }

    /**
     * Initialize WooCommerce specific integrations
     */
    public function init_woocommerce_integration() {
        // This ensures settings are properly registered with WooCommerce
        do_action('pc_init_woocommerce');
    }

    /**
     * Initialize all the core components of the plugin.
     */
    public function init_components($woocommerce_active = true) {
        // Initialize settings manager first
        $settings_manager = PC_Settings_Manager::instance();
        // Initialize licensing manager so filters apply everywhere
        PC_Licensing_Manager::instance();
        // Ensure library manager is available for dashboards and ajax
        PC_Library_Manager::instance();

        if (is_admin()) {
            new PC_Admin_Dashboard();
            new PC_Settings();
            if ($woocommerce_active) {
                PC_Product_Options::instance();
            }
        }
        
        // Initialize frontend for all requests
        if ($woocommerce_active && class_exists('PC_Frontend')) {
            PC_Frontend::instance();
        }
        
        // PC_Frontend_Manager intentionally not initialized to prevent duplicate button/modal
    }

    /**
     * Redirect to setup wizard after activation or version update.
     */
    public function maybe_redirect_to_wizard() {
        // Guard: admin area only
        if ( ! is_admin() ) return;
        // Skip for network admin, bulk activations, AJAX/CRON/REST
        if ( is_network_admin() || isset($_GET['activate-multi']) || wp_doing_ajax() || (defined('DOING_CRON') && DOING_CRON) || defined('REST_REQUEST') ) return;
        // If already on wizard page, no redirect
        if ( isset($_GET['page']) && $_GET['page'] === 'pc-setup-wizard' ) return;

        // Capability: allow manage_woocommerce OR (WooCommerce missing -> fallback to manage_options / activate_plugins)
        $has_cap = current_user_can('manage_woocommerce') || current_user_can('manage_options') || current_user_can('activate_plugins');
        if ( ! $has_cap ) return;

        $should_redirect = false;
        if ( get_transient('pc_do_activation_redirect') ) {
            delete_transient('pc_do_activation_redirect');
            $should_redirect = true;
        } elseif ( get_option('pc_do_activation_redirect_fallback') ) {
            // Fallback if transient not persisted (some hosts disable object cache for transients)
            delete_option('pc_do_activation_redirect_fallback');
            $should_redirect = true;
        }

        if ( $should_redirect ) {
                $url = admin_url('admin.php?page=pc-setup-wizard&pc_wizard_fresh=1');
            // Add flag if WooCommerce inactive so wizard can show notice
            if ( ! class_exists('WooCommerce') ) {
                $url = add_query_arg('pc_wc_inactive', '1', $url);
            }
            wp_safe_redirect($url);
            exit;
        }
    }

    /**
     * WooCommerce missing notice.
     */
    public function woocommerce_missing_notice() {
        echo '<div class="notice notice-error"><p><strong>Product Customizer for WooCommerce:</strong> This plugin requires WooCommerce to be installed and active. Please install and activate WooCommerce.</p></div>';
    }

    // All frontend logic (display_customize_button, add_modal_to_footer, cart handling, etc.)
    // will be added in the next phase. This keeps the admin implementation clean and focused.
}

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 */
function run_product_customizer_plugin() {
    return Product_Customizer_Plugin::instance();
}

// Let's get this party started
run_product_customizer_plugin();