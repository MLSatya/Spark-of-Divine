<?php
/**
 * Return to Shop Button Template
 * 
 * @package Spark_Of_Divine_Scheduler
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get shop URL from variable set in the method
$shop_url = isset($shop_page_url) ? $shop_page_url : get_permalink(wc_get_page_id('shop'));
?>

<style>
.sod-return-to-shop {
    text-align: center;
    margin: 20px 0;
}
.sod-return-to-shop .button {
    background: #333;
    color: #fff;
    padding: 10px 20px;
    text-decoration: none;
    display: inline-block;
}
</style>

<div class="sod-return-to-shop">
    <a href="<?php echo esc_url($shop_url); ?>" class="button">
        <?php _e('← Return to Schedule', 'spark-of-divine-scheduler'); ?>
    </a>
</div>
