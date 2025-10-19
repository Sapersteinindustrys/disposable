<?php
if (!defined('ABSPATH')) {
    exit;
}

class PC_Admin_Dashboard {
    private const MENU_SLUG = 'pc-control-center';
    private const NOTICE_TRANSIENT = 'pc_admin_settings_notice';

    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_post_pc_save_settings', [$this, 'handle_form_submission']);
        add_action('admin_post_pc_delete_library_entry', [$this, 'handle_delete_library_entry']);
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

        add_submenu_page(
            'woocommerce',
            __('Product Customizer', 'product-customizer-for-woocommerce'),
            __('Product Customizer', 'product-customizer-for-woocommerce'),
            $this->get_required_capability(),
            self::MENU_SLUG,
            [$this, 'render_page']
        );
    }

    public function enqueue_assets($hook) {
        $allowed_hooks = [
            'toplevel_page_' . self::MENU_SLUG,
            'woocommerce_page_' . self::MENU_SLUG,
        ];

        if (!in_array($hook, $allowed_hooks, true)) {
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

        wp_enqueue_script(
            'pc-admin-library',
            PC_PLUGIN_URL . 'src/assets/js/admin-library.js',
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

        $library_manager = PC_Library_Manager::instance();
        $library_items = $library_manager->get_items();
        $library_edit_id = isset($_GET['pc_library_edit']) ? absint($_GET['pc_library_edit']) : 0;
        $library_entry = $library_edit_id ? $library_manager->get_item($library_edit_id) : null;
        $library_defaults = [
            'id' => 0,
            'option_name' => '',
            'option_label' => '',
            'option_type' => 'text',
            'is_required' => false,
            'help_text' => '',
            'choices' => [],
            'product_name' => '',
            'product_variation' => '',
        ];

        if (!is_array($library_entry)) {
            $library_entry = $library_defaults;
        } else {
            $library_entry = array_merge($library_defaults, $library_entry);
        }

        $panel_param = isset($_GET['pc_panel']) ? sanitize_key($_GET['pc_panel']) : '';
        $valid_panels = ['general', 'appearance', 'behavior', 'library', 'licensing', 'shortcuts'];
        if ($panel_param && in_array($panel_param, $valid_panels, true)) {
            $active_panel = 'pc-panel-' . $panel_param;
        } elseif ($library_entry['id'] > 0) {
            $active_panel = 'pc-panel-library';
        } else {
            $active_panel = 'pc-panel-general';
        }
        ?>
    <div class="wrap pc-admin-dashboard" data-pc-active-panel="<?php echo esc_attr($active_panel); ?>">
            <h1><?php esc_html_e('Product Customizer Control Center', 'product-customizer-for-woocommerce'); ?></h1>
            <p class="pc-admin-subtitle"><?php esc_html_e('Your launchpad for keeping customization polished, on-brand, and stress-free.', 'product-customizer-for-woocommerce'); ?></p>

            <?php if ($flash) : ?>
                <div class="notice notice-<?php echo esc_attr($flash['type']); ?> is-dismissible">
                    <p><?php echo esc_html($flash['message']); ?></p>
                </div>
            <?php endif; ?>

            <div class="pc-admin-layout">
                <nav class="pc-admin-nav" aria-label="<?php esc_attr_e('Settings sections', 'product-customizer-for-woocommerce'); ?>">
                    <button type="button" class="pc-admin-nav__link <?php echo $active_panel === 'pc-panel-general' ? 'is-active' : ''; ?>" data-target="pc-panel-general">
                        <span class="dashicons dashicons-controls-repeat" aria-hidden="true"></span>
                        <?php esc_html_e('General', 'product-customizer-for-woocommerce'); ?>
                    </button>
                    <button type="button" class="pc-admin-nav__link <?php echo $active_panel === 'pc-panel-appearance' ? 'is-active' : ''; ?>" data-target="pc-panel-appearance">
                        <span class="dashicons dashicons-art" aria-hidden="true"></span>
                        <?php esc_html_e('Appearance', 'product-customizer-for-woocommerce'); ?>
                    </button>
                    <button type="button" class="pc-admin-nav__link <?php echo $active_panel === 'pc-panel-behavior' ? 'is-active' : ''; ?>" data-target="pc-panel-behavior">
                        <span class="dashicons dashicons-admin-settings" aria-hidden="true"></span>
                        <?php esc_html_e('Behavior', 'product-customizer-for-woocommerce'); ?>
                    </button>
                    <button type="button" class="pc-admin-nav__link <?php echo $active_panel === 'pc-panel-library' ? 'is-active' : ''; ?>" data-target="pc-panel-library">
                        <span class="dashicons dashicons-portfolio" aria-hidden="true"></span>
                        <?php esc_html_e('Library', 'product-customizer-for-woocommerce'); ?>
                    </button>
                    <button type="button" class="pc-admin-nav__link <?php echo $active_panel === 'pc-panel-licensing' ? 'is-active' : ''; ?>" data-target="pc-panel-licensing">
                        <span class="dashicons dashicons-lock" aria-hidden="true"></span>
                        <?php esc_html_e('Licensing', 'product-customizer-for-woocommerce'); ?>
                    </button>
                    <button type="button" class="pc-admin-nav__link <?php echo $active_panel === 'pc-panel-shortcuts' ? 'is-active' : ''; ?>" data-target="pc-panel-shortcuts">
                        <span class="dashicons dashicons-lightbulb" aria-hidden="true"></span>
                        <?php esc_html_e('Shortcuts', 'product-customizer-for-woocommerce'); ?>
                    </button>
                </nav>

                <main class="pc-admin-panels" id="pc-admin-panels">
                    <section class="pc-admin-panel <?php echo $active_panel === 'pc-panel-general' ? 'is-active' : ''; ?>" id="pc-panel-general" tabindex="-1">
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

                    <section class="pc-admin-panel <?php echo $active_panel === 'pc-panel-appearance' ? 'is-active' : ''; ?>" id="pc-panel-appearance" tabindex="-1">
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

                    <section class="pc-admin-panel <?php echo $active_panel === 'pc-panel-behavior' ? 'is-active' : ''; ?>" id="pc-panel-behavior" tabindex="-1">
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

                    <section class="pc-admin-panel <?php echo $active_panel === 'pc-panel-library' ? 'is-active' : ''; ?>" id="pc-panel-library" tabindex="-1">
                        <header>
                            <h2><?php esc_html_e('Reusable Option Library', 'product-customizer-for-woocommerce'); ?></h2>
                            <p><?php esc_html_e('Curate ready-made options, note the product and variation they came from, and reuse them across your catalog in seconds.', 'product-customizer-for-woocommerce'); ?></p>
                        </header>
                        <div class="pc-library-layout">
                            <div class="pc-card pc-library-card">
                                <div class="pc-card__body pc-library-list">
                                    <?php if (empty($library_items)) : ?>
                                        <p class="pc-library-empty"><?php esc_html_e('No saved options yet. Use the form to add your first library entry.', 'product-customizer-for-woocommerce'); ?></p>
                                    <?php else : ?>
                                        <table class="pc-library-table">
                                            <thead>
                                                <tr>
                                                    <th><?php esc_html_e('Option Label', 'product-customizer-for-woocommerce'); ?></th>
                                                    <th><?php esc_html_e('Type', 'product-customizer-for-woocommerce'); ?></th>
                                                    <th><?php esc_html_e('Source Product', 'product-customizer-for-woocommerce'); ?></th>
                                                    <th><?php esc_html_e('Variation Details', 'product-customizer-for-woocommerce'); ?></th>
                                                    <th><?php esc_html_e('Updated', 'product-customizer-for-woocommerce'); ?></th>
                                                    <th class="pc-library-actions-col"><?php esc_html_e('Actions', 'product-customizer-for-woocommerce'); ?></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($library_items as $item) : ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?php echo esc_html($item['option_label']); ?></strong>
                                                            <div class="pc-library-option-name">/<?php echo esc_html($item['option_name']); ?>/</div>
                                                        </td>
                                                        <td><?php echo esc_html(ucfirst($item['option_type'])); ?></td>
                                                        <td><?php echo $item['product_name'] !== '' ? esc_html($item['product_name']) : '&#8212;'; ?></td>
                                                        <td><?php echo $item['product_variation'] !== '' ? esc_html($item['product_variation']) : '&#8212;'; ?></td>
                                                        <td><?php echo esc_html($this->format_datetime($item['updated_at'])); ?></td>
                                                        <td class="pc-library-actions">
                                                            <a class="pc-library-action" href="<?php echo esc_url(add_query_arg(['pc_panel' => 'library', 'pc_library_edit' => $item['id']], $this->get_dashboard_url())); ?>"><?php esc_html_e('Edit', 'product-customizer-for-woocommerce'); ?></a>
                                                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pc-library-inline-form" onsubmit="return confirm('<?php echo esc_js(__('Delete this library entry?', 'product-customizer-for-woocommerce')); ?>');">
                                                                <?php wp_nonce_field('pc_delete_library_' . $item['id']); ?>
                                                                <input type="hidden" name="action" value="pc_delete_library_entry">
                                                                <input type="hidden" name="pc_library_entry_id" value="<?php echo esc_attr($item['id']); ?>">
                                                                <button type="submit" class="button-link delete pc-library-delete"><?php esc_html_e('Delete', 'product-customizer-for-woocommerce'); ?></button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pc-card pc-library-form">
                                <?php wp_nonce_field('pc_save_settings_library'); ?>
                                <input type="hidden" name="action" value="pc_save_settings">
                                <input type="hidden" name="pc_settings_section" value="library">
                                <input type="hidden" name="pc_library_entry_id" value="<?php echo esc_attr($library_entry['id']); ?>">

                                <div class="pc-card__body pc-library-form__body">
                                    <div class="pc-field">
                                        <label for="pc_library_option_label"><?php esc_html_e('Option Label', 'product-customizer-for-woocommerce'); ?></label>
                                        <input type="text" id="pc_library_option_label" name="pc_library_option_label" class="regular-text" value="<?php echo esc_attr($library_entry['option_label']); ?>" required>
                                        <p class="description"><?php esc_html_e('Customer-facing name of this option.', 'product-customizer-for-woocommerce'); ?></p>
                                    </div>
                                    <div class="pc-field">
                                        <label for="pc_library_option_name"><?php esc_html_e('Option Name (slug)', 'product-customizer-for-woocommerce'); ?></label>
                                        <input type="text" id="pc_library_option_name" name="pc_library_option_name" class="regular-text" value="<?php echo esc_attr($library_entry['option_name']); ?>" placeholder="engraving_text" required>
                                        <p class="description"><?php esc_html_e('Used internally. Lowercase letters, numbers, dashes, or underscores.', 'product-customizer-for-woocommerce'); ?></p>
                                    </div>
                                    <div class="pc-field">
                                        <label for="pc_library_option_type"><?php esc_html_e('Option Type', 'product-customizer-for-woocommerce'); ?></label>
                                        <select id="pc_library_option_type" name="pc_library_option_type" class="pc-library-type-select">
                                            <option value="text" <?php selected($library_entry['option_type'], 'text'); ?>><?php esc_html_e('Text Input', 'product-customizer-for-woocommerce'); ?></option>
                                            <option value="number" <?php selected($library_entry['option_type'], 'number'); ?>><?php esc_html_e('Number Input', 'product-customizer-for-woocommerce'); ?></option>
                                            <option value="dropdown" <?php selected($library_entry['option_type'], 'dropdown'); ?>><?php esc_html_e('Dropdown Select', 'product-customizer-for-woocommerce'); ?></option>
                                            <option value="radio" <?php selected($library_entry['option_type'], 'radio'); ?>><?php esc_html_e('Radio Buttons', 'product-customizer-for-woocommerce'); ?></option>
                                            <option value="checkbox" <?php selected($library_entry['option_type'], 'checkbox'); ?>><?php esc_html_e('Checkboxes', 'product-customizer-for-woocommerce'); ?></option>
                                            <option value="swatch" <?php selected($library_entry['option_type'], 'swatch'); ?>><?php esc_html_e('Image Swatches', 'product-customizer-for-woocommerce'); ?></option>
                                        </select>
                                    </div>
                                    <div class="pc-field pc-field--inline">
                                        <label for="pc_library_product_name"><?php esc_html_e('Source Product Name', 'product-customizer-for-woocommerce'); ?></label>
                                        <input type="text" id="pc_library_product_name" name="pc_library_product_name" class="regular-text" value="<?php echo esc_attr($library_entry['product_name']); ?>" placeholder="<?php esc_attr_e('Product or template reference', 'product-customizer-for-woocommerce'); ?>">
                                    </div>
                                    <div class="pc-field pc-field--inline">
                                        <label for="pc_library_product_variation"><?php esc_html_e('Variation Details', 'product-customizer-for-woocommerce'); ?></label>
                                        <input type="text" id="pc_library_product_variation" name="pc_library_product_variation" class="regular-text" value="<?php echo esc_attr($library_entry['product_variation']); ?>" placeholder="<?php esc_attr_e('Color / Size / SKU reference', 'product-customizer-for-woocommerce'); ?>">
                                    </div>
                                    <div class="pc-field pc-field--toggle">
                                        <label>
                                            <input type="checkbox" name="pc_library_is_required" value="1" <?php checked($library_entry['is_required']); ?>>
                                            <span><?php esc_html_e('Required option', 'product-customizer-for-woocommerce'); ?></span>
                                        </label>
                                    </div>
                                    <div class="pc-field">
                                        <label for="pc_library_help_text"><?php esc_html_e('Help Text', 'product-customizer-for-woocommerce'); ?></label>
                                        <textarea id="pc_library_help_text" name="pc_library_help_text" rows="3" class="large-text" placeholder="<?php esc_attr_e('Short guidance shown beneath the field.', 'product-customizer-for-woocommerce'); ?>"><?php echo esc_textarea($library_entry['help_text']); ?></textarea>
                                    </div>

                                    <?php $library_show_choices = in_array($library_entry['option_type'], ['dropdown', 'radio', 'checkbox', 'swatch'], true); ?>
                                    <div class="pc-library-choices" data-show-choice-types="dropdown,radio,checkbox,swatch" style="<?php echo $library_show_choices ? '' : 'display:none;'; ?>">
                                        <h3><?php esc_html_e('Choices', 'product-customizer-for-woocommerce'); ?></h3>
                                        <div class="pc-library-choices__items" data-next-index="<?php echo esc_attr($this->calculate_next_choice_index($library_entry['choices'])); ?>">
                                            <?php echo $this->render_library_choice_fields($library_entry['choices']); ?>
                                        </div>
                                        <button type="button" class="button button-secondary pc-library-add-choice"><?php esc_html_e('Add Choice', 'product-customizer-for-woocommerce'); ?></button>
                                        <p class="description"><?php esc_html_e('Choices appear for dropdowns, radios, checkboxes, and swatches. Include at least one label.', 'product-customizer-for-woocommerce'); ?></p>
                                    </div>
                                </div>

                                <footer class="pc-card__footer">
                                    <button type="submit" class="button button-primary">
                                        <?php echo $library_entry['id'] ? esc_html__('Update Library Entry', 'product-customizer-for-woocommerce') : esc_html__('Add to Library', 'product-customizer-for-woocommerce'); ?>
                                    </button>
                                    <?php if ($library_entry['id']) : ?>
                                        <a href="<?php echo esc_url(add_query_arg(['pc_panel' => 'library'], $this->get_dashboard_url())); ?>" class="button button-link"><?php esc_html_e('Cancel', 'product-customizer-for-woocommerce'); ?></a>
                                    <?php endif; ?>
                                </footer>

                                <template id="pc-library-choice-template">
                                    <?php echo $this->render_library_choice_row('__INDEX__', ['label' => '', 'value' => '', 'price' => '', 'default' => false, 'image_url' => '']); ?>
                                </template>
                            </form>
                        </div>
                    </section>

                    <section class="pc-admin-panel <?php echo $active_panel === 'pc-panel-licensing' ? 'is-active' : ''; ?>" id="pc-panel-licensing" tabindex="-1">
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

                    <section class="pc-admin-panel <?php echo $active_panel === 'pc-panel-shortcuts' ? 'is-active' : ''; ?>" id="pc-panel-shortcuts" tabindex="-1">
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
    $redirect = $this->get_dashboard_url();

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
            case 'library':
                $library_result = $this->save_library_settings();
                $this->set_flash($library_result['message'], $library_result['success'] ? 'success' : 'error');
                $redirect = add_query_arg('pc_panel', 'library', $this->get_dashboard_url());
                if (!$library_result['success'] && !empty($library_result['entry_id'])) {
                    $redirect = add_query_arg('pc_library_edit', (int) $library_result['entry_id'], $redirect);
                }
                break;
            case 'licensing':
                $this->save_licensing_settings();
                $this->set_flash(__('License saved. We are syncing with the licensing server now.', 'product-customizer-for-woocommerce'));
                break;
            default:
                break;
        }

        wp_safe_redirect($redirect);
        exit;
    }

    public function handle_delete_library_entry() {
        if (!current_user_can($this->get_required_capability())) {
            wp_die(__('You do not have permission to update these settings.', 'product-customizer-for-woocommerce'));
        }

        $entry_id = isset($_POST['pc_library_entry_id']) ? absint($_POST['pc_library_entry_id']) : 0;
        if ($entry_id <= 0) {
            wp_safe_redirect(add_query_arg('pc_panel', 'library', $this->get_dashboard_url()));
            exit;
        }

        check_admin_referer('pc_delete_library_' . $entry_id);

        $deleted = PC_Library_Manager::instance()->delete_item($entry_id);
        $this->set_flash(
            $deleted ? __('Library entry deleted.', 'product-customizer-for-woocommerce') : __('Could not delete the library entry. Please try again.', 'product-customizer-for-woocommerce'),
            $deleted ? 'success' : 'error'
        );

        wp_safe_redirect(add_query_arg('pc_panel', 'library', $this->get_dashboard_url()));
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

    private function save_library_settings() {
        $entry_id = isset($_POST['pc_library_entry_id']) ? absint($_POST['pc_library_entry_id']) : 0;
        $option_label = isset($_POST['pc_library_option_label']) ? sanitize_text_field($_POST['pc_library_option_label']) : '';
        $option_name = isset($_POST['pc_library_option_name']) ? sanitize_text_field($_POST['pc_library_option_name']) : '';
    $option_type = isset($_POST['pc_library_option_type']) ? sanitize_key($_POST['pc_library_option_type']) : 'text';
        $product_name = isset($_POST['pc_library_product_name']) ? sanitize_text_field($_POST['pc_library_product_name']) : '';
        $product_variation = isset($_POST['pc_library_product_variation']) ? sanitize_text_field($_POST['pc_library_product_variation']) : '';
        $is_required = !empty($_POST['pc_library_is_required']);
        $help_text = isset($_POST['pc_library_help_text']) ? sanitize_textarea_field($_POST['pc_library_help_text']) : '';
        $raw_choices = isset($_POST['pc_library_choices']) && is_array($_POST['pc_library_choices']) ? $_POST['pc_library_choices'] : [];

        if ($option_label === '' || $option_name === '') {
            return [
                'success' => false,
                'message' => __('Option label and name are required to save a library entry.', 'product-customizer-for-woocommerce'),
                'entry_id' => $entry_id,
            ];
        }

        $allowed_types = ['text', 'number', 'dropdown', 'radio', 'checkbox', 'swatch'];
        if (!in_array($option_type, $allowed_types, true)) {
            $option_type = 'text';
        }
        $choices = $this->sanitize_library_choices($raw_choices, $option_type);

        if ($this->option_type_requires_choices($option_type) && empty($choices)) {
            return [
                'success' => false,
                'message' => __('Please add at least one choice for this option type.', 'product-customizer-for-woocommerce'),
                'entry_id' => $entry_id,
            ];
        }

        $data = [
            'id' => $entry_id,
            'option_label' => $option_label,
            'option_name' => $option_name,
            'option_type' => $option_type,
            'is_required' => $is_required,
            'help_text' => $help_text,
            'choices' => $choices,
            'product_name' => $product_name,
            'product_variation' => $product_variation,
        ];

        $result = PC_Library_Manager::instance()->save_item($data);

        if (!$result) {
            return [
                'success' => false,
                'message' => __('We could not save this library entry. Please try again.', 'product-customizer-for-woocommerce'),
                'entry_id' => $entry_id,
            ];
        }

        return [
            'success' => true,
            'message' => $entry_id ? __('Library entry updated.', 'product-customizer-for-woocommerce') : __('Library entry added.', 'product-customizer-for-woocommerce'),
            'entry_id' => (int) $result,
        ];
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

    private function render_library_choice_fields($choices) {
        if (empty($choices) || !is_array($choices)) {
            $choices = [['label' => '', 'value' => '', 'price' => '', 'default' => false, 'image_url' => '']];
        }

        $output = '';
        $iterator = 0;
        foreach ($choices as $index => $choice) {
            $normalized_index = is_numeric($index) ? (int) $index : $iterator;
            $output .= $this->render_library_choice_row($normalized_index, $choice);
            $iterator++;
        }

        return $output;
    }

    private function render_library_choice_row($index, $choice) {
        $label = isset($choice['label']) ? $choice['label'] : '';
        $value = isset($choice['value']) ? $choice['value'] : '';
        $price = isset($choice['price']) ? $choice['price'] : '';
        $default = !empty($choice['default']);
        $image_url = isset($choice['image_url']) ? $choice['image_url'] : '';

        ob_start();
        ?>
        <div class="pc-library-choice" data-index="<?php echo esc_attr($index); ?>">
            <div class="pc-library-choice__grid">
                <div class="pc-library-field">
                    <label><?php esc_html_e('Label', 'product-customizer-for-woocommerce'); ?></label>
                    <input type="text" name="pc_library_choices[<?php echo esc_attr($index); ?>][label]" value="<?php echo esc_attr($label); ?>" placeholder="<?php esc_attr_e('Visible text', 'product-customizer-for-woocommerce'); ?>">
                </div>
                <div class="pc-library-field">
                    <label><?php esc_html_e('Value', 'product-customizer-for-woocommerce'); ?></label>
                    <input type="text" name="pc_library_choices[<?php echo esc_attr($index); ?>][value]" value="<?php echo esc_attr($value); ?>" placeholder="<?php esc_attr_e('Stored value (optional)', 'product-customizer-for-woocommerce'); ?>">
                </div>
                <div class="pc-library-field pc-library-field--price">
                    <label><?php esc_html_e('Price Adjustment', 'product-customizer-for-woocommerce'); ?></label>
                    <input type="number" name="pc_library_choices[<?php echo esc_attr($index); ?>][price]" value="<?php echo esc_attr($price); ?>" step="0.01" placeholder="0.00">
                </div>
                <div class="pc-library-field pc-library-field--checkbox">
                    <label>
                        <input type="checkbox" name="pc_library_choices[<?php echo esc_attr($index); ?>][default]" value="1" <?php checked($default); ?>>
                        <span><?php esc_html_e('Default choice', 'product-customizer-for-woocommerce'); ?></span>
                    </label>
                </div>
                <div class="pc-library-field pc-library-field--image">
                    <label><?php esc_html_e('Image URL', 'product-customizer-for-woocommerce'); ?></label>
                    <input type="text" name="pc_library_choices[<?php echo esc_attr($index); ?>][image_url]" value="<?php echo esc_attr($image_url); ?>" placeholder="https://...">
                </div>
                <div class="pc-library-field pc-library-field--actions">
                    <button type="button" class="button-link pc-library-remove-choice"><?php esc_html_e('Remove', 'product-customizer-for-woocommerce'); ?></button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function calculate_next_choice_index($choices) {
        if (empty($choices) || !is_array($choices)) {
            return 1;
        }

        $max = -1;
        foreach ($choices as $index => $choice) {
            if (is_numeric($index)) {
                $max = max($max, (int) $index);
            }
        }

        return $max + 1;
    }

    private function sanitize_library_choices($choices, $option_type) {
        if (empty($choices) || !is_array($choices)) {
            return [];
        }

        $sanitized = [];
        foreach ($choices as $choice) {
            $label = isset($choice['label']) ? sanitize_text_field($choice['label']) : '';
            $value = isset($choice['value']) ? sanitize_text_field($choice['value']) : '';
            $price_raw = isset($choice['price']) ? (string) $choice['price'] : '';
            $price = $price_raw === '' ? '' : (float) $price_raw;
            $default = !empty($choice['default']);
            $image_url = isset($choice['image_url']) ? esc_url_raw($choice['image_url']) : '';

            if ($label === '' && $value === '') {
                continue;
            }

            $sanitized[] = [
                'label' => $label,
                'value' => $value,
                'price' => $price,
                'default' => $default,
                'image_url' => $image_url,
            ];
        }

        if (empty($sanitized)) {
            return [];
        }

        if ($option_type !== 'swatch') {
            foreach ($sanitized as &$choice) {
                $choice['image_url'] = '';
            }
            unset($choice);
        }

        if (in_array($option_type, ['dropdown', 'radio', 'swatch'], true)) {
            $default_found = false;
            foreach ($sanitized as $index => $choice) {
                if ($choice['default']) {
                    if ($default_found) {
                        $sanitized[$index]['default'] = false;
                    } else {
                        $default_found = true;
                    }
                }
            }

            if (!$default_found && !empty($sanitized)) {
                $sanitized[0]['default'] = true;
            }
        }

        return $sanitized;
    }

    private function option_type_requires_choices($option_type) {
        return in_array($option_type, ['dropdown', 'radio', 'checkbox', 'swatch'], true);
    }

    private function format_datetime($datetime) {
        if (empty($datetime)) {
            return '';
        }

        $format = get_option('date_format') . ' ' . get_option('time_format');
        return mysql2date($format, $datetime, true);
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
        return current_user_can('manage_woocommerce') ? 'manage_woocommerce' : 'manage_options';
    }
}
