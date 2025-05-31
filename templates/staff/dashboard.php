<?php
/**
 * Staff Dashboard Template
 */
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="sod-staff-dashboard">
    <div class="sod-dashboard-header">
        <h2><?php _e('Welcome to Your Staff Dashboard', 'spark-of-divine-scheduler'); ?></h2>
        <p><?php _e('Manage your bookings, track your revenue, and more.', 'spark-of-divine-scheduler'); ?></p>
    </div>
    
    <!-- Action Items -->
    <?php if (!empty($pending_bookings)): ?>
    <div class="sod-dashboard-section sod-action-items">
        <h3><?php _e('Action Required', 'spark-of-divine-scheduler'); ?></h3>
        <div class="sod-action-items-list">
            <?php foreach ($pending_bookings as $booking): ?>
                <div class="sod-action-item">
                    <div class="sod-action-item-details">
                        <span class="sod-action-item-title">
                            <?php echo esc_html($booking->customer_name); ?> - 
                            <?php echo esc_html($booking->product_name ?: $booking->service_name); ?>
                        </span>
                        <span class="sod-action-item-date">
                            <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($booking->start_time))); ?>
                            <?php echo esc_html(date_i18n(get_option('time_format'), strtotime($booking->start_time))); ?>
                        </span>
                    </div>
                    <div class="sod-action-item-actions">
                        <button class="sod-action-btn sod-confirm-booking" data-booking-id="<?php echo esc_attr($booking->booking_id); ?>">
                            <?php _e('Confirm', 'spark-of-divine-scheduler'); ?>
                        </button>
                        <button class="sod-action-btn sod-reschedule-booking" data-booking-id="<?php echo esc_attr($booking->booking_id); ?>">
                            <?php _e('Reschedule', 'spark-of-divine-scheduler'); ?>
                        </button>
                        <button class="sod-action-btn sod-cancel-booking" data-booking-id="<?php echo esc_attr($booking->booking_id); ?>">
                            <?php _e('Cancel', 'spark-of-divine-scheduler'); ?>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Dashboard Widgets -->
    <div class="sod-dashboard-widgets">
        <div class="sod-dashboard-widget sod-upcoming-bookings">
            <div class="sod-widget-header">
                <h3><?php _e('Upcoming Bookings', 'spark-of-divine-scheduler'); ?></h3>
                <a href="<?php echo esc_url(wc_get_account_endpoint_url('staff-bookings')); ?>" class="sod-view-all">
                    <?php _e('View All', 'spark-of-divine-scheduler'); ?>
                </a>
            </div>
            
            <?php if (empty($upcoming_bookings)): ?>
                <p class="sod-no-items"><?php _e('No upcoming bookings', 'spark-of-divine-scheduler'); ?></p>
            <?php else: ?>
                <div class="sod-booking-list">
                    <?php foreach ($upcoming_bookings as $booking): ?>
                        <div class="sod-booking-item">
                            <div class="sod-booking-date">
                                <div class="sod-booking-day"><?php echo esc_html(date_i18n('d', strtotime($booking->start_time))); ?></div>
                                <div class="sod-booking-month"><?php echo esc_html(date_i18n('M', strtotime($booking->start_time))); ?></div>
                            </div>
                            <div class="sod-booking-details">
                                <div class="sod-booking-service">
                                    <?php echo esc_html($booking->product_name ?: $booking->service_name); ?>
                                </div>
                                <div class="sod-booking-customer">
                                    <?php echo esc_html($booking->customer_name); ?>
                                </div>
                                <div class="sod-booking-time">
                                    <?php echo esc_html(date_i18n(get_option('time_format'), strtotime($booking->start_time))); ?> - 
                                    <?php echo esc_html(date_i18n(get_option('time_format'), strtotime($booking->end_time))); ?>
                                </div>
                                <div class="sod-booking-status">
                                    <span class="sod-status-badge status-<?php echo esc_attr($booking->status); ?>">
                                        <?php echo esc_html(ucfirst($booking->status)); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="sod-dashboard-widget sod-revenue-summary">
            <div class="sod-widget-header">
                <h3><?php _e('Revenue Overview', 'spark-of-divine-scheduler'); ?></h3>
                <a href="<?php echo esc_url(wc_get_account_endpoint_url('staff-revenue')); ?>" class="sod-view-all">
                    <?php _e('View Details', 'spark-of-divine-scheduler'); ?>
                </a>
            </div>
            
            <div class="sod-revenue-stats">
                <div class="sod-revenue-stat">
                    <div class="sod-stat-label"><?php _e('This Month', 'spark-of-divine-scheduler'); ?></div>
                    <div class="sod-stat-value"><?php echo wc_price($revenue['current_month']['staff_portion']); ?></div>
                    <div class="sod-stat-caption"><?php _e('Your portion (65%)', 'spark-of-divine-scheduler'); ?></div>
                </div>
                
                <div class="sod-revenue-stat">
                    <div class="sod-stat-label"><?php _e('Last Month', 'spark-of-divine-scheduler'); ?></div>
                    <div class="sod-stat-value"><?php echo wc_price($revenue['previous_month']['staff_portion']); ?></div>
                    <div class="sod-stat-caption"><?php _e('Your portion (65%)', 'spark-of-divine-scheduler'); ?></div>
                </div>
                
                <div class="sod-revenue-stat">
                    <div class="sod-stat-label"><?php _e('Year to Date', 'spark-of-divine-scheduler'); ?></div>
                    <div class="sod-stat-value"><?php echo wc_price($revenue['year_to_date']['staff_portion']); ?></div>
                    <div class="sod-stat-caption"><?php _e('Your portion (65%)', 'spark-of-divine-scheduler'); ?></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal templates -->
    <div id="sod-modal-container" class="sod-modal-container">
        <!-- Confirm Booking Modal -->
        <div id="sod-confirm-booking-modal" class="sod-modal">
            <div class="sod-modal-content">
                <div class="sod-modal-header">
                    <h3><?php _e('Confirm Booking', 'spark-of-divine-scheduler'); ?></h3>
                    <button type="button" class="sod-modal-close">&times;</button>
                </div>
                <div class="sod-modal-body">
                    <p><?php _e('Are you sure you want to confirm this booking?', 'spark-of-divine-scheduler'); ?></p>
                </div>
                <div class="sod-modal-footer">
                    <button type="button" class="sod-btn sod-btn-cancel"><?php _e('Cancel', 'spark-of-divine-scheduler'); ?></button>
                    <button type="button" class="sod-btn sod-btn-confirm"><?php _e('Confirm', 'spark-of-divine-scheduler'); ?></button>
                </div>
            </div>
        </div>
        
        <!-- Reschedule Booking Modal -->
        <div id="sod-reschedule-booking-modal" class="sod-modal">
            <div class="sod-modal-content">
                <div class="sod-modal-header">
                    <h3><?php _e('Reschedule Booking', 'spark-of-divine-scheduler'); ?></h3>
                    <button type="button" class="sod-modal-close">&times;</button>
                </div>
                <div class="sod-modal-body">
                    <div class="sod-form-row">
                        <label for="sod-new-date"><?php _e('New Date:', 'spark-of-divine-scheduler'); ?></label>
                        <input type="date" id="sod-new-date" name="new_date" 
                               min="<?php echo esc_attr(date('Y-m-d')); ?>" required>
                    </div>
                    <div class="sod-form-row">
                        <label for="sod-new-time"><?php _e('New Time:', 'spark-of-divine-scheduler'); ?></label>
                        <input type="time" id="sod-new-time" name="new_time" required>
                    </div>
                    <div class="sod-form-row">
                        <label for="sod-reschedule-message"><?php _e('Message to Customer:', 'spark-of-divine-scheduler'); ?></label>
                        <textarea id="sod-reschedule-message" name="message" rows="3"></textarea>
                    </div>
                </div>
                <div class="sod-modal-footer">
                    <button type="button" class="sod-btn sod-btn-cancel"><?php _e('Cancel', 'spark-of-divine-scheduler'); ?></button>
                    <button type="button" class="sod-btn sod-btn-confirm"><?php _e('Reschedule', 'spark-of-divine-scheduler'); ?></button>
                </div>
            </div>
        </div>
        
        <!-- Cancel Booking Modal -->
        <div id="sod-cancel-booking-modal" class="sod-modal">
            <div class="sod-modal-content">
                <div class="sod-modal-header">
                    <h3><?php _e('Cancel Booking', 'spark-of-divine-scheduler'); ?></h3>
                    <button type="button" class="sod-modal-close">&times;</button>
                </div>
                <div class="sod-modal-body">
                    <p><?php _e('Are you sure you want to cancel this booking?', 'spark-of-divine-scheduler'); ?></p>
                    <div class="sod-form-row">
                        <label for="sod-cancel-reason"><?php _e('Reason for Cancellation:', 'spark-of-divine-scheduler'); ?></label>
                        <textarea id="sod-cancel-reason" name="reason" rows="3"></textarea>
                    </div>
                </div>
                <div class="sod-modal-footer">
                    <button type="button" class="sod-btn sod-btn-cancel"><?php _e('Back', 'spark-of-divine-scheduler'); ?></button>
                    <button type="button" class="sod-btn sod-btn-confirm"><?php _e('Cancel Booking', 'spark-of-divine-scheduler'); ?></button>
                </div>
            </div>
        </div>
    </div>
</div>