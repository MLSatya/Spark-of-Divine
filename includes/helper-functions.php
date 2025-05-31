<?php
            /**
             * Helper functions for the Spark of Divine Scheduler
             */

            if (!defined('ABSPATH')) {
                exit;
            }

            /**
             * Map service ID to product ID (for backward compatibility)
             */
            function map_service_to_product($service_id) {
                global $wpdb;

                // Just return 0 if we don't have a valid service ID
                if (empty($service_id)) {
                    return 0;
                }

                // Query the staff availability table to find the product linked to this service
                $product_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT product_id FROM {$wpdb->prefix}sod_staff_availability WHERE service_id = %d LIMIT 1",
                    $service_id
                ));

                // Check alternate table name if the standard one doesn't exist
                if ($wpdb->last_error) {
                    $alternate_table = 'wp_3be9vb_sod_staff_availability';
                    $product_id = $wpdb->get_var($wpdb->prepare(
                        "SELECT product_id FROM {$alternate_table} WHERE service_id = %d LIMIT 1",
                        $service_id
                    ));
                }

                return $product_id ? intval($product_id) : 0;
            }