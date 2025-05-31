<?php
$type = isset($type) ? $type : '';
$key = isset($key) ? $key : '';
?>
<tr class="attribute-row" data-type="<?php echo esc_attr($type); ?>" data-key="<?php echo esc_attr($key); ?>">
    <td><?php echo ucfirst($type); ?></td>
    <td>
        <?php if ($type === 'package' || $type === 'custom'): ?>
            <input type="text" name="sod_attribute[<?php echo esc_attr($type); ?>][<?php echo esc_attr($key); ?>][value]" value="" class="regular-text">
        <?php else: ?>
            <input type="number" name="sod_attribute[<?php echo esc_attr($type); ?>][<?php echo esc_attr($key); ?>][value]" 
                   value="<?php echo $type === 'duration' ? '60' : '1'; ?>" min="<?php echo $type === 'duration' ? '5' : '1'; ?>" 
                   step="<?php echo $type === 'duration' ? '5' : '1'; ?>" class="small-text">
            <span><?php echo $type === 'duration' ? 'minutes' : 'passes'; ?></span>
        <?php endif; ?>
    </td>
    <td><input type="number" name="sod_attribute[<?php echo esc_attr($type); ?>][<?php echo esc_attr($key); ?>][price]" value="0.00" min="0" step="0.01" class="small-text"></td>
    <td><input type="number" name="sod_attribute[<?php echo esc_attr($type); ?>][<?php echo esc_attr($key); ?>][passes]" value="1" min="1" step="1" class="small-text"></td>
    <td><button type="button" class="button remove-attribute"><?php _e('Remove', 'spark-of-divine-scheduler'); ?></button></td>
</tr>