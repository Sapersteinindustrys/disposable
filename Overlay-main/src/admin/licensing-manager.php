<?php
/**
 * Handles premium licensing and activation logic for Product Customizer.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class PC_Licensing_Manager {
    private const STATUS_OPTION = 'pc_license_status';
    private const KEY_OPTION = 'pc_license_key';
    private const TOKEN_OPTION = 'pc_license_activation_token';
    private const TIER_OPTION = 'pc_license_tier';
    private const LAST_CHECK_OPTION = 'pc_license_last_check';
    private const LAST_ERROR_OPTION = 'pc_license_last_error';
    private const PRODUCT_LIMIT_OPTION = 'pc_license_product_limit';
    private const NOTICE_TRANSIENT = 'pc_license_admin_notice';
    private const CRON_HOOK = 'pc_refresh_license_status_event';

    private static $instance = null;

    /**
     * Singleton accessor.
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_filter('pc_is_premium_active', [$this, 'filter_is_premium_active']);
        add_filter('pc_free_product_limit', [$this, 'filter_free_product_limit']);

        add_action('init', [$this, 'maybe_schedule_cron']);
        add_action(self::CRON_HOOK, [$this, 'handle_scheduled_refresh']);

        add_action('admin_post_pc_refresh_license', [$this, 'handle_manual_refresh']);
        add_action('admin_post_pc_deactivate_license', [$this, 'handle_manual_deactivate']);

        add_action('admin_notices', [$this, 'maybe_render_notice']);
    }

    /**
     * Hook: pc_is_premium_active
     */
    public function filter_is_premium_active($is_premium) {
        if ($is_premium) {
            return true;
        }
        return $this->get_license_status_value() === 'active';
    }

    /**
     * Hook: pc_free_product_limit
     */
    public function filter_free_product_limit($limit) {
        $override = $this->get_product_limit_override();

        if ($this->is_license_active()) {
            if ($override !== null) {
                return max(0, (int) $override);
            }
            return 0; // Unlimited by default for active licenses.
        }

        if ($override !== null && $override >= 0) {
            return (int) $override;
        }

        return $limit;
    }

    /**
     * Handle settings save for licensing section.
     *
     * @param array $options Array keyed by option id => ['value' => '', 'previous' => ''].
     */
    public function handle_settings_save($options) {
        $field = $options[self::KEY_OPTION] ?? null;
        $new_key = is_array($field) && array_key_exists('value', $field) ? trim($field['value']) : '';
        $previous_key = is_array($field) && array_key_exists('previous', $field) ? trim((string) $field['previous']) : trim((string) get_option(self::KEY_OPTION, ''));

        if ($new_key === '') {
            if ($previous_key !== '') {
                $this->deactivate_license(false);
                $this->set_admin_notice(__('License key removed. Premium features are now disabled.', 'product-customizer-for-woocommerce'), 'warning');
            } else {
                $this->reset_local_license_state();
            }
            $this->unschedule_cron();
            return;
        }

        if ($new_key !== $previous_key) {
            $this->activate_license($new_key, true);
            return;
        }

        // Same key persisted. Attempt activation if not already active.
        if ($this->get_license_status_value() !== 'active') {
            $this->activate_license($new_key, false);
        }
    }

    /**
     * Save a license key from the custom admin dashboard.
     */
    public function process_license_key_submission($submitted_key) {
        $this->handle_settings_save([
            self::KEY_OPTION => [
                'value' => trim((string) $submitted_key),
                'previous' => trim((string) get_option(self::KEY_OPTION, '')),
            ],
        ]);
    }

    /**
     * Attempt to activate license via API.
     */
    private function activate_license($license_key, $show_success_notice = true) {
        $endpoint = $this->build_endpoint('/licenses/activate');

        if (empty($endpoint)) {
            $this->set_admin_notice(
                __('License key saved. Configure the licensing API endpoint to complete activation.', 'product-customizer-for-woocommerce'),
                'warning'
            );
            $this->update_status('pending');
            $this->maybe_schedule_cron();
            return false;
        }

        $product_identifier = $this->get_product_slug();

        $body = [
            'license_key' => $license_key,
            'site_url' => $this->get_site_url_for_api(),
            'product_slug' => $product_identifier,
            'product' => $product_identifier,
            'plugin_version' => defined('PC_VERSION') ? PC_VERSION : '0',
        ];

        $response = wp_remote_post($endpoint, [
            'timeout' => 20,
            'headers' => [
                'Accept' => 'application/json',
            ],
            'body' => $body,
        ]);

        if (is_wp_error($response)) {
            $this->record_error($response->get_error_message());
            $this->set_admin_notice(
                sprintf(
                    __('Could not reach the licensing server: %s', 'product-customizer-for-woocommerce'),
                    $response->get_error_message()
                ),
                'error'
            );
            $this->update_status('error');
            return false;
        }

        $data = $this->parse_response($response);
        if (!$data['success']) {
            $message = $data['message'] ?: __('License activation failed. Please verify your key and try again.', 'product-customizer-for-woocommerce');
            $this->record_error($message);
            $this->update_status('inactive');
            $this->set_admin_notice($message, 'error');
            return false;
        }

        $payload = $data['payload'];
        $this->store_license_payload($payload);
        $this->record_error('');
        $this->update_status('active');
        $this->maybe_schedule_cron();

        if ($show_success_notice) {
            $this->set_admin_notice(__('License activated successfully.', 'product-customizer-for-woocommerce')); 
        }

        return true;
    }

    /**
     * Refresh license status via API.
     */
    private function refresh_license_status($force_notice = false) {
        $license_key = $this->get_license_key();
        if (empty($license_key)) {
            return false;
        }

        $endpoint = $this->build_endpoint('/licenses/' . rawurlencode($license_key));
        if (empty($endpoint)) {
            $this->set_admin_notice(
                __('Unable to refresh license status: licensing API endpoint is not configured.', 'product-customizer-for-woocommerce'),
                'warning'
            );
            return false;
        }

        $response = wp_remote_get($endpoint, [
            'timeout' => 20,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            $this->record_error($response->get_error_message());
            if ($force_notice) {
                $this->set_admin_notice(
                    sprintf(
                        __('License status refresh failed: %s', 'product-customizer-for-woocommerce'),
                        $response->get_error_message()
                    ),
                    'error'
                );
            }
            return false;
        }

        $data = $this->parse_response($response);
        if (!$data['success']) {
            $message = $data['message'] ?: __('License validation failed. Please review your subscription.', 'product-customizer-for-woocommerce');
            $this->record_error($message);
            $this->update_status('inactive');
            if ($force_notice) {
                $this->set_admin_notice($message, 'error');
            }
            return false;
        }

        $this->store_license_payload($data['payload']);
        $this->record_error('');
        $this->update_status('active');

        if ($force_notice) {
            $this->set_admin_notice(__('License status refreshed.', 'product-customizer-for-woocommerce'));
        }

        return true;
    }

    /**
     * Deactivate the license (locally and remotely when available).
     */
    private function deactivate_license($show_notice = true) {
        $license_key = $this->get_license_key();
        if (empty($license_key)) {
            $this->reset_local_license_state();
            return;
        }

        $endpoint = $this->build_endpoint('/licenses/deactivate');
        if (!empty($endpoint)) {
            $product_identifier = $this->get_product_slug();
            wp_remote_post($endpoint, [
                'timeout' => 20,
                'headers' => [
                    'Accept' => 'application/json',
                ],
                'body' => [
                    'license_key' => $license_key,
                    'site_url' => $this->get_site_url_for_api(),
                    'product_slug' => $product_identifier,
                    'product' => $product_identifier,
                ],
            ]);
        }

        $this->reset_local_license_state();
        $this->update_status('inactive');
        $this->unschedule_cron();

        if ($show_notice) {
            $this->set_admin_notice(__('License deactivated.', 'product-customizer-for-woocommerce'), 'warning');
        }
    }

    /**
     * Schedule cron to refresh license status daily when a license key exists.
     */
    public function maybe_schedule_cron() {
        $license_key = $this->get_license_key();
        if (empty($license_key)) {
            $this->unschedule_cron();
            return;
        }

        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + DAY_IN_SECONDS, 'daily', self::CRON_HOOK);
        }
    }

    private function unschedule_cron() {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }
    }

    /**
     * Cron callback.
     */
    public function handle_scheduled_refresh() {
        $this->refresh_license_status(false);
    }

    /**
     * Manual refresh handler (admin-post).
     */
    public function handle_manual_refresh() {
        if (!current_user_can('manage_woocommerce') && !current_user_can('manage_options')) {
            wp_die(__('You do not have permission to refresh the license.', 'product-customizer-for-woocommerce'));
        }

        check_admin_referer('pc_refresh_license');
        $this->refresh_license_status(true);
        $this->redirect_back();
    }

    /**
     * Manual deactivate handler (admin-post).
     */
    public function handle_manual_deactivate() {
        if (!current_user_can('manage_woocommerce') && !current_user_can('manage_options')) {
            wp_die(__('You do not have permission to deactivate the license.', 'product-customizer-for-woocommerce'));
        }

        check_admin_referer('pc_deactivate_license');
        $this->deactivate_license(true);
        $this->redirect_back();
    }

    /**
     * Prepare summary for settings UI.
     */
    public function get_status_summary() {
        $status = $this->get_license_status_value();
        $status_label = $this->format_status_label($status);
        $tier = get_option(self::TIER_OPTION, '');
        $product_limit = $this->get_product_limit_override();
        $last_check = $this->get_last_check_time();
        $last_error = get_option(self::LAST_ERROR_OPTION, '');

        return [
            'status' => $status,
            'status_label' => $status_label,
            'tier' => $tier,
            'product_limit' => $product_limit,
            'last_checked' => $last_check,
            'last_error' => $last_error,
            'license_key_present' => $this->get_license_key() !== '',
        ];
    }

    /**
     * Generate URL for manual refresh link.
     */
    public function get_manual_refresh_url() {
        return wp_nonce_url(admin_url('admin-post.php?action=pc_refresh_license'), 'pc_refresh_license');
    }

    /**
     * Generate URL for manual deactivate link.
     */
    public function get_manual_deactivate_url() {
        return wp_nonce_url(admin_url('admin-post.php?action=pc_deactivate_license'), 'pc_deactivate_license');
    }

    /**
     * Display admin notice if queued.
     */
    public function maybe_render_notice() {
        if (!is_admin()) {
            return;
        }

        $notice = get_transient(self::NOTICE_TRANSIENT);
        if (!$notice) {
            return;
        }

        delete_transient(self::NOTICE_TRANSIENT);

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $allowed_screens = [
            'woocommerce_page_wc-settings',
            'toplevel_page_product-customizer-settings',
        ];

        if ($screen && !in_array($screen->id, $allowed_screens, true)) {
            return;
        }

        $class = 'notice notice-success is-dismissible';
        if ($notice['type'] === 'error') {
            $class = 'notice notice-error';
        } elseif ($notice['type'] === 'warning') {
            $class = 'notice notice-warning';
        }

        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($notice['message']));
    }

    /**
     * Helpers
     */
    private function get_license_key() {
        return trim((string) get_option(self::KEY_OPTION, ''));
    }

    private function get_license_status_value() {
        return get_option(self::STATUS_OPTION, 'inactive');
    }

    private function get_product_limit_override() {
        $value = get_option(self::PRODUCT_LIMIT_OPTION, null);
        if ($value === null || $value === '') {
            return null;
        }
        return is_numeric($value) ? (int) $value : null;
    }

    private function get_last_check_time() {
        $timestamp = (int) get_option(self::LAST_CHECK_OPTION, 0);
        if ($timestamp <= 0) {
            return '';
        }
        return get_date_from_gmt(gmdate('Y-m-d H:i:s', $timestamp), get_option('date_format') . ' ' . get_option('time_format'));
    }

    private function is_license_active() {
        return $this->get_license_status_value() === 'active';
    }

    private function store_license_payload($payload) {
        $token = isset($payload['activation_token']) ? sanitize_text_field($payload['activation_token']) : '';
        $tier = isset($payload['tier']) ? sanitize_text_field($payload['tier']) : '';
        $limit = isset($payload['max_products']) ? (int) $payload['max_products'] : null;

        update_option(self::TOKEN_OPTION, $token);
        update_option(self::TIER_OPTION, $tier);

        if ($limit !== null) {
            update_option(self::PRODUCT_LIMIT_OPTION, $limit);
        }

        update_option(self::LAST_CHECK_OPTION, time());
    }

    private function update_status($status) {
        update_option(self::STATUS_OPTION, $status);
    }

    private function record_error($message) {
        update_option(self::LAST_ERROR_OPTION, $message);
    }

    private function reset_local_license_state() {
        delete_option(self::TOKEN_OPTION);
        delete_option(self::TIER_OPTION);
        delete_option(self::PRODUCT_LIMIT_OPTION);
        delete_option(self::LAST_CHECK_OPTION);
        delete_option(self::LAST_ERROR_OPTION);
        update_option(self::STATUS_OPTION, 'inactive');
    }

    private function set_admin_notice($message, $type = 'success') {
        set_transient(self::NOTICE_TRANSIENT, [
            'message' => $message,
            'type' => $type,
        ], 30);
    }

    private function redirect_back() {
        $redirect = wp_get_referer();
        if (!$redirect) {
            $redirect = admin_url('admin.php?page=wc-settings&tab=product_customizer&section=licensing');
        }
        wp_safe_redirect($redirect);
        exit;
    }

    private function parse_response($response) {
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        if (!is_array($decoded)) {
            $preview_length = 200;
            $snippet = function_exists('mb_substr') ? mb_substr($body, 0, $preview_length) : substr($body, 0, $preview_length);
            $body_preview = trim(wp_strip_all_tags($snippet));
            if ($body_preview !== '') {
                $message = sprintf(
                    /* translators: 1: HTTP status code, 2: response preview */
                    __('Licensing server error (%1$s): %2$s', 'product-customizer-for-woocommerce'),
                    $code ? $code : __('n/a', 'product-customizer-for-woocommerce'),
                    $body_preview
                );
            } else {
                $message = $code >= 200 && $code < 300
                    ? __('Unexpected response from licensing server.', 'product-customizer-for-woocommerce')
                    : __('Licensing server returned an error.', 'product-customizer-for-woocommerce');
            }

            return [
                'success' => ($code >= 200 && $code < 300),
                'message' => $message,
                'payload' => [],
            ];
        }

        $success = isset($decoded['success']) ? (bool) $decoded['success'] : ($code >= 200 && $code < 300);
        $message = isset($decoded['message']) ? sanitize_text_field($decoded['message']) : '';

        if (isset($decoded['data']) && is_array($decoded['data'])) {
            $payload = $decoded['data'];
        } elseif (isset($decoded['license']) && is_array($decoded['license'])) {
            $payload = $decoded['license'];
        } else {
            $payload = $decoded;
            if ($message !== '' && isset($payload['message'])) {
                unset($payload['message']);
            }
            if (isset($payload['success'])) {
                unset($payload['success']);
            }
        }

        return [
            'success' => $success,
            'message' => $message,
            'payload' => $payload,
        ];
    }

    private function build_endpoint($path) {
        $base = defined('PC_LICENSE_API_BASE_URL') ? PC_LICENSE_API_BASE_URL : '';
        $base = apply_filters('pc_license_api_base_url', $base);
        $base = trim((string) $base);

        if ($base === '') {
            return '';
        }

        $base = untrailingslashit($base);
        $path = '/' . ltrim($path, '/');
        $url = $base . $path;
        return add_query_arg('pc_cache_buster', $this->get_cache_buster_value(), $url);
    }

    private function get_site_url_for_api() {
        $site_url = defined('PC_LICENSE_SITE_URL') ? PC_LICENSE_SITE_URL : home_url();
        $site_url = apply_filters('pc_license_site_url', $site_url);
        return trim((string) $site_url);
    }

    private function get_product_slug() {
        $slug = 'overlay-product-customizer';
        return apply_filters('pc_license_product_slug', $slug);
    }

    private function get_cache_buster_value() {
        $value = defined('PC_VERSION') ? PC_VERSION : time();
        return apply_filters('pc_license_cache_buster', $value);
    }

    private function format_status_label($status) {
        switch ($status) {
            case 'active':
                return __('Active', 'product-customizer-for-woocommerce');
            case 'pending':
                return __('Pending Activation', 'product-customizer-for-woocommerce');
            case 'error':
                return __('Error', 'product-customizer-for-woocommerce');
            default:
                return __('Inactive', 'product-customizer-for-woocommerce');
        }
    }
}
