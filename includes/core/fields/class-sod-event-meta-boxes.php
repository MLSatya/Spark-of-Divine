<?php
/**
 * Custom Meta Boxes for SOD Event Post Type
 *
 * @package SparkOfDivineScheduler
 * @since 2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class SOD_Event_Meta_Boxes {

    public function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'add_event_meta_boxes' ), 20 );
        add_action( 'save_post_sod_event', array( $this, 'save_event_meta_boxes' ) );
    }

    public function add_event_meta_boxes() {
        add_meta_box(
            'sod_event_details',
            __( 'Event Details', 'spark-of-divine-scheduler' ),
            array( $this, 'render_event_details' ),
            'sod_event',
            'normal',
            'high'
        );
    }

    public function render_event_details( $post ) {
        wp_nonce_field( 'sod_event_meta_box', 'sod_event_meta_box_nonce' );

        // Retrieve existing values from post meta
        $start_time = get_post_meta( $post->ID, '_sod_event_start_time', true );
        $end_time = get_post_meta( $post->ID, '_sod_event_end_time', true );
        $is_multi_day = get_post_meta( $post->ID, '_sod_event_is_multi_day', true );
        $recurs_day = get_post_meta( $post->ID, '_sod_event_recurs_day', true );
        $staff_id = get_post_meta( $post->ID, '_sod_event_staff_id', true );
        $product_id = get_post_meta( $post->ID, '_sod_event_product_id', true );

        // Debug: Log retrieved values
        error_log("Event Meta Retrieved - Post ID: $post->ID, Start: $start_time, End: $end_time, Multi: $is_multi_day, Recurs: $recurs_day, Staff: $staff_id, Product: $product_id");

        ?>
        <p>
            <label for="sod_event_start_time"><?php _e( 'Start Time:', 'spark-of-divine-scheduler' ); ?></label><br>
            <input type="datetime-local" id="sod_event_start_time" name="sod_event_start_time" value="<?php echo esc_attr( $start_time ); ?>" class="widefat" />
        </p>
        <p>
            <label for="sod_event_end_time"><?php _e( 'End Time:', 'spark-of-divine-scheduler' ); ?></label><br>
            <input type="datetime-local" id="sod_event_end_time" name="sod_event_end_time" value="<?php echo esc_attr( $end_time ); ?>" class="widefat" />
        </p>
        <p>
            <label for="sod_event_is_multi_day">
                <input type="checkbox" id="sod_event_is_multi_day" name="sod_event_is_multi_day" value="1" <?php checked( $is_multi_day, 1 ); ?> />
                <?php _e( 'Is Multi-Day Event?', 'spark-of-divine-scheduler' ); ?>
            </label>
        </p>
        <p>
            <label for="sod_event_recurs_day"><?php _e( 'Recurs Every X Day of the Month (e.g., 15 for 15th):', 'spark-of-divine-scheduler' ); ?></label><br>
            <input type="number" id="sod_event_recurs_day" name="sod_event_recurs_day" min="1" max="31" value="<?php echo esc_attr( $recurs_day ); ?>" class="widefat" />
        </p>
        <p>
            <label for="sod_event_staff_id"><?php _e( 'Facilitator (Staff):', 'spark-of-divine-scheduler' ); ?></label><br>
            <select id="sod_event_staff_id" name="sod_event_staff_id" class="widefat">
                <option value=""><?php _e( 'Select Staff', 'spark-of-divine-scheduler' ); ?></option>
                <?php
                $staff_query = new WP_Query( array(
                    'post_type' => 'sod_staff',
                    'posts_per_page' => -1,
                    'orderby' => 'title',
                    'order' => 'ASC',
                ) );
                if ( $staff_query->have_posts() ) {
                    while ( $staff_query->have_posts() ) {
                        $staff_query->the_post();
                        $selected = ( $staff_id == get_the_ID() ) ? 'selected="selected"' : '';
                        echo '<option value="' . esc_attr( get_the_ID() ) . '" ' . $selected . '>' . esc_html( get_the_title() ) . '</option>';
                    }
                    wp_reset_postdata();
                }
                ?>
            </select>
        </p>
        <?php if ( $product_id ) : ?>
            <p>
                <label><?php _e( 'Linked WooCommerce Product:', 'spark-of-divine-scheduler' ); ?></label><br>
                <a href="<?php echo get_edit_post_link( $product_id ); ?>" target="_blank"><?php echo get_the_title( $product_id ); ?> (ID: <?php echo $product_id; ?>)</a>
            </p>
        <?php endif; ?>
        <?php
    }

    public function save_event_meta_boxes( $post_id ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( wp_is_post_revision( $post_id ) ) {
            return;
        }
        if ( ! isset( $_POST['sod_event_meta_box_nonce'] ) || 
             ! wp_verify_nonce( $_POST['sod_event_meta_box_nonce'], 'sod_event_meta_box' ) ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Get submitted data
        $start_time = isset( $_POST['sod_event_start_time'] ) ? sanitize_text_field( $_POST['sod_event_start_time'] ) : '';
        $end_time = isset( $_POST['sod_event_end_time'] ) ? sanitize_text_field( $_POST['sod_event_end_time'] ) : '';
        $is_multi_day = isset( $_POST['sod_event_is_multi_day'] ) ? 1 : 0;
        $recurs_day = isset( $_POST['sod_event_recurs_day'] ) && ! empty( $_POST['sod_event_recurs_day'] ) ? absint( $_POST['sod_event_recurs_day'] ) : '';
        $staff_id = isset( $_POST['sod_event_staff_id'] ) && ! empty( $_POST['sod_event_staff_id'] ) ? absint( $_POST['sod_event_staff_id'] ) : '';

        // Save to post meta
        update_post_meta( $post_id, '_sod_event_start_time', $start_time );
        update_post_meta( $post_id, '_sod_event_end_time', $end_time );
        update_post_meta( $post_id, '_sod_event_is_multi_day', $is_multi_day );
        if ( $recurs_day ) {
            update_post_meta( $post_id, '_sod_event_recurs_day', $recurs_day );
        } else {
            delete_post_meta( $post_id, '_sod_event_recurs_day' );
        }
        if ( $staff_id ) {
            update_post_meta( $post_id, '_sod_event_staff_id', $staff_id );
        } else {
            delete_post_meta( $post_id, '_sod_event_staff_id' );
        }

        // Create or update WooCommerce product
        $product_id = get_post_meta( $post_id, '_sod_event_product_id', true );
        if ( ! $product_id && $start_time ) { // Only create if no product exists and start time is set
            $product_id = $this->create_event_product( $post_id, $start_time, $staff_id );
            update_post_meta( $post_id, '_sod_event_product_id', $product_id );
        }

        // Update custom table
        global $wpdb;
        $table_name = $wpdb->prefix . 'sod_events';
        $existing = $wpdb->get_var( $wpdb->prepare( "SELECT event_id FROM $table_name WHERE post_id = %d", $post_id ) );

        if ( $existing ) {
            $wpdb->update(
                $table_name,
                array(
                    'start_time' => $start_time,
                    'end_time' => $end_time,
                    'is_multi_day' => $is_multi_day,
                    'recurs_day' => $recurs_day ?: null,
                    'staff_id' => $staff_id ?: null,
                    'product_id' => $product_id,
                ),
                array( 'post_id' => $post_id ),
                array( '%s', '%s', '%d', '%d', '%d', '%d' ),
                array( '%d' )
            );
        } else {
            $wpdb->insert(
                $table_name,
                array(
                    'post_id' => $post_id,
                    'start_time' => $start_time,
                    'end_time' => $end_time,
                    'is_multi_day' => $is_multi_day,
                    'recurs_day' => $recurs_day ?: null,
                    'staff_id' => $staff_id ?: null,
                    'product_id' => $product_id,
                ),
                array( '%d', '%s', '%s', '%d', '%d', '%d', '%d' )
            );
        }

        error_log("Event Meta Saved - Post ID: $post_id, Product ID: $product_id, Start: $start_time, Staff: $staff_id");
    }

    private function create_event_product( $post_id, $start_time, $staff_id ) {
        if ( ! class_exists( 'WC_Product_Simple' ) ) {
            error_log("WooCommerce not active, cannot create product for Event Post ID: $post_id");
            return 0;
        }

        $event_title = get_the_title( $post_id );
        $product = new WC_Product_Simple();
        $product->set_name( $event_title );
        $product->set_status( 'publish' );
        $product->set_catalog_visibility( 'visible' );
        $product->set_price( 0.00 ); // Default price, adjust as needed
        $product->set_regular_price( 0.00 );
        $product->set_sold_individually( true );
        $product->set_virtual( true );

        // Add event-specific meta
        $product->update_meta_data( '_sod_event_post_id', $post_id );
        $product->update_meta_data( '_sod_event_start_time', $start_time );
        if ( $staff_id ) {
            $staff_name = get_the_title( $staff_id );
            $product->update_meta_data( '_sod_event_staff_id', $staff_id );
            $product->set_description( sprintf( __( 'Event facilitated by %s', 'spark-of-divine-scheduler' ), $staff_name ) );
        }

        $product_id = $product->save();
        error_log("WooCommerce Product Created - Product ID: $product_id for Event Post ID: $post_id");

        return $product_id;
    }
}

new SOD_Event_Meta_Boxes();