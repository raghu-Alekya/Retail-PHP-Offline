<?php
/**
 * The admin-fast-keys functionality of the plugin.
 *
 * @package    Fast_Keys
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class Fast_Keys {
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
        add_action('init', [$this, 'register_fast_keys_post_type']);
        add_action('add_meta_boxes', [$this, 'add_fast_keys_meta_box']);
        add_action('save_post', [$this, 'save_fast_keys_meta']);
        add_filter('manage_fast_keys_posts_columns', [$this, 'set_custom_columns']);
        add_action('manage_fast_keys_posts_custom_column', [$this, 'custom_column_content'], 10, 2);
        add_action('manage_fast_keys_posts_custom_column', [$this, 'render_custom_columns'], 10, 2);
    }

    public function register_fast_keys_post_type() {
        $args = [
            'labels' => [
                'name'          => __('Fast Keys', 'pinaka-pos'),
                'singular_name' => __('Fast Key', 'pinaka-pos'),
                'add_new'       => __('Add New Fast Key', 'pinaka-pos'),
                'add_new_item'  => __('Add New Fast Key', 'pinaka-pos'),
                'edit_item'     => __('Edit Fast Key', 'pinaka-pos'),
                'new_item'      => __('New Fast Key', 'pinaka-pos'),
                'view_item'     => __('View Fast Key', 'pinaka-pos'),
                'search_items'  => __('Search Fast Keys', 'pinaka-pos'),
                'not_found'     => __('No Fast Keys Found', 'pinaka-pos'),
                'not_found_in_trash' => __('No Fast Keys Found in Trash', 'pinaka-pos'),
            ],
            'public'        => true, // Ensure it's public
            'show_ui'       => true, // Enable UI in admin
            'show_in_menu'  => true, // Show in admin menu
            'menu_position' => 20, // Adjust position in admin menu
            'menu_icon'     => 'dashicons-editor-ul', // Set a WordPress icon
            'supports'      => ['title'], // Define supported features
            'has_archive'   => true, // Enable archive page
            'show_in_rest'  => true, // Enable for REST API
        ];
        register_post_type('fast_keys', $args);
    }

    // 1. Register new columns
    public function set_custom_columns($columns) {
        $columns['fast_keys_data'] = __('Fast Keys Data', 'pinaka-pos');
        $columns['fast_keys_user'] = __('User Email', 'pinaka-pos');
        $columns['fast_keys_index'] = __('Index ID', 'pinaka-pos');
        return $columns;
    }

    // 2. Fill column content
    public function render_custom_columns($column, $post_id) {
        switch ($column) {
            case 'fast_keys_data':
                echo esc_html(get_the_title($post_id));
                break;

            case 'fast_keys_user':
                $user_id = get_post_meta($post_id, '_fast_keys_user_id', true);
                if ($user_id) {
                    $user = get_userdata($user_id);
                    echo $user ? esc_html($user->user_email) : __('Unknown', 'pinaka-pos');
                } else {
                    echo __('N/A', 'pinaka-pos');
                }
                break;

            case 'fast_keys_user_pin':
                $user_id = get_post_meta($post_id, '_fast_keys_user_id', true);
                if ($user_id) {
                    $pin = get_user_meta($user_id, 'emp_login_pin', true);
                    echo $pin ? esc_html($pin) : __('N/A', 'pinaka-pos');
                } else {
                    echo __('N/A', 'pinaka-pos');
                }
                break;

            case 'fast_keys_index':
                $index = get_post_meta($post_id, '_fast_key_index', true);
                echo $index ? intval($index) : __('N/A', 'pinaka-pos');
                break;
        }
    }



    public function custom_column_content($column, $post_id) {
        if ($column === 'fast_keys_data') {
            $fast_keys = get_post_meta($post_id, '_fast_keys_data', true);
            if ($fast_keys) {
                $fast_keys_array = json_decode($fast_keys, true);
                if ($fast_keys_array) {
                    echo '<ul>';
                    foreach ($fast_keys_array as $key) {
                        echo '<li>' . esc_html($key['product_id']) . ' - Order: ' . esc_html($key['sl_number']) . '</li>';
                    }
                    echo '</ul>';
                } else {
                    echo __('Invalid JSON data', 'pinaka-pos');
                }
            } else {
                echo __('No Fast Keys', 'pinaka-pos');
            }
        }
    }

    public function add_fast_keys_meta_box() {
        add_meta_box(
            'fast_keys_details',
            __('Fast Keys Details', 'pinaka-pos'),
            [$this, 'render_fast_keys_meta_box'],
            'fast_keys',
            'normal',
            'high'
        );
    }
    
    public function render_fast_keys_meta_box($post) {
        // Load stored values
        $fast_keys_json  = get_post_meta($post->ID, '_fast_keys_data', true);
        $fast_keys       = json_decode($fast_keys_json, true) ?: [];

        $fastkey_index   = get_post_meta($post->ID, '_fast_key_index', true);
        $fastkey_user_id = get_post_meta($post->ID, '_fast_keys_user_id', true);
        $fastkey_image   = get_post_meta($post->ID, '_fast_key_image', true);

        ?>
        <h4><?php _e('Fast Key Settings', 'pinaka-pos'); ?></h4>

        <p>
            <label><strong><?php _e('Fast Key Index:', 'pinaka-pos'); ?></strong></label><br>
            <input type="number" name="fastkey_index" value="<?php echo esc_attr($fastkey_index); ?>" style="width:100px;">
        </p>

        <p>
            <label><strong><?php _e('User ID:', 'pinaka-pos'); ?></strong></label><br>
            <input type="text" name="fastkey_user_id" value="<?php echo esc_attr($fastkey_user_id); ?>" readonly style="width:120px; background:#f7f7f7;">
        </p>

        <p>
            <label><strong><?php _e('Fast Key Image:', 'pinaka-pos'); ?></strong></label><br>
            <input type="text" name="fastkey_image" id="fastkey_image" value="<?php echo esc_url($fastkey_image); ?>" style="width:80%;">
            <button type="button" class="button upload-fastkey-image"><?php _e('Upload', 'pinaka-pos'); ?></button>
            <?php if ($fastkey_image): ?>
                <div style="margin-top:10px;">
                    <img src="<?php echo esc_url($fastkey_image); ?>" style="max-width:150px; border:1px solid #ddd;">
                </div>
            <?php endif; ?>
        </p>

        <h4><?php _e('Fast Keys Data (Products)', 'pinaka-pos'); ?></h4>
        <table style="width:100%; border:1px solid #ddd; border-collapse:collapse;">
            <tr style="background:#f7f7f7;">
                <th style="padding:5px; border:1px solid #ddd;">Product ID</th>
                <th style="padding:5px; border:1px solid #ddd;">Sort Order</th>
            </tr>
            <?php if (!empty($fast_keys)) : ?>
                <?php foreach ($fast_keys as $key) : ?>
                    <tr>
                        <td style="padding:5px; border:1px solid #ddd;">
                            <input type="text" name="fast_keys[product_id][]" value="<?php echo esc_attr($key['product_id']); ?>" style="width:100%;">
                        </td>
                        <td style="padding:5px; border:1px solid #ddd;">
                            <input type="number" name="fast_keys[sl_number][]" value="<?php echo esc_attr($key['sl_number']); ?>" style="width:100px;">
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td><input type="text" name="fast_keys[product_id][]" value=""></td>
                    <td><input type="number" name="fast_keys[sl_number][]" value="1"></td>
                </tr>
            <?php endif; ?>
        </table>
        <?php
    }


    public function save_fast_keys_meta($post_id) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        // Save fast_keys_data
        if (isset($_POST['fast_keys'])) {
            $fast_keys_data = [];
            $count = count($_POST['fast_keys']['product_id']);
            for ($i = 0; $i < $count; $i++) {
                $fast_keys_data[] = [
                    'product_id' => sanitize_text_field($_POST['fast_keys']['product_id'][$i]),
                    'sl_number'  => intval($_POST['fast_keys']['sl_number'][$i]),
                ];
            }
            update_post_meta($post_id, '_fast_keys_data', json_encode($fast_keys_data));
        }

        // Save image
        if (isset($_POST['fastkey_image'])) {
            update_post_meta($post_id, '_fast_key_image', esc_url_raw($_POST['fastkey_image']));
        }

        // Save user id (only if new, prevent tampering)
        if (isset($_POST['fastkey_user_id']) && !get_post_meta($post_id, '_fast_keys_user_id', true)) {
            update_post_meta($post_id, '_fast_keys_user_id', intval($_POST['fastkey_user_id']));
        }

        // Handle reordering indexes
        if (isset($_POST['fastkey_index'])) {
            global $wpdb;

            $new_index = intval($_POST['fastkey_index']);
            $old_index = intval(get_post_meta($post_id, '_fast_key_index', true));

            // Update this post’s index
            update_post_meta($post_id, '_fast_key_index', $new_index);

            if ($new_index !== $old_index) {
                // Get all other fastkeys ordered by index
                $query = new WP_Query([
                    'post_type'      => 'fastkeys',
                    'post_status'    => 'publish',
                    'posts_per_page' => -1,
                    'post__not_in'   => [$post_id],
                    'meta_key'       => '_fast_key_index',
                    'orderby'        => 'meta_value_num',
                    'order'          => 'ASC',
                ]);

                $counter = 1;
                if ($query->have_posts()) {
                    foreach ($query->posts as $post) {
                        if ($counter == $new_index) {
                            $counter++; // Skip spot taken by current post
                        }
                        update_post_meta($post->ID, '_fast_key_index', $counter);
                        $counter++;
                    }
                }
            }
        }
    }



}
