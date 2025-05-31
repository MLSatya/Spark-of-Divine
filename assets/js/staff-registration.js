// staff-registration.js
jQuery(document).ready(function ($) {
    // Add a new service entry
    $('#add-service-entry').on('click', function (e) {
        e.preventDefault();
        var newEntry = `
            <div class="service-entry">
                <div class="sod-form-group">
                    <label>Service Name</label>
                    <input type="text" name="service_name[]" class="sod-form-input" required />
                </div>
                <div class="sod-form-group">
                    <label>Category</label>
                    <input type="text" name="service_category[]" class="sod-form-input" />
                </div>
                <button type="button" class="button button-secondary remove-service-entry">Remove Service</button>
            </div>
        `;
        $('#service-entries').append(newEntry);
    });

    // Remove a service entry
    $('#service-entries').on('click', '.remove-service-entry', function (e) {
        e.preventDefault();
        $(this).closest('.service-entry').remove();
    });

    // Handle the form submission via AJAX
    $('#sod-staff-registration-form').on('submit', function (e) {
        e.preventDefault();

        var formData = new FormData(this);
        formData.append('action', 'save_staff_registration');
        formData.append('nonce', sodRegistration.nonce);

        $.ajax({
            url: sodRegistration.ajax_url,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                if (response.success) {
                    alert(response.data);
                    window.location.href = '/staff-availability/';
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function (xhr, status, error) {
                console.error('AJAX Error:', status, error);
                alert('An error occurred while registering the staff member. Please try again.');
            }
        });
    });
});