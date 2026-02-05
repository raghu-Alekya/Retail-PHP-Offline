<?php 
/**
 * The admin-vendor functionality of the plugin.
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

class Pinaka_POS_Vendor {

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param    string    $name        The name of this plugin.
     * @param    string    $version    The version of this plugin.
     */

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    private $loader;
    
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->loader = new Pinaka_POS_Loader();
        $this->define_admin_hooks();

        add_action('add_meta_boxes', [$this, 'add_vendor_meta_boxes']);
        add_action('save_post', [$this, 'save_vendor_meta']);
    }

    /**
     * Register the vendor custom post type.
     *
     * @since    1.0.0
     * @access   private
     */
    public function register_vendor_post_type() {
        $labels = array(
            'name'                  => __('Vendors', 'pinaka-pos'),
            'singular_name'         => __('Vendor', 'pinaka-pos'),
            'add_new'               => __('Add New Vendor', 'pinaka-pos'),
            'add_new_item'          => __('Add New Vendor', 'pinaka-pos'),
            'edit_item'             => __('Edit Vendor', 'pinaka-pos'),
            'new_item'              => __('New Vendor', 'pinaka-pos'),
            'view_item'             => __('View Vendor', 'pinaka-pos'),
            'search_items'          => __('Search Vendors', 'pinaka-pos'),
            'not_found'             => __('No vendors found', 'pinaka-pos'),
            'not_found_in_trash'    => __('No vendors found in Trash', 'pinaka-pos'),
            'menu_name'             => __('Vendors', 'pinaka-pos'),
        );

        $args = array(
            'labels'        => $labels,
            'description'   => __('Vendor custom post type', 'pinaka-pos'),
            'public'        => true,
            'publicly_queryable' => true,
            'show_ui'       => true,
            'show_in_menu'  => false, // Hide from top menu, shown under Pinaka POS
            'query_var'     => true,
            'rewrite'       => array('slug' => 'vendor'),
            'capability_type' => 'post',
            'has_archive'   => true,
            'hierarchical'  => false,
            'menu_position' => null,
            'supports'      => array('title', 'editor', 'thumbnail'),
        );

        register_post_type('vendor', $args);
    }

    /**
     * Define the admin hooks.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_admin_hooks() {
        // ✅ Ensure post type is registered before menu is built
        $this->loader->add_action('init', $this, 'register_vendor_post_type');
        $this->loader->add_action('admin_menu', $this, 'add_vendor_submenu_page');
    }

    /**
     * Add the vendor submenu page.
     *
     * @since    1.0.0
     * @access   private
     */
    public function add_vendor_submenu_page() {
        add_submenu_page(
            'pinaka-pos-dashboard',
            __('Manage Vendors', 'pinaka-pos'),
            __('Manage Vendors', 'pinaka-pos'),
            'manage_options',
            'pinaka-pos-vendors',
            [$this, 'vendorRender']
        );
    }

    /**
     * Render the vendor submenu page.
     *
     * @since    1.0.0
     * @access   public
     */
    public function vendorRender() {
        wp_safe_redirect(admin_url('edit.php?post_type=vendor'));
        exit;
    }

    /**
 * Add meta boxes to Vendor post type
 */
public function add_vendor_meta_boxes() {
    add_meta_box(
        'vendor_contact_info',
        'Vendor Contact Information',
        [$this, 'render_vendor_contact_meta_box'],
        'vendor',
        'normal',
        'high'
    );

    add_meta_box(
        'vendor_linked_payments',
        'Linked Payments',
        [$this, 'render_vendor_payments_meta_box'],
        'vendor',
        'normal',
        'high'
    );
}

    /**
     * Render Vendor Contact Information Meta Box
     */
    public function render_vendor_contact_meta_box($post) {
        $phone = get_post_meta($post->ID, '_vendor_phone', true);
        $email = get_post_meta($post->ID, '_vendor_email', true);
        $address = get_post_meta($post->ID, '_vendor_address', true);
        ?>
        <p>
            <label for="vendor_phone">Phone:</label><br>
            <input type="text" id="vendor_phone" name="vendor_phone" value="<?php echo esc_attr($phone); ?>" style="width:100%;">
        </p>
        <p>
            <label for="vendor_email">Email:</label><br>
            <input type="email" id="vendor_email" name="vendor_email" value="<?php echo esc_attr($email); ?>" style="width:100%;">
        </p>
        <p>
            <label for="vendor_address">Address:</label><br>
            <textarea id="vendor_address" name="vendor_address" style="width:100%;"><?php echo esc_textarea($address); ?></textarea>
        </p>
        <?php
    }

    /**
     * Render Vendor Linked Payments Meta Box
     */
    public function render_vendor_payments_meta_box($post) {
        $linked_payments = get_post_meta($post->ID, '_vendor_linked_payments', true);
        ?>
        <p>
            <label for="vendor_linked_payments">Linked Payments (IDs, comma-separated):</label><br>
            <input type="text" id="vendor_linked_payments" name="vendor_linked_payments" value="<?php echo esc_attr($linked_payments); ?>" style="width:100%;">
        </p>
        <?php
    }

    /**
     * Save Vendor Meta Fields
     */
    public function save_vendor_meta($post_id) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        if (isset($_POST['vendor_phone'])) {
            update_post_meta($post_id, '_vendor_phone', sanitize_text_field($_POST['vendor_phone']));
        }

        if (isset($_POST['vendor_email'])) {
            update_post_meta($post_id, '_vendor_email', sanitize_email($_POST['vendor_email']));
        }

        if (isset($_POST['vendor_address'])) {
            update_post_meta($post_id, '_vendor_address', sanitize_textarea_field($_POST['vendor_address']));
        }

        if (isset($_POST['vendor_linked_payments'])) {
            update_post_meta($post_id, '_vendor_linked_payments', sanitize_text_field($_POST['vendor_linked_payments']));
        }
    }
}