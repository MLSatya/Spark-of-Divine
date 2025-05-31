<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SOD_Staff_Crud {

    private $wpdb;

    public function __construct( $wpdb ) {
        $this->wpdb = $wpdb;
    }

    public function createStaff( $user_id, $phone_number, $accepts_cash, $services, $availability ) {
        $table = $this->wpdb->prefix . 'sod_staff';

        $result = $this->wpdb->insert(
            $table,
            array(
                'user_id'      => $user_id,
                'phone_number' => $phone_number,
                'accepts_cash' => $accepts_cash,
                'services'     => maybe_serialize( $services ),
            ),
            array( '%d', '%s', '%d', '%s' )
        );

        if ( $result === false ) {
            sod_log_error("Failed to create staff member. " . $this->wpdb->last_error, "Staff CRUD");
            return false;
        }

        $staff_id = $this->wpdb->insert_id;

        // Create a corresponding post type entry.
        $post_data = array(
            'post_title'  => "Staff Member #{$staff_id}",
            'post_type'   => 'sod_staff',
            'post_status' => 'publish',
            'meta_input'  => array(
                'sod_staff_user_id'     => $user_id,
                'sod_staff_phone'       => $phone_number,
                'sod_staff_services'    => maybe_serialize( $services ),
                'sod_staff_accepts_cash'=> $accepts_cash,
            ),
        );
        $post_id = wp_insert_post( $post_data );
        if ( $post_id === 0 ) {
            sod_log_error("Failed to create staff post for staff ID $staff_id", "Staff CRUD");
            return false;
        }
        // Save staff availability using a separate method (or handler)
        $this->saveStaffAvailability( $staff_id, $availability );
        sod_debug_log("Successfully created staff with ID $staff_id and post ID $post_id", "Staff CRUD");
        return $staff_id;
    }

    public function getStaff( $staff_id ) {
        $table = $this->wpdb->prefix . 'sod_staff';
        $staff = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT * FROM $table WHERE staff_id = %d", $staff_id ) );

        if ( null === $staff ) {
            sod_debug_log("No staff member found with ID $staff_id", "Staff CRUD");
        } else {
            $post_id = $this->getPostIdByStaffId( $staff_id );
            if ( $post_id ) {
                $staff->phone = get_post_meta( $post_id, 'sod_staff_phone', true );
                $staff->services = maybe_unserialize( get_post_meta( $post_id, 'sod_staff_services', true ) );
                // Optionally, load availability via a helper method.
                $staff->availability = $this->getStaffAvailability( $staff_id );
            }
            sod_debug_log("Retrieved staff member with ID $staff_id", "Staff CRUD");
        }
        return $staff;
    }

    public function updateStaff( $staff_id, $phone_number, $accepts_cash, $services, $availability ) {
        $table = $this->wpdb->prefix . 'sod_staff';
        $result = $this->wpdb->update(
            $table,
            array(
                'phone_number' => $phone_number,
                'accepts_cash' => $accepts_cash,
                'services'     => maybe_serialize( $services ),
            ),
            array( 'staff_id' => $staff_id ),
            array( '%s', '%d', '%s' ),
            array( '%d' )
        );

        if ( $result === false ) {
            sod_log_error("Failed to update staff with ID $staff_id. " . $this->wpdb->last_error, "Staff CRUD");
            return false;
        }

        $post_id = $this->getPostIdByStaffId( $staff_id );
        if ( $post_id ) {
            update_post_meta( $post_id, 'sod_staff_phone', $phone_number );
            update_post_meta( $post_id, 'sod_staff_accepts_cash', $accepts_cash );
            update_post_meta( $post_id, 'sod_staff_services', maybe_serialize( $services ) );
        }

        // Update availability.
        $this->saveStaffAvailability( $staff_id, $availability );
        sod_debug_log("Successfully updated staff with ID $staff_id", "Staff CRUD");
        return true;
    }

    public function deleteStaff( $staff_id ) {
        $table = $this->wpdb->prefix . 'sod_staff';
        $result = $this->wpdb->delete( $table, array( 'staff_id' => $staff_id ), array( '%d' ) );

        if ( $result === false ) {
            sod_log_error("Failed to delete staff with ID $staff_id. " . $this->wpdb->last_error, "Staff CRUD");
            return false;
        }

        $post_id = $this->getPostIdByStaffId( $staff_id );
        if ( $post_id ) {
            wp_delete_post( $post_id, true );
            sod_debug_log("Deleted staff post ID $post_id for staff ID $staff_id", "Staff CRUD");
        }

        // Delete staff availability entries.
        $this->wpdb->delete( $this->wpdb->prefix . 'sod_staff_availability', array( 'staff_id' => $staff_id ), array( '%d' ) );
        sod_debug_log("Successfully deleted staff with ID $staff_id", "Staff CRUD");
        return $result;
    }

    // Helper to get the post ID for a staff member.
    private function getPostIdByStaffId( $staff_id ) {
        $posts = get_posts( array(
            'post_type'   => 'sod_staff',
            'meta_key'    => 'sod_staff_user_id',
            'meta_value'  => $staff_id,
            'numberposts' => 1,
        ) );
        return ! empty( $posts ) ? $posts[0]->ID : null;
    }

    // Example stub for saving availability. (You can move this to a handler if desired.)
    public function saveStaffAvailability( $staff_id, $availability ) {
        // Implement your logic here.
        // For example, loop over availability items and insert them into the sod_staff_availability table.
        sod_debug_log("Saving availability for staff ID $staff_id", "Staff CRUD");
    }

    // Example stub for retrieving staff availability.
    public function getStaffAvailability( $staff_id ) {
        // Implement your query to return availability records.
        sod_debug_log("Retrieving availability for staff ID $staff_id", "Staff CRUD");
        return array();
    }
}