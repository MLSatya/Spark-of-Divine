<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Calculate available time slots for a staff member on a specific day.
 *
 * @param string $avail_start  Staff availability start time (e.g., "09:00:00").
 * @param string $avail_end    Staff availability end time (e.g., "17:00:00").
 * @param array  $booked_slots Array of booked slots. Each slot is an array with keys 'start' and 'end' (times in "H:i:s" format).
 * @param int    $slot_length  Slot duration in minutes.
 *
 * @return array An array of available slots. Each slot is an associative array with 'start' and 'end'.
 */
function calculate_available_slots( $avail_start, $avail_end, $booked_slots, $slot_length ) {
    // Assume the times refer to today; adjust as needed.
    $today = date('Y-m-d');
    $avail_start_ts = strtotime("$today $avail_start");
    $avail_end_ts   = strtotime("$today $avail_end");

    $available_slots = [];
    // Loop through the availability block in increments of $slot_length minutes.
    for ( $time = $avail_start_ts; $time + ($slot_length * 60) <= $avail_end_ts; $time += ($slot_length * 60) ) {
        $slot_start = $time;
        $slot_end   = $time + ($slot_length * 60);

        // Check for overlap with any booked slot.
        $overlap = false;
        foreach ( $booked_slots as $booked ) {
            $booked_start = strtotime("$today " . $booked['start']);
            $booked_end   = strtotime("$today " . $booked['end']);
            if ( $slot_start < $booked_end && $slot_end > $booked_start ) {
                $overlap = true;
                break;
            }
        }
        if ( ! $overlap ) {
            $available_slots[] = [
                'start' => date('H:i:s', $slot_start),
                'end'   => date('H:i:s', $slot_end)
            ];
        }
    }
    return $available_slots;
}

/**
 * Retrieve booked slots for a staff member on a specific day.
 *
 * @param int    $staff_id Staff member's ID.
 * @param string $date     Date in Y-m-d format.
 *
 * @return array Array of booked slots. Each is an array with 'start' and 'end' keys.
 */
function get_booked_slots_for_staff( $staff_id, $date ) {
    global $wpdb;
    // This example assumes your booking posts store the booked slot as JSON in meta '_sod_booked_slot',
    // and that staff ID and date are stored in meta '_sod_staff_id' and '_sod_date'.
    $query = $wpdb->prepare( "
        SELECT pm.meta_value FROM {$wpdb->postmeta} pm
        INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
        WHERE pm.meta_key = '_sod_booked_slot'
        AND p.post_type = 'sod_booking'
        AND p.post_status = 'publish'
        AND p.ID IN (
            SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sod_staff_id' AND meta_value = %d
        )
        AND p.ID IN (
            SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sod_date' AND meta_value = %s
        )
    ", $staff_id, $date );

    $results = $wpdb->get_col( $query );
    $booked_slots = [];
    foreach ( $results as $result ) {
        $slot = json_decode( $result, true );
        if ( is_array( $slot ) && isset( $slot['start'], $slot['end'] ) ) {
            $booked_slots[] = $slot;
        }
    }
    return $booked_slots;
}