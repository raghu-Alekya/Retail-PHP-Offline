<?php 
/**
 * The admin-shifts functionality of the plugin.
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

class Pinaka_POS_Shifts {

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

        add_action('add_meta_boxes', [$this, 'add_shift_meta_boxes']);
        add_action('save_post', [$this, 'save_shift_meta']);
        add_filter('manage_shifts_posts_columns', [$this, 'set_custom_columns']);
        add_action('manage_shifts_posts_custom_column', [$this, 'custom_column_content'], 10, 2);
    }

    /**
     * Register the vendor custom post type.
     *
     * @since    1.0.0
     * @access   private
     */
    public function register_shifts_post_type() {
        $labels = array(
            'name'                  => __('Shifts', 'pinaka-pos'),
            'singular_name'         => __('Shift', 'pinaka-pos'),
            'add_new'               => __('Add New Shift', 'pinaka-pos'),
            'add_new_item'          => __('Add New Shift', 'pinaka-pos'),
            'edit_item'             => __('Edit Shift', 'pinaka-pos'),
            'new_item'              => __('New Shift', 'pinaka-pos'),
            'view_item'             => __('View Shift', 'pinaka-pos'),
            'search_items'          => __('Search Shifts', 'pinaka-pos'),
            'not_found'             => __('No shifts found', 'pinaka-pos'),
            'not_found_in_trash'    => __('No shifts found in Trash', 'pinaka-pos'),
            'menu_name'             => __('Shifts', 'pinaka-pos'),
        );

        $args = array(
            'labels'        => $labels,
            'description'   => __('Shifts custom post type', 'pinaka-pos'),
            'public'        => true,
            'publicly_queryable' => true,
            'show_ui'       => true,
            'show_in_menu'  => false, // Hide from top menu, shown under Pinaka POS
            'query_var'     => true,
            'rewrite'       => array('slug' => 'shifts'),
            'capability_type' => 'post',
            'has_archive'   => true,
            'hierarchical'  => false,
            'menu_position' => null,
            'supports'      => array('title', 'editor', 'thumbnail'),
        );

        register_post_type('shifts', $args);
    }

    /**
     * Define the admin Table columns.
     */

    public function set_custom_columns($columns) {
        $columns['shift_start_time'] = __('Start Time', 'pinaka-pos');
        $columns['shift_total_sales'] = __('Total Sale Amount', 'pinaka-pos');
        $columns['shift_safe_drop_total'] = __('Safe Drop Total', 'pinaka-pos');
        $columns['shift_assigned_staff'] = __('Assigned Staff', 'pinaka-pos');
        $columns['shift_opening_balance'] = __('Opening Bal', 'pinaka-pos');
        $columns['shift_closing_balance'] = __('Closing Bal ', 'pinaka-pos');
        $columns['shift_over_short'] = __('Over/Short ', 'pinaka-pos');
        $columns['shift_end_time'] = __('Closing Time', 'pinaka-pos');
        return $columns;
    }

    /**
     * Render the column values.
     */

    public function custom_column_content($column, $post_id) {
        switch ($column) {
            case 'shift_assigned_staff':
                $shift_user_id = get_post_meta($post_id, '_shift_assigned_staff', true);
                $shift_user = get_userdata($shift_user_id);
                echo $shift_user ? esc_html($shift_user->display_name) : __('N/A', 'pinaka-pos');
                break;
            case 'shift_start_time':
                echo esc_html(get_post_meta($post_id, '_shift_start_time', true));
                break;
            case 'shift_total_sales':
                echo esc_html(get_post_meta($post_id, '_shift_total_sales', true));
                break;
            case 'shift_safe_drop_total':
                $safe_drops = $this->get_shift_safe_drops( $post_id );
		        $safe_drop_total = floatval( $safe_drops['total_safe_drop'] ?? 0 );
                echo $safe_drop_total;
                break;
            case 'shift_opening_balance':
                echo esc_html(get_post_meta($post_id, '_shift_opening_balance', true));
                break;
            case 'shift_closing_balance':
                echo esc_html(get_post_meta($post_id, '_shift_closing_balance', true));
                break;
            case 'shift_over_short':
                echo esc_html(get_post_meta($post_id, '_shift_over_short', true));
                break;
            case 'shift_end_time':
                echo esc_html(get_post_meta($post_id, '_shift_end_time', true));
                break;
        }
    }


    /**
     * Define the admin hooks.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_admin_hooks() {
        // ✅ Ensure post type is registered before menu is built
        $this->loader->add_action('init', $this, 'register_shifts_post_type');
        $this->loader->add_action('admin_menu', $this, 'add_shifts_submenu_page');
    }

    /**
     * Add the shifts submenu page.
     *
     * @since    1.0.0
     * @access   private
     */
    public function add_shifts_submenu_page() {
        add_submenu_page(
            'pinaka-pos-dashboard',
            __('Manage Shifts', 'pinaka-pos'),
            __('Manage Shifts', 'pinaka-pos'),
            'manage_options',
            'pinaka-pos-shiftss',
            [$this, 'shiftsRender']
        );
    }

    /**
     * Render the shifts submenu page.
     *
     * @since    1.0.0
     * @access   public
     */
    public function shiftsRender() {
        wp_safe_redirect(admin_url('edit.php?post_type=shifts'));
        exit;
    }

    /**
     * Add meta boxes to Shift post type
     */
    public function add_shift_meta_boxes() {
        add_meta_box(
            'shift_details',
            'Shift Details',
            [$this, 'render_shift_details_meta_box'],
            'shifts',
            'normal',
            'high'
        );
    }

    /**
     * Render Shift Details Meta Box
     */
    public function render_shift_details_meta_box($post) {
        $start_time = get_post_meta($post->ID, '_shift_start_time', true);
        $end_time = get_post_meta($post->ID, '_shift_end_time', true);
        $assigned_staff = get_post_meta($post->ID, '_shift_assigned_staff', true);

        $staff_name = get_user_meta($assigned_staff, 'first_name', true).' '.get_user_meta($assigned_staff, 'last_name', true);

        $total_sales = get_post_meta($post->ID, '_shift_total_sales', true);
        $safe_float = function($v) {
			return is_numeric($v) ? floatval($v) : 0.0;
		};
        $safe_drops = $this->get_shift_safe_drops( $post->ID );
		$safe_drop_total = $safe_float( $safe_drops['total_safe_drop'] ?? 0 );
        $safe_drop_total = $safe_drop_total;
        $opening_balance = get_post_meta($post->ID, '_shift_opening_balance', true);
        $closing_balance = get_post_meta($post->ID, '_shift_closing_balance', true);
        $status = get_post_meta($post->ID, '_shift_status', true);
        $drawer_opening_denominations = get_post_meta($post->ID, '_shift_drawer_denominations', true);
        $drawer = json_decode($drawer_opening_denominations, true) ?: [];
        $tube_opening_denominations = get_post_meta($post->ID, '_shift_tube_denominations', true);

        $tubes = json_decode($tube_opening_denominations, true) ?: [];
        $vendor_payments = $this->get_shift_vendor_payouts_total($post->ID);
        ?>
        <p>
            <label for="shift_start_time">Start Time:</label><br>
            <input type="datetime-local" id="shift_start_time" name="shift_start_time" value="<?php echo esc_attr($start_time); ?>" style="width:100%;">
        </p>
        <p>
            <label for="shift_end_time">End Time:</label><br>
            <input type="datetime-local" id="shift_end_time" name="shift_end_time" value="<?php echo esc_attr($end_time); ?>" style="width:100%;">
        </p>
        <p>
            <label for="shift_assigned_staff">Assigned Staff:</label><br>
            <input type="text" value="<?php echo esc_attr($staff_name); ?>" style="width:100%;">
            <input type="hidden" id="shift_assigned_staff" name="shift_assigned_staff" value="<?php echo esc_attr($assigned_staff); ?>" style="width:100%;">
        </p>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?= __('Opening Tube Denomination', 'pinaka-pos') ?></th>
                        <th><?= __('Tubes', 'pinaka-pos') ?></th>
                        <th><?= __('Cells', 'pinaka-pos') ?></th>
                        <th><?= __('Total', 'pinaka-pos') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $sum_of_all_denomination = 0;
                    if( !empty($tubes) ) {
                    foreach ($tubes as $entry): 
                    if($entry['denomination'] > 0):
                    ?>                                                                             
                        <tr>
                            <td>
                                <input type="number" name="safes[denomination][]" value="<?= esc_attr(@$entry['denomination']) ?>" step="0.01" />
                            </td>
                            <td>
                                <input type="number" name="safes[tube_count][]" value="<?= esc_attr(@$entry['tube_count']) ?>" />
                            </td>
                            <td>
                                <input type="number" name="safes[cell_count][]" value="<?= esc_attr(@$entry['cell_count']) ?>" />
                            </td>
                            <td>
                                <?php 
                                $total = @$entry['denomination'] * @$entry['tube_count'] * @$entry['cell_count'];
                                echo number_format($total, 2); 
                                ?>
                            </td>
                        </tr>
                        <?php 
                        $sum_of_all_denomination += $total;
                        endif;
                    endforeach; 
                    } ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td><?php echo number_format(@$sum_of_all_denomination, 2);?></td>
                    </tr>
                </tfoot>
            </table>
        <p>
            <label for="shift_opening_balance">Opening Balance:</label><br>
            <input type="number" id="shift_opening_balance" name="shift_opening_balance" value="<?php echo esc_attr($opening_balance); ?>" style="width:100%;">
        </p>
        <table class="widefat" style="width:100%; border:1px solid #ddd;">
            <thead>
                <tr>
                    <th><?= __('Denomination', 'pinaka-pos') ?></th>
                    <th><?= __('Count', 'pinaka-pos') ?></th>
                    <th><?= __('Total', 'pinaka-pos') ?></th>

                </tr>
            </thead>
            <tbody>
            <?php 
                $sum_of_all_denomination = 0;
                foreach ($drawer as $entry): 
                    if($entry['denomination'] > 0):
                ?>
                <tr>
                    <td><input type="number" name="safes[denomination][]" value="<?php echo esc_attr($entry['denomination']); ?>" step="0.01"></td>
                    <td><input type="number" name="safes[tube_count][]" value="<?php echo esc_attr($entry['denom_count']); ?>"></td>
                    <td>
                        <?php 
                        $total = $entry['denomination'] * $entry['denom_count'];
                        echo number_format($total, 2); 
                        ?>
                    </td>

                </tr>
            <?php 
                $sum_of_all_denomination += $total;
                endif;
                endforeach; 
            ?>
            </tbody>
            <tfoot>
                <tr>
                    <td></td>
                    <td></td>
                    <td><?php echo number_format($sum_of_all_denomination, 2);?></td>
                </tr>
            </tfoot>
        </table>
        
        <p>
            <label for="shift_opening_balance">Vendor Payments:</label><br>
        </p>
        <?php 
            if(count($vendor_payments) > 0):
        ?>
        <table class="widefat" style="width:100%; border:1px solid #ddd;">
            <thead>
                <tr>
                    <th><?= __('Vendor Name', 'pinaka-pos') ?></th>
                    <th><?= __('Amount', 'pinaka-pos') ?></th>

                </tr>
            </thead>
            <tbody>
            <?php 
                $sum_of_all_vendorpayments= 0;
                foreach ($vendor_payments['vendor_payments'] as $vendor_payment): ?>
                <tr>
                    <td><?=$vendor_payment['vendor_name'];?></td>
                    <td><?=number_format($vendor_payment['amount'],2);?></td>
                    <?php 
                    $total = $vendor_payment['amount'];
                    number_format($total, 2); 
                    ?>

                </tr>
            <?php 
                $sum_of_all_vendorpayments += $total;
                endforeach; 
            ?>
            </tbody>
            <tfoot>
                <tr>
                    <td></td>
                    <td><?php echo number_format($sum_of_all_vendorpayments, 2);?></td>
                </tr>
            </tfoot>
        </table>
        <?php
            endif;
        ?>      
        <p>
            <label for="shift_closing_balance">Closing Balance:</label><br>
            <input type="number" id="shift_closing_balance" name="shift_closing_balance" value="<?php echo esc_attr($closing_balance); ?>" style="width:100%;">
        </p>
        <p>
            <label for="shift_status">Status:</label><br>
            <select id="shift_status" name="shift_status">
                <option value="Open" <?php selected($status, 'Open'); ?>>Open</option>
                <option value="Closed" <?php selected($status, 'Closed'); ?>>Closed</option>
            </select>
        </p>
        <?php
    }

    /**
     * Save Shift Meta Fields
     */
    public function save_shift_meta($post_id) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        if (isset($_POST['shift_start_time'])) {
            update_post_meta($post_id, '_shift_start_time', sanitize_text_field($_POST['shift_start_time']));
        }

        if (isset($_POST['shift_end_time'])) {
            update_post_meta($post_id, '_shift_end_time', sanitize_text_field($_POST['shift_end_time']));
        }

        if (isset($_POST['shift_assigned_staff'])) {
            update_post_meta($post_id, '_shift_assigned_staff', sanitize_text_field($_POST['shift_assigned_staff']));
        }

        if (isset($_POST['shift_opening_balance'])) {
            update_post_meta($post_id, '_shift_opening_balance', floatval($_POST['shift_opening_balance']));
        }

        if (isset($_POST['shift_closing_balance'])) {
            update_post_meta($post_id, '_shift_closing_balance', floatval($_POST['shift_closing_balance']));
        }

        if (isset($_POST['shift_status'])) {
            update_post_meta($post_id, '_shift_status', sanitize_text_field($_POST['shift_status']));
        }
    }
    

    /**
	 * Get the total vendor payouts for a given shift.
	 *
	 * @param int $shift_id The ID of the shift.
	 * @return float The total vendor payouts amount.
	 */
	public function get_shift_vendor_payouts_total( $shift_id ) {
		$payouts = get_posts([
			'post_type'   => 'vendor_payments',
			'post_status' => 'publish',
			'numberposts' => -1,
			'meta_query'  => [
				[
					'key'   => '_vendor_payment_shift_id',
					'value' => $shift_id,
				]
			],
		]);

		$total_vendor_payouts = 0;
		$results = [];
		foreach ( $payouts as $payout ) {
			$total_vendor_payouts += floatval(get_post_meta($payout->ID, '_vendor_payment_amount', true));
            $vendor_info = get_post_meta($payout->ID, '_vendor_id', true);
			$results['vendor_payments'][] = [
				'id'        => $payout->ID,
				'amount'    => floatval(get_post_meta($payout->ID, '_vendor_payment_amount', true)),
				'note'      => get_post_meta($payout->ID, '_vendor_payment_note', true),
				'payment_method' => get_post_meta($payout->ID, '_vendor_payment_method', true),
				'time'      => get_post($payout->ID, 'post_date', true)->post_date,
				'vendor_name' => get_post($vendor_info, '_vendor_id', true)->post_title,
				'service_type'	 => get_post_meta($payout->ID, '_vendor_payment_service_type', true),
				'vendor_id' => get_post_meta($payout->ID, '_vendor_id', true),
			];
			$results['total_vendor_payments'] = $total_vendor_payouts;
		}
		return $results;
	}
    public function get_shift_safe_drops( $shift_id ) {
		$safe_drops = get_posts([
			'post_type'   => 'safedrops',
			'post_status' => 'publish',
			'numberposts' => -1,
			'meta_query'  => [
				[
					'key'   => '_safedrops_shift_id',
					'value' => $shift_id,
				],
			],
		]);

		$results = [];
		$total_safe_drop = 0;
		foreach ( $safe_drops as $drop ) {
			$time = get_post($drop->ID, 'post_date', true);
			$total_safe_drop += floatval(get_post_meta($drop->ID, '_safedrops_total', true));
			$results['safe_drops'][] = [
				'id'        => $drop->ID,
				'total'     => floatval(get_post_meta($drop->ID, '_safedrops_total', true)),
				'denominations' => json_decode(get_post_meta($drop->ID, '_safedrops_data', true), true),
				'note'      => get_post_meta($drop->ID, '_safe_drop_note', true),
				'time'      => $time->post_date,
			];
			$results['total_safe_drop'] = $total_safe_drop ?? 0;
		}
		
		return $results;
	}
}