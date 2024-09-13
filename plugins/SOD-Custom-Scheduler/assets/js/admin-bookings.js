// Admin Booking Mgmt
jQuery(document).ready(function($) {
    $('.edit-booking').on('click', function() {
        var bookingId = $(this).data('id');
        // Open edit modal or redirect to edit page
        openEditBookingModal(bookingId);
    });

    function openEditBookingModal(bookingId) {
        // Fetch booking details via AJAX
        $.ajax({
            url: sodBookings.ajax_url,
            type: 'POST',
            data: {
                action: 'get_booking_details',
                nonce: sodBookings.nonce,
                booking_id: bookingId
            },
            success: function(response) {
                if (response.success) {
                    // Populate and show edit modal
                    showEditModal(response.data);
                } else {
                    alert('Error fetching booking details');
                }
            }
        });
    }

    function showEditModal(bookingData) {
        // Create and show modal with form to edit booking
        // Include fields for service, staff, date, time, duration, status
        // Add save button that calls updateBooking function
    }

    function updateBooking(bookingId, updatedData) {
        $.ajax({
            url: sodBookings.ajax_url,
            type: 'POST',
            data: {
                action: 'update_booking',
                nonce: sodBookings.nonce,
                booking_id: bookingId,
                booking_data: updatedData
            },
            success: function(response) {
                if (response.success) {
                    alert('Booking updated successfully');
                    // Refresh the page or update the table row
                } else {
                    alert('Error updating booking: ' + response.data.message);
                }
            }
        });
    }
});