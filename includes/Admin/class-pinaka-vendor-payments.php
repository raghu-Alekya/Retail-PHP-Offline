<?php
/**
 * The admin vendor payments functionality of the plugin.
 *
 * @link       https://www.pinaka.com/
 * @since      1.0.0
 *
 * @package    Pinaka_Pos
 * @subpackage Pinaka_Pos/admin
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class Pinaka_Vendor_Payments {
    private $plugin_name;
    private $version;
    private $loader;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->loader = new Pinaka_POS_Loader();
        $this->define_admin_hooks();

        add_action('add_meta_boxes', [$this, 'add_vendor_payment_meta_boxes']);
        add_action('save_post', [$this, 'save_vendor_payment_meta']);
        add_filter('manage_vendor_payments_posts_columns', [$this, 'set_custom_columns']);
        add_action('manage_vendor_payments_posts_custom_column', [$this, 'custom_column_content'], 10, 2);
    }

    public function register_vendor_payments_post_type() {
        $labels = array(
            'name'                  => __('Vendor Payments', 'pinaka-pos'),
            'singular_name'         => __('Vendor Payment', 'pinaka-pos'),
            'add_new'               => __('Add New Vendor Payment', 'pinaka-pos'),
            'add_new_item'          => __('Add New Vendor Payment', 'pinaka-pos'),
            'edit_item'             => __('Edit Vendor Payment', 'pinaka-pos'),
            'new_item'              => __('New Vendor Payment', 'pinaka-pos'),
            'view_item'             => __('View Vendor Payment', 'pinaka-pos'),
            'search_items'          => __('Search Vendor Payments', 'pinaka-pos'),
            'not_found'             => __('No vendor payments found', 'pinaka-pos'),
            'not_found_in_trash'    => __('No vendor payments found in Trash', 'pinaka-pos'),
            'menu_name'             => __('Vendor Payments', 'pinaka-pos'),
        );

        $args = array(
            'labels'        => $labels,
            'description'   => __('Vendor Payments custom post type', 'pinaka-pos'),
            'public'        => true,
            'publicly_queryable' => true,
            'show_ui'       => true,
            'show_in_menu'  => false,
            'query_var'     => true,
            'rewrite'       => array('slug' => 'vendor-payments'),
            'capability_type' => 'post',
            'has_archive'   => true,
            'hierarchical'  => false,
            'menu_position' => null,
            'supports'      => array('title', 'editor'),
        );

        register_post_type('vendor_payments', $args);
    }

    private function define_admin_hooks() {
        $this->loader->add_action('init', $this, 'register_vendor_payments_post_type');
    }

    public function set_custom_columns($columns) {
        $columns['vendor_name'] = __('Vendor Name', 'pinaka-pos');
        $columns['payment_purpose'] = __('Payment Purpose', 'pinaka-pos');
        $columns['payment_method'] = __('Payment Method', 'pinaka-pos');
        $columns['payment_amount'] = __('Payment Amount', 'pinaka-pos');
        return $columns;
    }

    public function custom_column_content($column, $post_id) {
        switch ($column) {
            case 'vendor_name':
                $vendor_id = get_post_meta($post_id, '_vendor_id', true);
                $vendor = get_post($vendor_id);
                echo $vendor ? esc_html($vendor->post_title) : __('N/A', 'pinaka-pos');
                break;
            case 'payment_purpose':
                echo esc_html(get_post_meta($post_id, '_vendor_payment_service_type', true));
                break;
            case 'payment_amount':
                echo esc_html(get_post_meta($post_id, '_vendor_payment_amount', true));
                break;
            case 'payment_method':
                echo esc_html(get_post_meta($post_id, '_vendor_payment_method', true));
                break;
        }
    }

    public function add_vendor_payment_meta_boxes() {
        add_meta_box(
            'vendor_payment_details',
            'Vendor Payment Details',
            [$this, 'render_vendor_payment_details_meta_box'],
            'vendor_payments',
            'normal',
            'high'
        );
    }

    public function render_vendor_payment_details_meta_box($post) {
        $vendor_id = get_post_meta($post->ID, '_vendor_id', true);
        $amount = get_post_meta($post->ID, '_vendor_payment_amount', true);
        $payment_method = get_post_meta($post->ID, '_vendor_payment_method', true);
        $datetime = get_post_meta($post->ID, '_vendor_payment_datetime', true);
        $notes = get_post_meta($post->ID, '_vendor_payment_notes', true);
        $purpose = get_post_meta($post->ID, '_vendor_payment_service_type', true);
        // Fetch Vendors
        $vendors = get_posts([
            'post_type'   => 'vendor',
            'post_status' => 'publish',
            'numberposts' => -1
        ]);
        ?>
        <p>
            <label for="vendor_payment_vendor_id">Vendor:</label><br>
            <select id="vendor_payment_vendor_id" name="vendor_payment_vendor_id">
                <option value="">Select Vendor</option>
                <?php foreach ($vendors as $vendor) : ?>
                    <option value="<?php echo $vendor->ID; ?>" <?php selected($vendor_id, $vendor->ID); ?>>
                        <?php echo esc_html($vendor->post_title); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <label for="vendor_payment_purpose">Payment Method:</label><br>
            <select id="vendor_payment_purpose" name="vendor_payment_purpose">
                <option value=""></option>
                <option value="Expenses" <?php selected($purpose, 'Expenses'); ?>>Expenses</option>
                <option value="Purchase" <?php selected($purpose, 'Purchase'); ?>>Purchase</option>
            </select>
        </p>
        <p>
            <label for="vendor_payment_amount">Amount:</label><br>
            <input type="number" id="vendor_payment_amount" name="vendor_payment_amount" value="<?php echo esc_attr($amount); ?>" style="width:100%;">
        </p>
        <p>
            <label for="vendor_payment_method">Payment Method:</label><br>
            <select id="vendor_payment_method" name="vendor_payment_method">
                <option value=""></option>
                <option value="Cash" <?php selected($payment_method, 'Cash'); ?>>Cash</option>
                <option value="Card" <?php selected($payment_method, 'Card'); ?>>Card</option>
                <option value="Cheque" <?php selected($payment_method, 'Cheque'); ?>>Cheque</option>
                <option value="Online" <?php selected($payment_method, 'Online'); ?>>Online</option>
                <option value="Other" <?php selected($payment_method, 'Other'); ?>>Other</option>
            </select>
        </p>
        <p>
            <label for="vendor_payment_notes">Notes:</label><br>
            <textarea id="vendor_payment_notes" name="vendor_payment_notes" style="width:100%;"><?php echo esc_textarea($notes); ?></textarea>
        </p>
        <?php
    }

    public function save_vendor_payment_meta($post_id) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        if (isset($_POST['vendor_payment_vendor_id'])) {
            update_post_meta($post_id, '_vendor_id', sanitize_text_field($_POST['vendor_payment_vendor_id']));
        }

        if (isset($_POST['vendor_payment_amount'])) {
            update_post_meta($post_id, '_vendor_payment_amount', floatval($_POST['vendor_payment_amount']));
        }

        if (isset($_POST['vendor_payment_method'])) {
            update_post_meta($post_id, '_vendor_payment_method', sanitize_text_field($_POST['vendor_payment_method']));
        }
        if (isset($_POST['vendor_payment_purpose'])) {
            update_post_meta($post_id, '_vendor_payment_service_type', sanitize_text_field($_POST['vendor_payment_purpose']));
        }
        if (isset($_POST['vendor_payment_notes'])) {
            update_post_meta($post_id, '_vendor_payment_notes', sanitize_textarea_field($_POST['vendor_payment_notes']));
        }
    }
}
