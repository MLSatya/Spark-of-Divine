<?php
if ( ! defined('ABSPATH') ) {
    exit;
}

class SOD_Customer_Meta_Boxes {

    public function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'add_customer_meta_boxes' ) );
        add_action( 'save_post_sod_customer', array( $this, 'save_customer_meta_boxes' ) );
    }

    public function add_customer_meta_boxes() {
        add_meta_box(
            'sod_customer_details',
            __( 'Customer Details', 'spark-of-divine-scheduler' ),
            array( $this, 'render_customer_details' ),
            'sod_customer',
            'normal',
            'default'
        );
    }

    public function render_customer_details( $post ) {
        wp_nonce_field( 'sod_customer_meta_box', 'sod_customer_meta_box_nonce' );
        $phone = get_post_meta( $post->ID, 'sod_customer_phone', true );
        $email = get_post_meta( $post->ID, 'sod_customer_email', true );
        ?>
        <p>
            <label for="sod_customer_phone"><?php _e( 'Phone Number:', 'spark-of-divine-scheduler' ); ?></label>
            <input type="text" name="sod_customer_phone" id="sod_customer_phone" value="<?php echo esc_attr( $phone ); ?>" class="widefat" />
        </p>
        <p>
            <label for="sod_customer_email"><?php _e( 'Email:', 'spark-of-divine-scheduler' ); ?></label>
            <input type="email" name="sod_customer_email" id="sod_customer_email" value="<?php echo esc_attr( $email ); ?>" class="widefat" />
        </p>
        <?php
    }

    public function save_customer_meta_boxes( $post_id ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( wp_is_post_revision( $post_id ) ) return;
        if ( ! isset( $_POST['sod_customer_meta_box_nonce'] ) ||
             ! wp_verify_nonce( $_POST['sod_customer_meta_box_nonce'], 'sod_customer_meta_box' ) ) {
            return;
        }

        $phone = isset( $_POST['sod_customer_phone'] ) ? sanitize_text_field( $_POST['sod_customer_phone'] ) : '';
        $email = isset( $_POST['sod_customer_email'] ) ? sanitize_email( $_POST['sod_customer_email'] ) : '';

        update_post_meta( $post_id, 'sod_customer_phone', $phone );
        update_post_meta( $post_id, 'sod_customer_email', $email );
    }
}

new SOD_Customer_Meta_Boxes();