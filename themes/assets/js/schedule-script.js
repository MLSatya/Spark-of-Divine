// Schedule Script with Robust Error Handling
jQuery(document).ready(function($) {
    var calendar;
    var modal = document.getElementById('bookingModal');
    var span = document.getElementsByClassName("close")[0];

    function initializeCalendar() {
        try {
            var calendarEl = document.getElementById('calendar');
            if (!calendarEl) {
                throw new Error("Calendar element not found");
            }
            calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,dayGridDay'
                },
                events: {
                    url: '/wp-json/spark-divine/v1/events',
                    extraParams: function() {
                        return {
                            staff: $('#staff-filter').val() || [],
                            service: $('#service-filter').val() || [],
                            category: $('#category-filter').val() || []
                        };
                    },
                    failure: function(error) {
                        console.error('There was an error while fetching events:', error);
                        showErrorMessage('Unable to load events. Please try again later.');
                    }
                },
                eventClick: function(info) {
                    try {
                        showBookingModal(info.event);
                    } catch (error) {
                        console.error('Error showing booking modal:', error);
                        showErrorMessage('Unable to show booking details. Please try again.');
                    }
                },
                eventContent: function(arg) {
                    return {
                        html: `
                            <div class="fc-event-main-frame">
                                <div class="fc-event-title-container">
                                    <div class="fc-event-title fc-sticky">${escapeHtml(arg.event.title)}</div>
                                </div>
                                <div class="fc-event-text">
                                    <div>Cost: $${escapeHtml(arg.event.extendedProps.cost)} / 15min</div>
                                    <div>Max Duration: ${escapeHtml(arg.event.extendedProps.maxDuration)} min</div>
                                </div>
                            </div>
                        `
                    };
                },
                eventDidMount: function(info) {
                    try {
                        var serviceId = info.event.extendedProps.serviceId;
                        var color = getColorForService(serviceId);
                        info.el.style.backgroundColor = color;
                    } catch (error) {
                        console.error('Error setting event color:', error);
                    }
                },
                dayMaxEvents: true,
                displayEventTime: false
            });
            calendar.render();
        } catch (error) {
            console.error('Error initializing calendar:', error);
            showErrorMessage('Unable to initialize calendar. Please refresh the page and try again.');
        }
    }

    function getColorForService(serviceId) {
        var colors = ['#FF9999', '#99FF99', '#9999FF', '#FFFF99', '#FF99FF', '#99FFFF'];
        return colors[serviceId % colors.length] || '#CCCCCC';
    }

    function formatDate(date) {
        try {
            return new Intl.DateTimeFormat('en-US', {
                weekday: 'short',
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: 'numeric',
                minute: 'numeric'
            }).format(date);
        } catch (error) {
            console.error('Error formatting date:', error);
            return 'Invalid Date';
        }
    }

    function showBookingModal(event) {
        try {
            var modalContent = `
                <h2>Book Appointment</h2>
                <p>Service: ${escapeHtml(event.extendedProps.serviceName)}</p>
                <p>Staff: ${escapeHtml(event.extendedProps.staffName)}</p>
                <p>Cost: $${escapeHtml(event.extendedProps.cost)} per 15 minutes</p>
                <p>Select Duration:</p>
                <select id="duration-select">
                    ${generateDurationOptions(event.extendedProps.maxDuration)}
                </select>
                <p>Total Cost: $<span id="total-cost">${escapeHtml(event.extendedProps.cost)}</span></p>
                <p>Start: ${formatDate(event.start)}</p>
                <p>End: ${formatDate(event.end)}</p>
                <button id="confirm-booking" ${event.extendedProps.acceptsPayments ? '' : 'disabled'}>
                    ${event.extendedProps.acceptsPayments ? 'Confirm Booking' : 'In-Person Booking Only'}
                </button>
            `;
            $('#modalBody').html(modalContent);
            modal.style.display = "block";
            modal.setAttribute('aria-hidden', 'false');

            $('#duration-select').on('change', function() {
                try {
                    var selectedDuration = $(this).val();
                    var totalCost = (selectedDuration / 15) * event.extendedProps.cost;
                    $('#total-cost').text(totalCost.toFixed(2));
                } catch (error) {
                    console.error('Error calculating total cost:', error);
                    showErrorMessage('Error calculating total cost. Please try again.');
                }
            });

            $('#confirm-booking').on('click', function() {
                try {
                    if (event.extendedProps.acceptsPayments) {
                        console.log('Booking confirmed for ' + event.extendedProps.serviceName);
                        modal.style.display = "none";
                        modal.setAttribute('aria-hidden', 'true');
                        showSuccessMessage('Booking confirmed successfully!');
                    }
                } catch (error) {
                    console.error('Error confirming booking:', error);
                    showErrorMessage('Error confirming booking. Please try again.');
                }
            });
        } catch (error) {
            console.error('Error showing booking modal:', error);
            showErrorMessage('Unable to show booking details. Please try again.');
        }
    }

    span.onclick = function() {
        modal.style.display = "none";
        modal.setAttribute('aria-hidden', 'true');
    }

    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = "none";
            modal.setAttribute('aria-hidden', 'true');
        }
    }

    function generateDurationOptions(maxDuration) {
        try {
            var options = '';
            for (var i = 15; i <= maxDuration; i += 15) {
                options += `<option value="${i}">${i} minutes</option>`;
            }
            return options;
        } catch (error) {
            console.error('Error generating duration options:', error);
            return '<option value="15">15 minutes</option>';
        }
    }
    
    function loadListings(type) {
        var endpoint = type === 'services' ? '/wp-json/wp/v2/service' : '/wp-json/wp/v2/staff';
        $.ajax({
            url: endpoint,
            data: {
                service: $('#service-filter').val() || [],
                staff: $('#staff-filter').val() || [],
                category: $('#category-filter').val() || []
            },
            success: function(items) {
                try {
                    var html = '';
                    items.forEach(function(item) {
                        html += '<div class="item-card">';
                        html += `<img src="${escapeHtml(item.featured_image_url || 'default-image-url.jpg')}" alt="${escapeHtml(item.title.rendered)}" class="item-image">`;
                        html += '<div class="item-details">';
                        html += `<h3 class="item-title">${escapeHtml(item.title.rendered)}</h3>`;
                        html += `<p class="item-description">${item.excerpt ? escapeHtml(item.excerpt.rendered) : ''}</p>`;
                        html += `<a href="#" class="book-now-button" data-id="${escapeHtml(item.id)}" data-type="${escapeHtml(type)}">Book Now</a>`;
                        html += '</div>';
                        html += '</div>';
                    });
                    $('#listings').html(html);
                } catch (error) {
                    console.error('Error rendering listings:', error);
                    showErrorMessage('Error displaying listings. Please try again.');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error("AJAX request failed:", textStatus, errorThrown);
                showErrorMessage('Unable to load listings. Please try again later.');
            }
        });
    }

    $('#view-filter').on('change', function() {
        try {
            var view = $(this).val();
            if (view === 'calendar') {
                $('#calendar').show();
                $('#listings').hide();
                calendar.refetchEvents();
            } else {
                $('#calendar').hide();
                $('#listings').show();
                loadListings(view);
            }
        } catch (error) {
            console.error('Error changing view:', error);
            showErrorMessage('Error changing view. Please try again.');
        }
    });

    $('#staff-filter, #service-filter, #category-filter').on('change', function() {
        try {
            var view = $('#view-filter').val();
            if (view === 'calendar') {
                calendar.refetchEvents();
            } else {
                loadListings(view);
            }
        } catch (error) {
            console.error('Error applying filters:', error);
            showErrorMessage('Error applying filters. Please try again.');
        }
    });

    $(document).on('click', '.book-now-button', function(e) {
        e.preventDefault();
        try {
            var id = $(this).data('id');
            var type = $(this).data('type');
            $.ajax({
                url: '/wp-json/wp/v2/' + type + '/' + id,
                success: function(item) {
                    try {
                        var event = {
                            title: item.title.rendered,
                            extendedProps: {
                                serviceName: item.title.rendered,
                                staffName: type === 'staff' ? item.title.rendered : 'Any Staff',
                                cost: item.acf.cost || 0,
                                maxDuration: item.acf.max_duration || 60,
                                acceptsPayments: item.acf.accepts_payments || false
                            },
                            start: new Date(),
                            end: new Date()
                        };
                        showBookingModal(event);
                    } catch (error) {
                        console.error('Error processing item data:', error);
                        showErrorMessage('Error processing booking data. Please try again.');
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error("AJAX request failed:", textStatus, errorThrown);
                    showErrorMessage('Unable to load booking details. Please try again later.');
                }
            });
        } catch (error) {
            console.error('Error initiating booking:', error);
            showErrorMessage('Error initiating booking. Please try again.');
        }
    });

    function showErrorMessage(message) {
        alert(message); // Replace with a more user-friendly error display method
    }

    function showSuccessMessage(message) {
        alert(message); // Replace with a more user-friendly success display method
    }

    function escapeHtml(unsafe) {
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    initializeCalendar();
});