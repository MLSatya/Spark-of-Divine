// Booking Form JS
document.addEventListener('DOMContentLoaded', function() {
    var modal = document.getElementById("bookingModal");
    var btn = document.getElementById("openModal");
    var span = document.getElementsByClassName("close")[0];

    // Open the modal
    btn.onclick = function() {
        modal.style.display = "block";
        loadCategories(); // Load categories when the modal is opened
    };

    // Close the modal
    span.onclick = function() {
        modal.style.display = "none";
    };

    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = "none";
        }
    };

    // Handle form submission
    document.getElementById('sod-booking-form').addEventListener('submit', function(e) {
        e.preventDefault();

        var formData = {
            action: 'sod_submit_booking', // Ensure this is correctly handled in PHP
            nonce: sodBooking.nonce,
            service_id: document.getElementById('service').value,
            staff_id: document.getElementById('staff').value,
            timeslot: document.getElementById('timeslot').value,
            duration: document.getElementById('duration').value
        };

        // Submit booking data
        jQuery.post(sodBooking.ajax_url, formData, function(response) {
            if (response.success) {
                alert(response.data.message);
                modal.style.display = "none";
                // Optionally reset the form or update the UI
            } else {
                alert(response.data.message);
            }
        });
    });

    // Load categories dynamically
    function loadCategories() {
        jQuery.post(sodBooking.ajax_url, {
            action: 'get_service_categories',
            nonce: sodBooking.nonce
        }, function(response) {
            if (response.success) {
                populateSelectField('category', response.data, 'Select Category');
            } else {
                alert('Failed to load categories.');
            }
        });
    }

    // Load services when a category is selected
    document.getElementById('category').addEventListener('change', function() {
        var categoryId = this.value;
        
        if (categoryId) {
            jQuery.post(sodBooking.ajax_url, {
                action: 'get_services',
                nonce: sodBooking.nonce,
                category_id: categoryId
            }, function(response) {
                if (response.success) {
                    populateSelectField('service', response.data, 'Select Service');
                } else {
                    alert('Failed to load services.');
                }
            });
        }
    });

    // Load staff when a service is selected
    document.getElementById('service').addEventListener('change', function() {
        var serviceId = this.value;
        
        if (serviceId) {
            jQuery.post(sodBooking.ajax_url, {
                action: 'get_staff', // Ensure this action is registered in PHP
                nonce: sodBooking.nonce,
                service_id: serviceId
            }, function(response) {
                if (response.success) {
                    populateSelectField('staff', response.data, 'Select Staff');
                } else {
                    alert('Failed to load staff.');
                }
            });
        }
    });

    // Load available timeslots when a staff member is selected
    document.getElementById('staff').addEventListener('change', function() {
        var staffId = this.value;
        
        if (staffId) {
            jQuery.post(sodBooking.ajax_url, {
                action: 'get_timeslots', // Ensure this action is registered in PHP
                nonce: sodBooking.nonce,
                staff_id: staffId
            }, function(response) {
                if (response.success) {
                    populateSelectField('timeslot', response.data, 'Select Time Slot');
                } else {
                    alert('Failed to load timeslots.');
                }
            });
        }
    });

    // Utility function to populate a select field
    function populateSelectField(fieldId, items, placeholder) {
        var select = document.getElementById(fieldId);
        select.innerHTML = '<option value="">' + placeholder + '</option>';

        items.forEach(function(item) {
            var option = document.createElement('option');
            option.value = item.id; // Ensure this matches the correct property for ID
            option.text = item.name; // Ensure this matches the correct property for display name
            select.appendChild(option);
        });
    }
});