<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SOD_Customer_Crud {

    /**
     * @var wpdb
     */
    private $wpdb;

    public function __construct( $wpdb ) {
        $this->wpdb = $wpdb;
    }

    /**
     * Create a customer in the custom table and corresponding custom post.
     *
     * @param array $data
     * @return mixed Customer ID on success, false on failure.
     */
    public function createCustomer( $data ) {
        $table = $this->wpdb->prefix . 'sod_customers';

        $result = $this->wpdb->insert(
            $table,
            array(
                'name'                  => $data['name'],
                'email'                 => $data['email'],
                'client_phone'          => $data['client_phone'],
                'emergency_contact_name'=> $data['emergency_contact_name'],
                'emergency_contact_phone'=> $data['emergency_contact_phone'],
                'signing_dependent'     => $data['signing_dependent'],
                'dependent_name'        => $data['dependent_name'],
                'dependent_dob'         => $data['dependent_dob'],
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
        );

        if ( $result === false ) {
            sod_log_error('Failed to create customer. ' . $this->wpdb->last_error, 'Customer CRUD');
            return false;
        }

        $custom_customer_id = $this->wpdb->insert_id;

        // Create corresponding post type entry.
        $post_data = array(
            'post_title'   => $data['name'],
            'post_type'    => 'sod_customer',
            'post_status'  => 'publish',
            'meta_input'   => array(
                'sod_customer_id'    => $custom_customer_id,
                'sod_customer_email'   => $data['email'],
                'sod_customer_phone'   => $data['client_phone'],
            ),
        );
        $post_id = wp_insert_post( $post_data );
        if ( $post_id === 0 ) {
            sod_log_error('Failed to create customer post for customer ID ' . $custom_customer_id, 'Customer CRUD');
            return false;
        }
        sod_debug_log("Successfully created customer with custom table ID $custom_customer_id and post ID $post_id", 'Customer CRUD');
        return $custom_customer_id;
    }

    /**
     * Retrieve a customer by custom customer ID.
     *
     * @param int $customer_id
     * @return object|false
     */
    public function getCustomer( $customer_id ) {
        $table = $this->wpdb->prefix . 'sod_customers';
        $customer = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT * FROM $table WHERE customer_id = %d", $customer_id ) );

        if ( null === $customer ) {
            sod_debug_log("No customer found with ID $customer_id", 'Customer CRUD');
        } else {
            sod_debug_log("Retrieved customer with ID $customer_id", 'Customer CRUD');
            // Optionally, add post meta data.
            $post_id = $this->getPostIdByCustomerId( $customer_id );
            if ( $post_id ) {
                $customer->phone = get_post_meta( $post_id, 'sod_customer_phone', true );
                $customer->email = get_post_meta( $post_id, 'sod_customer_email', true );
            }
        }
        return $customer;
    }

    /**
     * Update a customer in the custom table and update its post meta.
     *
     * @param int    $customer_id
     * @param string $name
     * @param string $email
     * @param string $phone
     * @return int|false
     */
    public function updateCustomer( $customer_id, $name, $email, $phone ) {
        $table = $this->wpdb->prefix . 'sod_customers';
        $result = $this->wpdb->update(
            $table,
            array(
                'name'         => $name,
                'email'        => $email,
                'client_phone' => $phone,
            ),
            array( 'customer_id' => $customer_id ),
            array( '%s', '%s', '%s' ),
            array( '%d' )
        );

        if ( $result === false ) {
            sod_log_error("Failed to update customer $customer_id. " . $this->wpdb->last_error, 'Customer CRUD');
        } elseif ( $result === 0 ) {
            sod_debug_log("No changes made for customer $customer_id", 'Customer CRUD');
        } else {
            sod_debug_log("Successfully updated customer $customer_id", 'Customer CRUD');
            $post_id = $this->getPostIdByCustomerId( $customer_id );
            if ( $post_id ) {
                update_post_meta( $post_id, 'sod_customer_email', $email );
                update_post_meta( $post_id, 'sod_customer_phone', $phone );
            }
        }
        return $result;
    }

    /**
     * Delete a customer and its associated custom post.
     *
     * @param int $customer_id
     * @return int|false
     */
    public function deleteCustomer( $customer_id ) {
        $table = $this->wpdb->prefix . 'sod_customers';
        $result = $this->wpdb->delete( $table, array( 'customer_id' => $customer_id ), array( '%d' ) );

        if ( $result === false ) {
            sod_log_error("Failed to delete customer $customer_id. " . $this->wpdb->last_error, 'Customer CRUD');
        } elseif ( $result === 0 ) {
            sod_debug_log("No customer found to delete with ID $customer_id", 'Customer CRUD');
        } else {
            sod_debug_log("Successfully deleted customer $customer_id", 'Customer CRUD');
            $post_id = $this->getPostIdByCustomerId( $customer_id );
            if ( $post_id ) {
                wp_delete_post( $post_id, true );
                sod_debug_log("Deleted customer post $post_id", 'Customer CRUD');
            }
        }
        return $result;
    }

    /**
     * Helper method to get the custom post ID associated with a customer.
     *
     * @param int $customer_id
     * @return int|null
     */
    private function getPostIdByCustomerId( $customer_id ) {
        $posts = get_posts( array(
            'post_type'   => 'sod_customer',
            'meta_key'    => 'sod_customer_id',
            'meta_value'  => $customer_id,
            'numberposts' => 1,
        ) );
        return ! empty( $posts ) ? $posts[0]->ID : null;
    }
}