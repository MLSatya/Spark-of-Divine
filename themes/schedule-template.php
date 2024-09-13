<?php
/*
Template Name: Service and Staff Schedule
*/

get_header();

// Get services, staff, and categories
$services = get_posts(array('post_type' => 'service', 'posts_per_page' => -1));
$staff = get_posts(array('post_type' => 'staff', 'posts_per_page' => -1));
$categories = get_terms(array('taxonomy' => 'service_category', 'hide_empty' => false));

?>
<div class="sod-schedule-container">
    <!-- Top Menu (Fixed for all views) -->
    <nav class="sod-top-menu">
        <ul>
            <li><a href="#my-account">My Account</a></li>
            <li><a href="#schedule">Schedule</a></li>
            <li>
                <a href="#appointments">Appointments</a>
                <div class="submenu">
                    <div class="submenu-column">
                        <h4>Filter by Staff</h4>
                        <ul>
                            <?php foreach ($staff as $staff_member) : ?>
                                <li><a href="#" data-filter="staff" data-id="<?php echo esc_attr($staff_member->ID); ?>"><?php echo esc_html($staff_member->post_title); ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <div class="submenu-column">
                        <h4>Filter by Service</h4>
                        <ul>
                            <?php foreach ($services as $service) : ?>
                                <li><a href="#" data-filter="service" data-id="<?php echo esc_attr($service->ID); ?>"><?php echo esc_html($service->post_title); ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <div class="submenu-column">
                        <h4>Filter by Category</h4>
                        <ul>
                            <?php foreach ($categories as $category) : ?>
                                <li><a href="#" data-filter="category" data-id="<?php echo esc_attr($category->term_id); ?>"><?php echo esc_html($category->name); ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </li>
        </ul>
    </nav>

    <!-- Main Content Area -->
    <div class="sod-main-content">
        <!-- Left Sidebar (Only for Calendar View) -->
        <div class="sod-filter-sidebar" id="calendarFilters" style="display: none;">
            <h3>Filters</h3>
            <div class="sod-filter-group">
                <h4>Staff</h4>
                <ul id="staff-filter">
                    <?php foreach ($staff as $staff_member) : ?>
                        <li>
                            <label>
                                <input type="checkbox" value="<?php echo esc_attr($staff_member->ID); ?>">
                                <?php echo esc_html($staff_member->post_title); ?>
                            </label>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="sod-filter-group">
                <h4>Services</h4>
                <ul id="service-filter">
                    <?php foreach ($services as $service) : ?>
                        <li>
                            <label>
                                <input type="checkbox" value="<?php echo esc_attr($service->ID); ?>">
                                <?php echo esc_html($service->post_title); ?>
                            </label>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="sod-filter-group">
                <h4>Categories</h4>
                <ul id="category-filter">
                    <?php foreach ($categories as $category) : ?>
                        <li>
                            <label>
                                <input type="checkbox" value="<?php echo esc_attr($category->term_id); ?>">
                                <?php echo esc_html($category->name); ?>
                            </label>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <!-- Schedule View -->
        <div class="sod-schedule-view">
            <div class="view-toggle">
                <button id="calendarViewBtn" class="active">Calendar View</button>
                <button id="listingViewBtn">Listing View</button>
            </div>
            <div id="calendar"></div>
            <div id="listings" style="display: none;">
                <!-- Service and staff listings will be populated here via JavaScript -->
            </div>
        </div>
    </div>

    <!-- Booking Modal -->
    <div id="bookingModal" class="modal" aria-hidden="true">
        <div class="modal-content">
            <span class="close">&times;</span>
            <div id="modalBody"></div>
        </div>
    </div>
</div>

<?php get_footer(); ?>