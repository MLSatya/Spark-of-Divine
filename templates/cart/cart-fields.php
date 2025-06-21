<?php
/**
 * Cart Contact Fields Template
 * 
 * @package Spark_Of_Divine_Scheduler
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get session data
$first_name = WC()->session->get('sod_cart_first_name', '');
$last_name = WC()->session->get('sod_cart_last_name', '');
$email = WC()->session->get('sod_cart_email', '');
$phone = WC()->session->get('sod_cart_phone', '');

// Don't show if all fields are already filled
if ($first_name && $last_name && $email && $phone) {
    ?>
    <div class="sod-cart-contact-saved woocommerce-message">
        <?php _e('Contact information saved. You can proceed to checkout.', 'spark-of-divine-scheduler'); ?>
    </div>
    <?php
    return;
}
?>

<div class="sod-cart-contact-info">
    <h3><?php _e('Contact Information', 'spark-of-divine-scheduler'); ?></h3>
    <p><?php _e('Please provide your contact details to continue with checkout.', 'spark-of-divine-scheduler'); ?></p>
    
    <form id="sod-contact-form" class="sod-contact-form">
        <p class="form-row form-row-first">
            <label for="first_name"><?php _e('First Name', 'spark-of-divine-scheduler'); ?> <abbr class="required" title="required">*</abbr></label>
            <input type="text" class="input-text" name="first_name" id="first_name" value="<?php echo esc_attr($first_name); ?>" required />
        </p>
        
        <p class="form-row form-row-last">
            <label for="last_name"><?php _e('Last Name', 'spark-of-divine-scheduler'); ?> <abbr class="required" title="required">*</abbr></label>
            <input type="text" class="input-text" name="last_name" id="last_name" value="<?php echo esc_attr($last_name); ?>" required />
        </p>
        
        <p class="form-row form-row-wide">
            <label for="email"><?php _e('Email Address', 'spark-of-divine-scheduler'); ?> <abbr class="required" title="required">*</abbr></label>
            <input type="email" class="input-text" name="email" id="email" value="<?php echo esc_attr($email); ?>" required />
        </p>
        
        <p class="form-row form-row-wide">
            <label for="phone"><?php _e('Phone Number', 'spark-of-divine-scheduler'); ?> <abbr class="required" title="required">*</abbr></label>
            <input type="tel" class="input-text" name="phone" id="phone" value="<?php echo esc_attr($phone); ?>" required />
        </p>
        
        <p class="form-row">
            <button type="submit" class="button alt"><?php _e('Save Contact Information', 'spark-of-divine-scheduler'); ?></button>
        </p>
    </form>
</div>
