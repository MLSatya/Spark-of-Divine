<?php
/**
 * Payment Split Handler
 * Manages the 35% store / 65% practitioner payment split
 */

if (!defined('ABSPATH')) {
    exit;
}

class SOD_Payment_Split_Handler {
    
    const STORE_PERCENTAGE = 0.35;
    const PRACTITIONER_PERCENTAGE = 0.65;
    
    private static $instance = null;
    
    public static function getInstance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Modify cart item prices to show only deposit
        add_filter('woocommerce_cart_item_price', [$this, 'show_deposit_in_cart'], 10, 3);
        add_filter('woocommerce_cart_item_subtotal', [$this, 'show_deposit_subtotal'], 10, 3);
        
        // Add deposit info to order items
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'add_deposit_meta_to_order'], 10, 4);
        
        // Display payment split in order emails
        add_action('woocommerce_email_after_order_table', [$this, 'add_payment_split_to_email'], 10, 4);
    }
    
    /**
     * Calculate payment split
     */
    public function calculate_split($total_amount) {
        return [
            'total' => $total_amount,
            'deposit' => round($total_amount * self::STORE_PERCENTAGE, 2),
            'balance' => round($total_amount * self::PRACTITIONER_PERCENTAGE, 2)
        ];
    }
    
    /**
     * Show deposit amount in cart
     */
    public function show_deposit_in_cart($price, $cart_item, $cart_item_key) {
        $product = $cart_item['data'];
        $full_price = $product->get_price();
        $split = $this->calculate_split($full_price);
        
        // Temporarily set the price to deposit amount
        $product->set_price($split['deposit']);
        
        // Add deposit info to display
        $price_html = wc_price($split['deposit']);
        $price_html .= '<br><small class="deposit-note">Deposit (35% of ' . wc_price($full_price) . ')</small>';
        
        return $price_html;
    }
    
    /**
     * Show deposit subtotal
     */
    public function show_deposit_subtotal($subtotal, $cart_item, $cart_item_key) {
        $quantity = $cart_item['quantity'];
        $product = $cart_item['data'];
        $full_price = $product->get_price();
        $split = $this->calculate_split($full_price * $quantity);
        
        $subtotal_html = wc_price($split['deposit']);
        $subtotal_html .= '<br><small class="deposit-note">Deposit for ' . $quantity . ' session(s)</small>';
        
        return $subtotal_html;
    }
    
    /**
     * Add deposit meta to order items
     */
    public function add_deposit_meta_to_order($item, $cart_item_key, $values, $order) {
        $product = $values['data'];
        $full_price = $product->get_price();
        $split = $this->calculate_split($full_price);
        
        $item->add_meta_data('_full_price', $full_price);
        $item->add_meta_data('_deposit_amount', $split['deposit']);
        $item->add_meta_data('_balance_due', $split['balance']);
        $item->add_meta_data('_deposit_percentage', self::STORE_PERCENTAGE * 100 . '%');
    }
    
    /**
     * Add payment split info to emails
     */
    public function add_payment_split_to_email($order, $sent_to_admin, $plain_text, $email) {
        if ($plain_text) {
            echo "\n\n=== PAYMENT INFORMATION ===\n";
            echo "This order total represents a 35% deposit.\n";
            echo "The remaining 65% balance is to be paid directly to the practitioner.\n";
        } else {
            ?>
            <div style="margin: 20px 0; padding: 15px; background: #f0f8ff; border: 1px solid #b0d4ff;">
                <h3 style="margin-top: 0;">Payment Information</h3>
                <p>This order total represents a <strong>35% deposit</strong>.</p>
                <p>The remaining <strong>65% balance</strong> is to be paid directly to the practitioner at the time of service.</p>
            </div>
            <?php
        }
    }
}

// Initialize
SOD_Payment_Split_Handler::getInstance();
