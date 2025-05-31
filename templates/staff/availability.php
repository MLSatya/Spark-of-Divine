<?php
/**
 * Staff Availability Template
 */
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="sod-staff-availability">
    <div class="sod-availability-header">
        <h2><?php _e('Manage Your Availability', 'spark-of-divine-scheduler'); ?></h2>
        <p><?php _e('Configure when you are available to provide services.', 'spark-of-divine-scheduler'); ?></p>
    </div>
    
    <!-- Add New Availability -->
    <div class="sod-availability-section">
        <h3><?php _e('Add New Availability', 'spark-of-divine-scheduler'); ?></h3>
        
        <form id="sod-add-availability-form" class="sod-availability-form">
            <div class="sod-form-row">
                <label for="product_id"><?php _e('Service:', 'spark-of-divine-scheduler'); ?></label>
                <select id="product_id" name="product_id" required>
                    <option value=""><?php _e('Select Service', 'spark-of-divine-scheduler'); ?></option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?php echo esc_attr($product->ID); ?>">
                            <?php echo esc_html($product->post_title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="sod-form-row">
                <label for="availability_type"><?php _e('Availability Type:', 'spark-of-divine-scheduler'); ?></label>
                <select id="availability_type" name="availability_type">
                    <option value="recurring"><?php _e('Weekly Recurring', 'spark-of-divine-scheduler'); ?></option>
                    <option value="specific"><?php _e('Specific Date', 'spark-of-divine-scheduler'); ?></option>
                </select>
            </div>
            
            <div class="sod-form-row recurring-fields">
                <label for="day_of_week"><?php _e('Day of Week:', 'spark-of-divine-scheduler'); ?></label>
                <select id="day_of_week" name="day_of_week">
                    <option value="Monday"><?php _e('Monday', 'spark-of-divine-scheduler'); ?></option>
                    <option value="Tuesday"><?php _e('Tuesday', 'spark-of-divine-scheduler'); ?></option>
                    <option value="Wednesday"><?php _e('Wednesday', 'spark-of-divine-scheduler'); ?></option>
                    <option value="Thursday"><?php _e('Thursday', 'spark-of-divine-scheduler'); ?></option>
                    <option value="Friday"><?php _e('Friday', 'spark-of-divine-scheduler'); ?></option>
                    <option value="Saturday"><?php _e('Saturday', 'spark-of-divine-scheduler'); ?></option>
                    <option value="Sunday"><?php _e('Sunday', 'spark-of-divine-scheduler'); ?></option>
                </select>
            </div>
            
            <div class="sod-form-row recurring-fields">
                <label for="recurring_type"><?php _e('Recurrence Pattern:', 'spark-of-divine-scheduler'); ?></label>
                <select id="recurring_type" name="recurring_type">
                    <option value="weekly"><?php _e('Every Week', 'spark-of-divine-scheduler'); ?></option>
                    <option value="biweekly"><?php _e('Every Two Weeks', 'spark-of-divine-scheduler'); ?></option>
                    <option value="monthly"><?php _e('Monthly (Same Day)', 'spark-of-divine-scheduler'); ?></option>
                </select>
            </div>
            
            <div class="sod-form-row recurring-fields">
                <label for="recurring_end_date"><?php _e('End Date (Optional):', 'spark-of-divine-scheduler'); ?></label>
                <input type="text" id="recurring_end_date" name="recurring_end_date" class="datepicker" placeholder="<?php _e('Never', 'spark-of-divine-scheduler'); ?>">
            </div>
            
            <div class="sod-form-row specific-fields" style="display: none;">
                <label for="specific_date"><?php _e('Date:', 'spark-of-divine-scheduler'); ?></label>
                <input type="text" id="specific_date" name="specific_date" class="datepicker" placeholder="<?php _e('Select a date', 'spark-of-divine-scheduler'); ?>">
            </div>
            
            <div class="sod-form-row">
                <label for="start_time"><?php _e('Start Time:', 'spark-of-divine-scheduler'); ?></label>
                <input type="time" id="start_time" name="start_time" required>
            </div>
            
            <div class="sod-form-row">
                <label for="end_time"><?php _e('End Time:', 'spark-of-divine-scheduler'); ?></label>
                <input type="time" id="end_time" name="end_time" required>
            </div>
            
            <div class="sod-form-actions">
                <button type="submit" class="sod-btn sod-btn-primary"><?php _e('Add Availability', 'spark-of-divine-scheduler'); ?></button>
                <span class="sod-form-spinner" style="display: none;"></span>
            </div>
        </form>
    </div>
    
    <!-- Recurring Availability -->
    <div class="sod-availability-section">
        <h3><?php _e('Recurring Weekly Availability', 'spark-of-divine-scheduler'); ?></h3>
        
        <?php if (empty($availability['recurring'])): ?>
            <p class="sod-no-items"><?php _e('No recurring availability set. Add some above!', 'spark-of-divine-scheduler'); ?></p>
        <?php else: ?>
            <div class="sod-availability-table-wrapper">
                <table class="sod-availability-table">
                    <thead>
                        <tr>
                            <th><?php _e('Day', 'spark-of-divine-scheduler'); ?></th>
                            <th><?php _e('Time', 'spark-of-divine-scheduler'); ?></th>
                            <th><?php _e('Service', 'spark-of-divine-scheduler'); ?></th>
                            <th><?php _e('Pattern', 'spark-of-divine-scheduler'); ?></th>
                            <th><?php _e('End Date', 'spark-of-divine-scheduler'); ?></th>
                            <th><?php _e('Actions', 'spark-of-divine-scheduler'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($availability['recurring'] as $slot): ?>
                            <tr data-id="<?php echo esc_attr($slot->id); ?>">
                                <td><?php echo esc_html($slot->day_of_week); ?></td>
                                <td>
                                    <?php 
                                    echo esc_html(date_i18n(get_option('time_format'), strtotime($slot->start_time))) . ' - ' . 
                                         esc_html(date_i18n(get_option('time_format'), strtotime($slot->end_time)));
                                    ?>
                                </td>
                                <td><?php echo esc_html($slot->product_name); ?></td>
                                <td>
                                    <?php 
                                    switch ($slot->recurring_type) {
                                        case 'weekly':
                                            _e('Every Week', 'spark-of-divine-scheduler');
                                            break;
                                        case 'biweekly':
                                            _e('Every Two Weeks', 'spark-of-divine-scheduler');
                                            break;
                                        case 'monthly':
                                            _e('Monthly', 'spark-of-divine-scheduler');
                                            break;
                                        default:
                                            _e('Every Week', 'spark-of-divine-scheduler');
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    echo !empty($slot->recurring_end_date) 
                                        ? esc_html(date_i18n(get_option('date_format'), strtotime($slot->recurring_end_date)))
                                        : __('None', 'spark-of-divine-scheduler');
                                    ?>
                                </td>
                                <td class="sod-action-cell">
                                    <button class="sod-action-btn sod-edit-availability" 
                                            data-id="<?php echo esc_attr($slot->id); ?>"
                                            data-start="<?php echo esc_attr($slot->start_time); ?>"
                                            data-end="<?php echo esc_attr($slot->end_time); ?>"
                                            data-end-date="<?php echo esc_attr($slot->recurring_end_date); ?>">
                                        <?php _e('Edit', 'spark-of-divine-scheduler'); ?>
                                    </button>
                                    <button class="sod-action-btn sod-delete-availability" 
                                            data-id="<?php echo esc_attr($slot->id); ?>">
                                        <?php _e('Delete', 'spark-of-divine-scheduler'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Specific Date Availability -->
    <div class="sod-availability-section">
        <h3><?php _e('Specific Date Availability', 'spark-of-divine-scheduler'); ?></h3>
        
        <?php if (empty($availability['specific_dates'])): ?>
            <p class="sod-no-items"><?php _e('No specific date availability set. Add some above!', 'spark-of-divine-scheduler'); ?></p>
        <?php else: ?>
            <div class="sod-availability-table-wrapper">
                <table class="sod-availability-table">
                    <thead>
                        <tr>
                            <th><?php _e('Date', 'spark-of-divine-scheduler'); ?></th>
                            <th><?php _e('Time', 'spark-of-divine-scheduler'); ?></th>
                            <th><?php _e('Service', 'spark-of-divine-scheduler'); ?></th>
                            <th><?php _e('Actions', 'spark-of-divine-scheduler'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($availability['specific_dates'] as $slot): ?>
                            <tr data-id="<?php echo esc_attr($slot->id); ?>">
                                <td>
                                    <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($slot->date))); ?>
                                </td>
                                <td>
                                    <?php 
                                    echo esc_html(date_i18n(get_option('time_format'), strtotime($slot->start_time))) . ' - ' . 
                                         esc_html(date_i18n(get_option('time_format'), strtotime($slot->end_time)));
                                    ?>
                                </td>
                                <td><?php echo esc_html($slot->product_name); ?></td>
                                <td class="sod-action-cell">
                                    <button class="sod-action-btn sod-edit-availability" 
                                            data-id="<?php echo esc_attr($slot->id); ?>"
                                            data-start="<?php echo esc_attr($slot->start_time); ?>"
                                            data-end="<?php echo esc_attr($slot->end_time); ?>">
                                        <?php _e('Edit', 'spark-of-divine-scheduler'); ?>
                                    </button>
                                    <button class="sod-action-btn sod-delete-availability" 
                                            data-id="<?php echo esc_attr($slot->id); ?>">
                                        <?php _e('Delete', 'spark-of-divine-scheduler'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Modal templates -->
    <div id="sod-modal-container" class="sod-modal-container">
        <!-- Edit Availability Modal -->
        <div id="sod-edit-availability-modal" class="sod-modal">
            <div class="sod-modal-content">
                <div class="sod-modal-header">
                    <h3><?php _e('Edit Availability', 'spark-of-divine-scheduler'); ?></h3>
                    <button type="button" class="sod-modal-close">&times;</button>
                </div>
                <div class="sod-modal-body">
                    <form id="sod-edit-availability-form">
                        <input type="hidden" id="edit_slot_id" name="slot_id">
                        
                        <div class="sod-form-row">
                            <label for="edit_start_time"><?php _e('Start Time:', 'spark-of-divine-scheduler'); ?></label>
                            <input type="time" id="edit_start_time" name="start_time" required>
                        </div>
                        
                        <div class="sod-form-row">
                            <label for="edit_end_time"><?php _e('End Time:', 'spark-of-divine-scheduler'); ?></label>
                            <input type="time" id="edit_end_time" name="end_time" required>
                        </div>
                        
                        <div class="sod-form-row recurring-edit-field">
                            <label for="edit_recurring_end_date"><?php _e('End Date:', 'spark-of-divine-scheduler'); ?></label>
                            <input type="text" id="edit_recurring_end_date" name="recurring_end_date" class="datepicker" placeholder="<?php _e('Never', 'spark-of-divine-scheduler'); ?>">
                        </div>
                    </form>
                </div>
                <div class="sod-modal-footer">
                    <button type="button" class="sod-btn sod-btn-cancel"><?php _e('Cancel', 'spark-of-divine-scheduler'); ?></button>
                    <button type="button" class="sod-btn sod-btn-confirm"><?php _e('Save Changes', 'spark-of-divine-scheduler'); ?></button>
                </div>
            </div>
        </div>
        
        <!-- Delete Availability Modal -->
        <div id="sod-delete-availability-modal" class="sod-modal">
            <div class="sod-modal-content">
                <div class="sod-modal-header">
                    <h3><?php _e('Delete Availability', 'spark-of-divine-scheduler'); ?></h3>
                    <button type="button" class="sod-modal-close">&times;</button>
                </div>
                <div class="sod-modal-body">
                    <p><?php _e('Are you sure you want to delete this availability slot?', 'spark-of-divine-scheduler'); ?></p>
                    <input type="hidden" id="delete_slot_id">
                </div>
                <div class="sod-modal-footer">
                    <button type="button" class="sod-btn sod-btn-cancel"><?php _e('Cancel', 'spark-of-divine-scheduler'); ?></button>
                    <button type="button" class="sod-btn sod-btn-confirm"><?php _e('Delete', 'spark-of-divine-scheduler'); ?></button>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Initialize datepickers
    $('.datepicker').datepicker({
        dateFormat: 'yy-mm-dd',
        minDate: 0,
        changeMonth: true,
        changeYear: true
    });
    
    // Toggle fields based on availability type
    $('#availability_type').on('change', function() {
        if ($(this).val() === 'recurring') {
            $('.recurring-fields').show();
            $('.specific-fields').hide();
            $('#specific_date').prop('required', false);
            $('#day_of_week').prop('required', true);
        } else {
            $('.recurring-fields').hide();
            $('.specific-fields').show();
            $('#specific_date').prop('required', true);
            $('#day_of_week').prop('required', false);
        }
    });
    
    // Add new availability slot
    $('#sod-add-availability-form').on('submit', function(e) {
        e.preventDefault();
        
        var formData = {
            action: 'sod_staff_update_availability',
            nonce: sod_staff_dashboard.nonce,
            type: 'add',
            product_id: $('#product_id').val()
        };
        
        // Add appropriate fields based on availability type
        if ($('#availability_type').val() === 'recurring') {
            formData.day_of_week = $('#day_of_week').val();
            formData.recurring_type = $('#recurring_type').val();
            formData.recurring_end_date = $('#recurring_end_date').val();
        } else {
            formData.specific_date = $('#specific_date').val();
        }
        
        // Add common fields
        formData.start_time = $('#start_time').val();
        formData.end_time = $('#end_time').val();
        
        // Show loading spinner
        $('.sod-form-spinner').show();
        $('#sod-add-availability-form button[type="submit"]').prop('disabled', true);
        
        $.ajax({
            url: sod_staff_dashboard.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    // Reload the page to show the new availability
                    location.reload();
                } else {
                    alert(response.data.message || sod_staff_dashboard.i18n.errorMessage);
                    $('.sod-form-spinner').hide();
                    $('#sod-add-availability-form button[type="submit"]').prop('disabled', false);
                }
            },
            error: function() {
                alert(sod_staff_dashboard.i18n.errorMessage);
                $('.sod-form-spinner').hide();
                $('#sod-add-availability-form button[type="submit"]').prop('disabled', false);
            }
        });
    });
    
    // Open edit modal
    $('.sod-edit-availability').on('click', function() {
        var $row = $(this).closest('tr');
        var slotId = $row.data('id');
        var startTime = $(this).data('start');
        var endTime = $(this).data('end');
        var endDate = $(this).data('end-date');
        
        // Set the form values
        $('#edit_slot_id').val(slotId);
        $('#edit_start_time').val(startTime.substring(0, 5)); // Format HH:MM
        $('#edit_end_time').val(endTime.substring(0, 5)); // Format HH:MM
        
        // Set end date if applicable
        if (endDate) {
            $('#edit_recurring_end_date').val(endDate);
            $('.recurring-edit-field').show();
        } else {
            $('#edit_recurring_end_date').val('');
            // Only show for recurring slots (check if the row has a day column)
            if ($row.children().length > 4) {
                $('.recurring-edit-field').show();
            } else {
                $('.recurring-edit-field').hide();
            }
        }
        
        // Show the modal
        $('.sod-modal-container').addClass('active');
        $('#sod-edit-availability-modal').addClass('active');
    });
    
    // Save edited availability
    $('#sod-edit-availability-modal .sod-btn-confirm').on('click', function() {
        var slotId = $('#edit_slot_id').val();
        var startTime = $('#edit_start_time').val();
        var endTime = $('#edit_end_time').val();
        var recurringEndDate = $('#edit_recurring_end_date').val();
        
        // Validate
        if (!startTime || !endTime) {
            alert('<?php _e('Please enter both start and end times', 'spark-of-divine-scheduler'); ?>');
            return;
        }
        
        $(this).prop('disabled', true);
        
        $.ajax({
            url: sod_staff_dashboard.ajax_url,
            type: 'POST',
            data: {
                action: 'sod_staff_update_availability',
                nonce: sod_staff_dashboard.nonce,
                type: 'update',
                slot_id: slotId,
                start_time: startTime,
                end_time: endTime,
                recurring_end_date: recurringEndDate
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || sod_staff_dashboard.i18n.errorMessage);
                    $('#sod-edit-availability-modal .sod-btn-confirm').prop('disabled', false);
                }
            },
            error: function() {
                alert(sod_staff_dashboard.i18n.errorMessage);
                $('#sod-edit-availability-modal .sod-btn-confirm').prop('disabled', false);
            }
        });
    });
    
    // Open delete confirmation modal
    $('.sod-delete-availability').on('click', function() {
        var slotId = $(this).data('id');
        $('#delete_slot_id').val(slotId);
        
        // Show the modal
        $('.sod-modal-container').addClass('active');
        $('#sod-delete-availability-modal').addClass('active');
    });
    
    // Delete availability
    $('#sod-delete-availability-modal .sod-btn-confirm').on('click', function() {
        var slotId = $('#delete_slot_id').val();
        
        $(this).prop('disabled', true);
        
        $.ajax({
            url: sod_staff_dashboard.ajax_url,
            type: 'POST',
            data: {
                action: 'sod_staff_update_availability',
                nonce: sod_staff_dashboard.nonce,
                type: 'delete',
                slot_id: slotId
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || sod_staff_dashboard.i18n.errorMessage);
                    $('#sod-delete-availability-modal .sod-btn-confirm').prop('disabled', false);
                }
            },
            error: function() {
                alert(sod_staff_dashboard.i18n.errorMessage);
                $('#sod-delete-availability-modal .sod-btn-confirm').prop('disabled', false);
            }
        });
    });
    
    // Close modals
    $('.sod-modal-close, .sod-btn-cancel').on('click', function() {
        $('.sod-modal-container').removeClass('active');
        $('.sod-modal').removeClass('active');
    });
    
    // Close when clicking outside modal
    $('.sod-modal').on('click', function(e) {
        if ($(e.target).hasClass('sod-modal')) {
            $('.sod-modal-container').removeClass('active');
            $('.sod-modal').removeClass('active');
        }
    });
});
</script>