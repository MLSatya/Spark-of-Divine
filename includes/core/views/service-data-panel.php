<?php
$post_id = get_the_ID();
$service_type = get_post_meta($post_id, '_sod_service_type', true) ?: 'service';
?>
<div id="sod_service_options" class="panel woocommerce_options_panel">
    <div class="options_group">
        <p class="form-field">
            <label><?php _e('Item Type', 'spark-of-divine-scheduler'); ?></label>
            <select name="sod_service_type" id="sod_service_type">
                <option value="service" <?php selected($service_type, 'service'); ?>><?php _e('Regular Service', 'spark-of-divine-scheduler'); ?></option>
                <option value="event" <?php selected($service_type, 'event'); ?>><?php _e('Event', 'spark-of-divine-scheduler'); ?></option>
            </select>
            <span class="description"><?php _e('Select the type of item this product represents', 'spark-of-divine-scheduler'); ?></span>
        </p>
        <p class="form-field">
            <label><?php _e('Linked Item', 'spark-of-divine-scheduler'); ?></label>
            <?php
            $linked_id = get_post_meta($post_id, '_sod_service_id', true);
            $post_types = ['sod_service', 'sod_event'];
            $items = get_posts(['post_type' => $post_types, 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC']);
            ?>
            <select name="sod_service_id" id="sod_service_id">
                <option value=""><?php _e('Select an item', 'spark-of-divine-scheduler'); ?></option>
                <?php foreach ($items as $item): ?>
                    <option value="<?php echo esc_attr($item->ID); ?>" <?php selected($linked_id, $item->ID); ?>>
                        <?php echo esc_html($item->post_title . ' (' . $item->post_type . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <span class="description"><?php _e('Link this product to a service or event for scheduling', 'spark-of-divine-scheduler'); ?></span>
        </p>
    </div>
    <div class="options_group service-attributes-section">
        <h4><?php _e('Item Attributes', 'spark-of-divine-scheduler'); ?></h4>
        <div class="attribute-type-controls">
            <button type="button" class="button add-attribute" data-type="duration"><?php _e('Add Duration', 'spark-of-divine-scheduler'); ?></button>
            <button type="button" class="button add-attribute" data-type="passes"><?php _e('Add Passes', 'spark-of-divine-scheduler'); ?></button>
            <button type="button" class="button add-attribute" data-type="package"><?php _e('Add Package', 'spark-of-divine-scheduler'); ?></button>
            <button type="button" class="button add-attribute" data-type="custom"><?php _e('Add Custom Attribute', 'spark-of-divine-scheduler'); ?></button>
        </div>
        <div class="attribute-management">
            <table class="widefat service-attributes-table">
                <thead>
                    <tr>
                        <th><?php _e('Type', 'spark-of-divine-scheduler'); ?></th>
                        <th><?php _e('Value', 'spark-of-divine-scheduler'); ?></th>
                        <th><?php _e('Price', 'spark-of-divine-scheduler'); ?></th>
                        <th><?php _e('Passes', 'spark-of-divine-scheduler'); ?></th>
                        <th><?php _e('Actions', 'spark-of-divine-scheduler'); ?></th>
                    </tr>
                </thead>
                <tbody id="service-attributes-list">
                    <?php
                    $attributes = [
                        'duration' => get_post_meta($post_id, '_sod_duration_attributes', true) ?: [],
                        'passes' => get_post_meta($post_id, '_sod_passes_attributes', true) ?: [],
                        'package' => get_post_meta($post_id, '_sod_package_attributes', true) ?: [],
                        'custom' => get_post_meta($post_id, '_sod_custom_attributes', true) ?: []
                    ];
                    foreach ($attributes as $type => $attrs) {
                        if (!empty($attrs) && is_array($attrs)) {
                            foreach ($attrs as $key => $attr) {
                                $value_field = $type === 'package' || $type === 'custom' ? 'text' : 'number';
                                $value_unit = $type === 'duration' ? 'minutes' : ($type === 'passes' ? 'passes' : '');
                                $min_value = $type === 'duration' ? 5 : ($type === 'passes' ? 1 : 0);
                                $step_value = $type === 'duration' ? 5 : 1;
                                ?>
                                <tr class="attribute-row" data-type="<?php echo esc_attr($type); ?>" data-key="<?php echo esc_attr($key); ?>">
                                    <td><?php echo ucfirst($type); ?></td>
                                    <td>
                                        <input type="<?php echo $value_field; ?>" name="sod_attribute[<?php echo esc_attr($type); ?>][<?php echo esc_attr($key); ?>][value]" 
                                               value="<?php echo esc_attr($attr['value']); ?>" min="<?php echo $min_value; ?>" step="<?php echo $step_value; ?>" 
                                               class="<?php echo $value_field === 'text' ? 'regular-text' : 'small-text'; ?>">
                                        <?php if ($value_unit): ?><span><?php echo $value_unit; ?></span><?php endif; ?>
                                    </td>
                                    <td><input type="number" name="sod_attribute[<?php echo esc_attr($type); ?>][<?php echo esc_attr($key); ?>][price]" 
                                               value="<?php echo esc_attr($attr['price']); ?>" min="0" step="0.01" class="small-text"></td>
                                    <td><input type="number" name="sod_attribute[<?php echo esc_attr($type); ?>][<?php echo esc_attr($key); ?>][passes]" 
                                               value="<?php echo esc_attr(isset($attr['passes']) ? $attr['passes'] : 1); ?>" min="1" step="1" class="small-text"></td>
                                    <td><button type="button" class="button remove-attribute"><?php _e('Remove', 'spark-of-divine-scheduler'); ?></button></td>
                                </tr>
                                <?php
                            }
                        }
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>