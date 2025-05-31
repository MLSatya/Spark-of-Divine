<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class SOD_Bookings_Crud
 *
 * A CRUD class for managing booking records.
 *
 * @package SparkOfDivineScheduler
 * @since 2.0
 */
class SOD_Bookings_Crud {

    /**
     * The WordPress database object.
     *
     * @var wpdb
     */
    private $wpdb;

    /**
     * Constructor.
     *
     * @param wpdb $wpdb The global wpdb instance.
     */
    public function __construct( $wpdb ) {
        $this->wpdb = $wpdb;
    }

    /**
     * Create a new booking record.
     *
     * @param int    $customerId    Customer ID.
     * @param int    $serviceId     Service ID.
     * @param int    $staffId       Staff ID.
     * @param string $date          Booking date.
     * @param string $time          Booking time.
     * @param int    $duration      Duration (in minutes).
     * @param string $status        Booking status.
     * @param string $paymentMethod Payment method.
     * @return int|false            The new booking ID on success, or false on failure.
     */
    public function createBooking( $customerId, $serviceId, $staffId, $date, $time, $duration, $status, $paymentMethod ) {
        $table  = $this->wpdb->prefix . 'sod_bookings';
        $result = $this->wpdb->insert(
            $table,
            array(
                'customer_id'    => $customerId,
                'service_id'     => $serviceId,
                'staff_id'       => $staffId,
                'date'           => $date,
                'time'           => $time,
                'duration'       => $duration,
                'status'         => $status,
                'payment_method' => $paymentMethod,
            ),
            array( '%d', '%d', '%d', '%s', '%s', '%d', '%s', '%s' )
        );

        if ( false === $result ) {
            sod_log_error("Failed to create booking. " . $this->wpdb->last_error, "Booking CRUD");
            return false;
        }
        
        $booking_id = $this->wpdb->insert_id;
        sod_debug_log("Created booking with ID " . $booking_id, "Booking CRUD");
        return $booking_id;
    }

    /**
     * Retrieve a single booking by its ID.
     *
     * @param int $bookingId The booking ID.
     * @return object|null   The booking record object, or null if not found.
     */
    public function getBooking( $bookingId ) {
        $table   = $this->wpdb->prefix . 'sod_bookings';
        $booking = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT * FROM $table WHERE booking_id = %d", $bookingId ) );

        if ( null === $booking ) {
            sod_debug_log("No booking found with ID $bookingId", "Booking CRUD");
        } else {
            sod_debug_log("Retrieved booking with ID $bookingId", "Booking CRUD");
        }
        return $booking;
    }

    /**
     * Update an existing booking record.
     *
     * @param int    $bookingId     The booking ID.
     * @param string $status        New booking status.
     * @param string $paymentMethod New payment method.
     * @return int|false            The number of rows affected, or false on error.
     */
    public function updateBooking( $bookingId, $status, $paymentMethod ) {
        $table  = $this->wpdb->prefix . 'sod_bookings';
        $result = $this->wpdb->update(
            $table,
            array(
                'status'         => $status,
                'payment_method' => $paymentMethod,
            ),
            array( 'booking_id' => $bookingId ),
            array( '%s', '%s' ),
            array( '%d' )
        );

        if ( false === $result ) {
            sod_log_error("Failed to update booking $bookingId. " . $this->wpdb->last_error, "Booking CRUD");
        } elseif ( 0 === $result ) {
            sod_debug_log("No changes made when updating booking $bookingId", "Booking CRUD");
        } else {
            sod_debug_log("Updated booking $bookingId", "Booking CRUD");
        }
        return $result;
    }

    /**
     * Delete a booking record.
     *
     * @param int $bookingId The booking ID.
     * @return int|false     The number of rows deleted, or false on error.
     */
    public function deleteBooking( $bookingId ) {
        $table  = $this->wpdb->prefix . 'sod_bookings';
        $result = $this->wpdb->delete( $table, array( 'booking_id' => $bookingId ), array( '%d' ) );

        if ( false === $result ) {
            sod_log_error("Failed to delete booking $bookingId. " . $this->wpdb->last_error, "Booking CRUD");
        } elseif ( 0 === $result ) {
            sod_debug_log("No booking found to delete with ID $bookingId", "Booking CRUD");
        } else {
            sod_debug_log("Deleted booking $bookingId", "Booking CRUD");
        }
        return $result;
    }

    /**
     * Retrieve all booking records.
     *
     * @return array An array of booking records.
     */
    public function getAllBookings() {
        $table    = $this->wpdb->prefix . 'sod_bookings';
        $bookings = $this->wpdb->get_results( "SELECT * FROM $table ORDER BY start_time DESC" );
        if ( $this->wpdb->last_error ) {
            sod_log_error("Error fetching bookings - " . $this->wpdb->last_error, "Booking CRUD");
            return array();
        }
        return $bookings;
    }
}