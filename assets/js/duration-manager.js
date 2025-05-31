// Duration Manager Script
jQuery(document).ready(function($) {
    var $table = $('#service-durations-table');
    
    // Add new row
    $('.add-duration-row').on('click', function() {
        var $lastRow = $table.find('tbody tr:last');
        var $newRow = $lastRow.clone(true);
        
        // Clear values in the new row
        $newRow.find('input').val('');
        
        $table.find('tbody').append($newRow);
    });
    
    // Remove row
    $('.remove-duration-row').on('click', function() {
        var $row = $(this).closest('tr');
        if ($table.find('tbody tr').length > 1) {
            $row.remove();
        } else {
            // Clear values if it's the last row
            $row.find('input').val('');
        }
    });
});
