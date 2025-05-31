<?php
if ( ! defined('ABSPATH') ) {
    exit;
}

class SOD_Service_Meta_Boxes {

    public function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'add_service_meta_boxes' ) );
        add_action( 'save_post_sod_service', array( $this, 'save_service_meta_boxes' ), 10, 2 );
    }

    public function add_service_meta_boxes() {
        add_meta_box(
            'sod_service_price',
            __( 'Service Base Price', 'spark-of-divine-scheduler' ),
            array( $this, 'render_service_price_meta_box' ),
            'sod_service',
            'normal',
            'high'
        );
    }

    public function render_service_price_meta_box( $post ) {
        wp_nonce_field( 'sod_service_meta_box', 'sod_service_meta_box_nonce' );
        $price = get_post_meta( $post->ID, 'sod_service_price', true );
        ?>
        <p>
            <label for="sod_service_price"><?php _e( 'Base Price ($):', 'spark-of-divine-scheduler' ); ?></label>
            <input type="number" name="sod_service_price" id="sod_service_price" 
                   value="<?php echo esc_attr( $price ); ?>" step="0.01" min="0" class="widefat" />
        </p>
        <?php
    }

    public function save_service_meta_boxes( $post_id, $post ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( wp_is_post_revision( $post_id ) ) return;
        if ( ! isset( $_POST['sod_service_meta_box_nonce'] ) || 
             ! wp_verify_nonce( $_POST['sod_service_meta_box_nonce'], 'sod_service_meta_box' ) ) {
            return;
        }

        $price = isset( $_POST['sod_service_price'] ) ? floatval( sanitize_text_field( $_POST['sod_service_price'] ) ) : 0.00;
        update_post_meta( $post_id, 'sod_service_price', $price );
    }
}

new SOD_Service_Meta_Boxes();