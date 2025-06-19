/**
 * Payment Split Handler
 * Manages 35% deposit / 65% balance calculations
 */
jQuery(document).ready(function($) {
    
    // Calculate and display payment split
    function calculatePaymentSplit(totalAmount) {
        const deposit = (totalAmount * 0.35).toFixed(2);
        const balance = (totalAmount * 0.65).toFixed(2);
        
        return {
            total: totalAmount,
            deposit: deposit,
            balance: balance
        };
    }
    
    // Update booking form to show deposit amount
    function updateBookingFormPrices() {
        $('.booking-form select[name="attribute"]').on('change', function() {
            const $option = $(this).find('option:selected');
            const priceText = $option.text();
            const priceMatch = priceText.match(/\$(\d+\.?\d*)/);
            
            if (priceMatch) {
                const totalPrice = parseFloat(priceMatch[1]);
                const split = calculatePaymentSplit(totalPrice);
                
                // Add or update deposit display
                let $depositInfo = $(this).closest('.booking-form').find('.sod-deposit-info');
                if (!$depositInfo.length) {
                    $depositInfo = $('<div class="sod-deposit-info"></div>');
                    $(this).closest('.booking-form-row').after($depositInfo);
                }
                
                $depositInfo.html(`
                    <div class="payment-split-info">
                        <p class="deposit-amount">Deposit Due Now: <strong>$${split.deposit}</strong> (35%)</p>
                        <p class="balance-amount">Balance Due to Practitioner: <strong>$${split.balance}</strong> (65%)</p>
                    </div>
                `);
            }
        });
    }
    
    // Initialize on page load
    updateBookingFormPrices();
    
    // Reinitialize after AJAX loads
    $(document).on('sod_schedule_loaded', function() {
        updateBookingFormPrices();
    });
});
