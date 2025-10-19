<?php
if (!defined('ABSPATH')) {
    exit;
}

class PC_Admin_Dashboard {
    private const MENU_SLUG = 'product-customizer-settings';
    private const NOTICE_TRANSIENT = 'pc_admin_settings_notice';

    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_post_pc_save_settings', [$this, 'handle_form_submission']);
    }

    public function register_menu() {
        add_menu_page(
            __('Product Customizer', 'product-customizer-for-woocommerce'),
            __('Product Customizer', 'product-customizer-for-woocommerce'),
            $this->get_required_capability(),
            self::MENU_SLUG,
            [$this, 'render_page'],
            'dashicons-art',
            56
        );
    }

    public function enqueue_assets($hook) {
        if ($hook !== 'toplevel_page_' . self::MENU_SLUG) {
            return;
        }

        wp_enqueue_style(
            'pc-admin-dashboard',
            PC_PLUGIN_URL . 'src/assets/css/admin-dashboard.css',
            [],
            PC_VERSION
        );

        wp_enqueue_style(
            'pc-setup-wizard',
            PC_PLUGIN_URL . 'src/assets/css/admin-wizard.css',
            [],
            PC_VERSION
        );

        wp_enqueue_script(
            'pc-admin-dashboard',
            PC_PLUGIN_URL . 'src/assets/js/admin-dashboard.js',
            ['jquery'],
            PC_VERSION,
            true
        );
    }

    public function render_page() {
        if (!current_user_can($this->get_required_capability())) {
            wp_die(__('You do not have permission to manage Product Customizer settings.', 'product-customizer-for-woocommerce'));
        }

        $flash = get_transient(self::NOTICE_TRANSIENT);
        if ($flash) {
            delete_transient(self::NOTICE_TRANSIENT);
        }

        $general = $this->get_general_settings_snapshot();
        $appearance = $this->get_appearance_settings_snapshot();
        $behavior = $this->get_behavior_settings_snapshot();
        $design_presets = $this->get_design_presets();
        $licensing = PC_Licensing_Manager::instance()->get_status_summary();
        $license_key = get_option('pc_license_key', '');
        $refresh_url = PC_Licensing_Manager::instance()->get_manual_refresh_url();
        $deactivate_url = PC_Licensing_Manager::instance()->get_manual_deactivate_url();

        $wizard_url = admin_url('admin.php?page=pc-setup-wizard');
        $design_studio_url = admin_url('admin.php?page=wc-settings&tab=product_customizer&section=design');
        ?>
        <div class="wrap pc-admin-dashboard">
            <h1><?php esc_html_e('Product Customizer Control Center', 'product-customizer-for-woocommerce'); ?></h1>
            <p class="pc-admin-subtitle"><?php esc_html_e('Your launchpad for keeping customization polished, on-brand, and stress-free.', 'product-customizer-for-woocommerce'); ?></p>

            <?php if ($flash) : ?>
                <div class="notice notice-<?php echo esc_attr($flash['type']); ?> is-dismissible">
                    <p><?php echo esc_html($flash['message']); ?></p>
                </div>
            <?php endif; ?>

            <div class="pc-admin-layout">
                <nav class="pc-admin-nav" aria-label="<?php esc_attr_e('Settings sections', 'product-customizer-for-woocommerce'); ?>">
                    <button type="button" class="pc-admin-nav__link is-active" data-target="pc-panel-general">
                        <span class="dashicons dashicons-controls-repeat" aria-hidden="true"></span>
                        <?php esc_html_e('General', 'product-customizer-for-woocommerce'); ?>
                    </button>
                    <button type="button" class="pc-admin-nav__link" data-target="pc-panel-appearance">
                        <span class="dashicons dashicons-art" aria-hidden="true"></span>
                        <?php esc_html_e('Appearance', 'product-customizer-for-woocommerce'); ?>
                    </button>
                    <button type="button" class="pc-admin-nav__link" data-target="pc-panel-behavior">
                        <span class="dashicons dashicons-admin-settings" aria-hidden="true"></span>
                        <?php esc_html_e('Behavior', 'product-customizer-for-woocommerce'); ?>
                    </button>
                    <button type="button" class="pc-admin-nav__link" data-target="pc-panel-licensing">
                        <span class="dashicons dashicons-lock" aria-hidden="true"></span>
                        <?php esc_html_e('Licensing', 'product-customizer-for-woocommerce'); ?>
                    </button>
                    <button type="button" class="pc-admin-nav__link" data-target="pc-panel-shortcuts">
                        <span class="dashicons dashicons-lightbulb" aria-hidden="true"></span>
                        <?php esc_html_e('Shortcuts', 'product-customizer-for-woocommerce'); ?>
                    </button>
                </nav>

                <main class="pc-admin-panels" id="pc-admin-panels">
                    <section class="pc-admin-panel is-active" id="pc-panel-general" tabindex="-1">
                        <header>
                            <h2><?php esc_html_e('General Experience', 'product-customizer-for-woocommerce'); ?></h2>
                            <p><?php esc_html_e('Fine-tune the language customers see the moment they step into your customization journey.', 'product-customizer-for-woocommerce'); ?></p>
                        </header>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pc-card">
                            <?php wp_nonce_field('pc_save_settings_general'); ?>
                            <input type="hidden" name="action" value="pc_save_settings">
                            <input type="hidden" name="pc_settings_section" value="general">
                            <div class="pc-card__body">
                                <div class="pc-field">
                                    <label for="pc_customize_button_text"><?php esc_html_e('Customize Button Text', 'product-customizer-for-woocommerce'); ?></label>
                                    <input type="text" id="pc_customize_button_text" name="pc_customize_button_text" value="<?php echo esc_attr($general['customize']); ?>" class="regular-text">
                                    <p class="description"><?php esc_html_e('Appears on product pages to invite shoppers into the builder.', 'product-customizer-for-woocommerce'); ?></p>
                                </div>
                                <div class="pc-field">
                                    <label for="pc_modal_title"><?php esc_html_e('Overlay Title', 'product-customizer-for-woocommerce'); ?></label>
                                    <input type="text" id="pc_modal_title" name="pc_modal_title" value="<?php echo esc_attr($general['title']); ?>" class="regular-text">
                                    <p class="description"><?php esc_html_e('Heading displayed at the top of the customization modal.', 'product-customizer-for-woocommerce'); ?></p>
                                </div>
                                <div class="pc-field">
                                    <label for="pc_add_to_cart_button_text"><?php esc_html_e('Add to Cart Button Text', 'product-customizer-for-woocommerce'); ?></label>
                                    <input type="text" id="pc_add_to_cart_button_text" name="pc_add_to_cart_button_text" value="<?php echo esc_attr($general['add_to_cart']); ?>" class="regular-text">
                                </div>
                                <div class="pc-field">
                                    <label for="pc_reset_button_text"><?php esc_html_e('Reset Button Text', 'product-customizer-for-woocommerce'); ?></label>
                                    <input type="text" id="pc_reset_button_text" name="pc_reset_button_text" value="<?php echo esc_attr($general['reset']); ?>" class="regular-text">
                                </div>
                                <div class="pc-field">
                                    <label for="pc_price_display_format"><?php esc_html_e('Price Display Format', 'product-customizer-for-woocommerce'); ?></label>
                                    <input type="text" id="pc_price_display_format" name="pc_price_display_format" value="<?php echo esc_attr($general['price']); ?>" class="large-text">
                                    <p class="description"><?php esc_html_e('Use {total_price} and optionally {additional_price} to keep pricing transparent.', 'product-customizer-for-woocommerce'); ?></p>
                                </div>
                            </div>
                            <footer class="pc-card__footer">
                                <button type="submit" class="button button-primary"><?php esc_html_e('Save General Settings', 'product-customizer-for-woocommerce'); ?></button>
                            </footer>
                        </form>
                    </section>

                    <section class="pc-admin-panel" id="pc-panel-appearance" tabindex="-1">
                        <header>
                            <h2><?php esc_html_e('Appearance & Layout', 'product-customizer-for-woocommerce'); ?></h2>
                            <p><?php esc_html_e('Keep the customization overlay bold, branded, and effortless to scan.', 'product-customizer-for-woocommerce'); ?></p>
                        </header>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pc-card">
                            <?php wp_nonce_field('pc_save_settings_appearance'); ?>
                            <input type="hidden" name="action" value="pc_save_settings">
                            <input type="hidden" name="pc_settings_section" value="appearance">
                            <div class="pc-card__body pc-grid">
                                <div class="pc-field">
                                    <label for="pc_modal_layout"><?php esc_html_e('Modal Layout', 'product-customizer-for-woocommerce'); ?></label>
                                    <select id="pc_modal_layout" name="pc_modal_layout">
                                        <option value="card" <?php selected($appearance['layout'], 'card'); ?>><?php esc_html_e('Card (Recommended)', 'product-customizer-for-woocommerce'); ?></option>
                                        <option value="sidebar" <?php selected($appearance['layout'], 'sidebar'); ?>><?php esc_html_e('Sidebar', 'product-customizer-for-woocommerce'); ?></option>
                                        <option value="tabs" <?php selected($appearance['layout'], 'tabs'); ?>><?php esc_html_e('Tabs', 'product-customizer-for-woocommerce'); ?></option>
                                        <option value="accordion" <?php selected($appearance['layout'], 'accordion'); ?>><?php esc_html_e('Accordion', 'product-customizer-for-woocommerce'); ?></option>
                                    </select>
                                </div>
                                <div class="pc-field">
                                    <label for="pc_modal_columns"><?php esc_html_e('Overlay Columns', 'product-customizer-for-woocommerce'); ?></label>
                                    <select id="pc_modal_columns" name="pc_modal_columns">
                                        <option value="1" <?php selected($appearance['columns'], '1'); ?>>1</option>
                                        <option value="2" <?php selected($appearance['columns'], '2'); ?>>2</option>
                                        <option value="3" <?php selected($appearance['columns'], '3'); ?>>3</option>
                                        <option value="4" <?php selected($appearance['columns'], '4'); ?>>4</option>
                                    </select>
                                </div>
                                <div class="pc-field">
                                    <label for="pc_modal_position"><?php esc_html_e('Overlay Position', 'product-customizer-for-woocommerce'); ?></label>
                                    <select id="pc_modal_position" name="pc_modal_position">
                                        <option value="center" <?php selected($appearance['position'], 'center'); ?>><?php esc_html_e('Center', 'product-customizer-for-woocommerce'); ?></option>
                                        <option value="top" <?php selected($appearance['position'], 'top'); ?>><?php esc_html_e('Top', 'product-customizer-for-woocommerce'); ?></option>
                                        <option value="bottom" <?php selected($appearance['position'], 'bottom'); ?>><?php esc_html_e('Bottom', 'product-customizer-for-woocommerce'); ?></option>
                                    </select>
                                </div>
                                <div class="pc-field">
                                    <label for="pc_modal_size"><?php esc_html_e('Modal Size', 'product-customizer-for-woocommerce'); ?></label>
                                    <select id="pc_modal_size" name="pc_modal_size">
                                        <option value="small" <?php selected($appearance['size'], 'small'); ?>><?php esc_html_e('Small', 'product-customizer-for-woocommerce'); ?></option>
                                        <option value="medium" <?php selected($appearance['size'], 'medium'); ?>><?php esc_html_e('Medium', 'product-customizer-for-woocommerce'); ?></option>
                                        <option value="large" <?php selected($appearance['size'], 'large'); ?>><?php esc_html_e('Large', 'product-customizer-for-woocommerce'); ?></option>
                                        <option value="extra-large" <?php selected($appearance['size'], 'extra-large'); ?>><?php esc_html_e('Extra Large', 'product-customizer-for-woocommerce'); ?></option>
                                    </select>
                                </div>
                                <div class="pc-field">
                                    <label for="pc_design_preset"><?php esc_html_e('Design Preset', 'product-customizer-for-woocommerce'); ?></label>
                                    <select id="pc_design_preset" name="pc_design_preset">
                                        <?php foreach ($design_presets as $value => $label) : ?>
                                            <option value="<?php echo esc_attr($value); ?>" <?php selected($appearance['preset'], $value); ?>><?php echo esc_html($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description"><?php esc_html_e('Quickly swap the full styling bundle. Adjust details in the Design Studio anytime.', 'product-customizer-for-woocommerce'); ?></p>
                                </div>
                            </div>
                            <footer class="pc-card__footer">
                                <button type="submit" class="button button-primary"><?php esc_html_e('Save Appearance', 'product-customizer-for-woocommerce'); ?></button>
                                <a href="<?php echo esc_url($design_studio_url); ?>" class="button button-link"><?php esc_html_e('Open Design Studio', 'product-customizer-for-woocommerce'); ?></a>
                            </footer>
                        </form>
                    </section>

                    <section class="pc-admin-panel" id="pc-panel-behavior" tabindex="-1">
                        <header>
                            <h2><?php esc_html_e('Interaction Behavior', 'product-customizer-for-woocommerce'); ?></h2>
                            <p><?php esc_html_e('Keep momentum high with thoughtful defaults and friendly safety nets.', 'product-customizer-for-woocommerce'); ?></p>
                        </header>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pc-card">
                            <?php wp_nonce_field('pc_save_settings_behavior'); ?>
                            <input type="hidden" name="action" value="pc_save_settings">
                            <input type="hidden" name="pc_settings_section" value="behavior">
                            <div class="pc-card__body pc-toggle-list">
                                <?php $this->render_toggle('pc_enable_outside_click_close', __('Allow closing when clicking outside the modal', 'product-customizer-for-woocommerce'), __('Shoppers can exit with a single click on the dimmed background.', 'product-customizer-for-woocommerce'), $behavior['outside_click']); ?>
                                <?php $this->render_toggle('pc_enable_escape_key_close', __('Enable Escape key to close', 'product-customizer-for-woocommerce'), __('Adds a familiar keyboard escape hatch.', 'product-customizer-for-woocommerce'), $behavior['escape_key']); ?>
                                <?php $this->render_toggle('pc_auto_focus_first_field', __('Auto-focus the first field', 'product-customizer-for-woocommerce'), __('Drops shoppers directly into the opening step.', 'product-customizer-for-woocommerce'), $behavior['auto_focus']); ?>
                                <?php $this->render_toggle('pc_validate_on_change', __('Validate selections in real time', 'product-customizer-for-woocommerce'), __('Catches issues early so confidence stays high.', 'product-customizer-for-woocommerce'), $behavior['validate']); ?>
                            </div>
                            <footer class="pc-card__footer">
                                <button type="submit" class="button button-primary"><?php esc_html_e('Save Behavior', 'product-customizer-for-woocommerce'); ?></button>
                            </footer>
                        </form>
                    </section>

                    <section class="pc-admin-panel" id="pc-panel-licensing" tabindex="-1">
                        <header>
                            <h2><?php esc_html_e('Premium Licensing', 'product-customizer-for-woocommerce'); ?></h2>
                            <p><?php esc_html_e('Activate, refresh, or troubleshoot your premium access in one calm view.', 'product-customizer-for-woocommerce'); ?></p>
                        </header>
                        <div class="pc-card">
                            <div class="pc-card__body pc-license-meta">
                                <div>
                                    <span class="pc-status pc-status--<?php echo esc_attr($licensing['status']); ?>"><?php echo esc_html($licensing['status_label']); ?></span>
                                    <?php if ($licensing['tier']) : ?>
                                        <span class="pc-status-tier"><?php echo esc_html(sprintf(__('Tier: %s', 'product-customizer-for-woocommerce'), $licensing['tier'])); ?></span>
                                    <?php endif; ?>
                                </div>
                                <ul class="pc-license-details">
                                    <li><?php printf('%s <strong>%s</strong>', esc_html__('Last checked:', 'product-customizer-for-woocommerce'), esc_html($licensing['last_checked'] ?: __('Not yet', 'product-customizer-for-woocommerce'))); ?></li>
                                    <li><?php printf('%s <strong>%s</strong>', esc_html__('Product limit:', 'product-customizer-for-woocommerce'), esc_html($licensing['product_limit'] === null ? __('Unlimited', 'product-customizer-for-woocommerce') : $licensing['product_limit'])); ?></li>
                                </ul>
                                <?php if (!empty($licensing['last_error'])) : ?>
                                    <p class="pc-license-error"><?php echo esc_html($licensing['last_error']); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="pc-card__body">
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pc-license-form">
                                    <?php wp_nonce_field('pc_save_settings_licensing'); ?>
                                    <input type="hidden" name="action" value="pc_save_settings">
                                    <input type="hidden" name="pc_settings_section" value="licensing">
                                    <label for="pc_license_key" class="pc-field">
                                        <span><?php esc_html_e('License Key', 'product-customizer-for-woocommerce'); ?></span>
                                        <input type="text" id="pc_license_key" name="pc_license_key" value="<?php echo esc_attr($license_key); ?>" class="regular-text" placeholder="OVR-XXXX-XXXX-XXXX-XXXX">
                                    </label>
                                    <div class="pc-license-actions">
                                        <button type="submit" class="button button-primary"><?php esc_html_e('Save & Activate', 'product-customizer-for-woocommerce'); ?></button>
                                        <?php if ($licensing['license_key_present']) : ?>
                                            <a href="<?php echo esc_url($refresh_url); ?>" class="button" role="button"><?php esc_html_e('Refresh Status', 'product-customizer-for-woocommerce'); ?></a>
                                            <a href="<?php echo esc_url($deactivate_url); ?>" class="button button-secondary" role="button"><?php esc_html_e('Deactivate License', 'product-customizer-for-woocommerce'); ?></a>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </section>

                    <section class="pc-admin-panel" id="pc-panel-shortcuts" tabindex="-1">
                        <header>
                            <h2><?php esc_html_e('Shortcuts & Guidance', 'product-customizer-for-woocommerce'); ?></h2>
                            <p><?php esc_html_e('Need a refresher or want to see the onboarding again? Everything is a click away.', 'product-customizer-for-woocommerce'); ?></p>
                        </header>
                        <div class="pc-card pc-card--guides">
                            <div class="pc-card__body">
                                <a class="pc-quick-link" href="<?php echo esc_url($wizard_url); ?>">
                                    <span class="dashicons dashicons-controls-play" aria-hidden="true"></span>
                                    <span>
                                        <strong><?php esc_html_e('Relaunch the Setup Wizard', 'product-customizer-for-woocommerce'); ?></strong>
                                        <small><?php esc_html_e('Revisit the guided tour anytime.', 'product-customizer-for-woocommerce'); ?></small>
                                    </span>
                                </a>
                                <a class="pc-quick-link" href="<?php echo esc_url(admin_url('admin.php?page=wc-settings&tab=product_customizer')); ?>">
                                    <span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
                                    <span>
                                        <strong><?php esc_html_e('Advanced WooCommerce Tab', 'product-customizer-for-woocommerce'); ?></strong>
                                        <small><?php esc_html_e('Legacy settings table, still available when you need it.', 'product-customizer-for-woocommerce'); ?></small>
                                    </span>
                                </a>
                                <a class="pc-quick-link" href="<?php echo esc_url(admin_url('edit.php?post_type=product&page=product_customizer_options')); ?>">
                                    <span class="dashicons dashicons-products" aria-hidden="true"></span>
                                    <span>
                                        <strong><?php esc_html_e('Manage Product Options', 'product-customizer-for-woocommerce'); ?></strong>
                                        <small><?php esc_html_e('Jump right into option groups and templates.', 'product-customizer-for-woocommerce'); ?></small>
                                    </span>
                                </a>
                            </div>
                        </div>
                    </section>
                </main>
            </div>
        </div>
        <?php
    }

    public function handle_form_submission() {
        if (!current_user_can($this->get_required_capability())) {
            wp_die(__('You do not have permission to update these settings.', 'product-customizer-for-woocommerce'));
        }

        $section = isset($_POST['pc_settings_section']) ? sanitize_key($_POST['pc_settings_section']) : '';

        if ($section === '') {
            wp_safe_redirect($this->get_dashboard_url());
            exit;
        }

        check_admin_referer('pc_save_settings_' . $section);

        switch ($section) {
            case 'general':
                $this->save_general_settings();
                $this->set_flash(__('General settings updated.', 'product-customizer-for-woocommerce'));
                break;
            case 'appearance':
                $this->save_appearance_settings();
                $this->set_flash(__('Appearance settings updated.', 'product-customizer-for-woocommerce'));
                break;
            case 'behavior':
                $this->save_behavior_settings();
                $this->set_flash(__('Behavior settings updated.', 'product-customizer-for-woocommerce'));
                break;
            case 'licensing':
                $this->save_licensing_settings();
                $this->set_flash(__('License saved. We are syncing with the licensing server now.', 'product-customizer-for-woocommerce'));
                break;
            default:
                break;
        }

        wp_safe_redirect($this->get_dashboard_url());
        exit;
    }

    private function save_general_settings() {
        $fields = [
            'pc_customize_button_text' => 'sanitize_text_field',
            'pc_modal_title' => 'sanitize_text_field',
            'pc_add_to_cart_button_text' => 'sanitize_text_field',
            'pc_reset_button_text' => 'sanitize_text_field',
            'pc_price_display_format' => [$this, 'sanitize_format_string'],
        ];

        foreach ($fields as $option => $callback) {
            $value = isset($_POST[$option]) ? $_POST[$option] : '';
            $sanitized = is_callable($callback) ? call_user_func($callback, $value) : sanitize_text_field($value);
            update_option($option, $sanitized);
        }
    }

    private function save_appearance_settings() {
        $fields = [
            'pc_modal_layout' => ['card', 'sidebar', 'tabs', 'accordion'],
            'pc_modal_columns' => ['1', '2', '3', '4'],
            'pc_modal_position' => ['center', 'top', 'bottom'],
            'pc_modal_size' => ['small', 'medium', 'large', 'extra-large'],
            'pc_design_preset' => array_keys($this->get_design_presets()),
        ];

        foreach ($fields as $option => $allowed) {
            $value = isset($_POST[$option]) ? sanitize_text_field($_POST[$option]) : '';
            if (!in_array($value, $allowed, true)) {
                $value = $allowed[0];
            }
            update_option($option, $value);
        }
    }

    private function save_behavior_settings() {
        $toggles = [
            'pc_enable_outside_click_close',
            'pc_enable_escape_key_close',
            'pc_auto_focus_first_field',
            'pc_validate_on_change',
        ];

        foreach ($toggles as $option) {
            $value = isset($_POST[$option]) ? 'yes' : 'no';
            update_option($option, $value);
        }
    }

    private function save_licensing_settings() {
        $license_key = isset($_POST['pc_license_key']) ? sanitize_text_field($_POST['pc_license_key']) : '';
        PC_Licensing_Manager::instance()->process_license_key_submission($license_key);
    }

    private function get_general_settings_snapshot() {
        return [
            'customize' => get_option('pc_customize_button_text', 'Customize'),
            'title' => get_option('pc_modal_title', 'Customize Your Product'),
            'add_to_cart' => get_option('pc_add_to_cart_button_text', 'Add to Cart'),
            'reset' => get_option('pc_reset_button_text', 'Reset'),
            'price' => get_option('pc_price_display_format', 'Total: {total_price} (+{additional_price})'),
        ];
    }

    private function get_appearance_settings_snapshot() {
        return [
            'layout' => get_option('pc_modal_layout', 'card'),
            'columns' => (string) get_option('pc_modal_columns', '1'),
            'position' => get_option('pc_modal_position', 'center'),
            'size' => get_option('pc_modal_size', 'medium'),
            'preset' => get_option('pc_design_preset', 'modern-blue'),
        ];
    }

    private function get_behavior_settings_snapshot() {
        return [
            'outside_click' => get_option('pc_enable_outside_click_close', 'yes') === 'yes',
            'escape_key' => get_option('pc_enable_escape_key_close', 'yes') === 'yes',
            'auto_focus' => get_option('pc_auto_focus_first_field', 'yes') === 'yes',
            'validate' => get_option('pc_validate_on_change', 'yes') === 'yes',
        ];
    }

    private function get_design_presets() {
        return [
            'modern-blue' => __('Modern Blue (Recommended)', 'product-customizer-for-woocommerce'),
            'elegant-gray' => __('Elegant Gray', 'product-customizer-for-woocommerce'),
            'vibrant-green' => __('Vibrant Green', 'product-customizer-for-woocommerce'),
            'warm-orange' => __('Warm Orange', 'product-customizer-for-woocommerce'),
            'minimalist' => __('Minimalist Black & White', 'product-customizer-for-woocommerce'),
            'luxury-purple' => __('Luxury Purple', 'product-customizer-for-woocommerce'),
        ];
    }

    private function render_toggle($option, $label, $hint, $is_enabled) {
        ?>
        <label class="pc-toggle">
            <input type="checkbox" name="<?php echo esc_attr($option); ?>" <?php checked($is_enabled); ?>>
            <span class="pc-toggle__slider" aria-hidden="true"></span>
            <span class="pc-toggle__label">
                <strong><?php echo esc_html($label); ?></strong>
                <small><?php echo esc_html($hint); ?></small>
            </span>
        </label>
        <?php
    }

    private function sanitize_format_string($value) {
        $value = wp_strip_all_tags((string) $value);
        return $value === '' ? 'Total: {total_price} (+{additional_price})' : $value;
    }

    private function set_flash($message, $type = 'success') {
        set_transient(self::NOTICE_TRANSIENT, [
            'message' => $message,
            'type' => $type === 'error' ? 'error' : 'success',
        ], 45);
    }

    private function get_dashboard_url() {
        return admin_url('admin.php?page=' . self::MENU_SLUG);
    }

    private function get_required_capability() {
        return class_exists('WooCommerce') ? 'manage_woocommerce' : 'manage_options';
    }
}
