// JS Modal
document.addEventListener('DOMContentLoaded', function() {
    var modal = document.getElementById("bookingModal");
    var btn = document.getElementById("openModal");
    var span = document.getElementsByClassName("close")[0];

    btn.onclick = function() {
        modal.style.display = "block";
    }

    span.onclick = function() {
        modal.style.display = "none";
    }

    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = "none";
        }
    }

    // Handle dependent details
    const dependentYes = document.getElementById('signing-dependent-yes');
    const dependentNo = document.getElementById('signing-dependent-no');
    const dependentDetails = document.getElementById('dependent-details');

    dependentYes.addEventListener('change', function() {
        if (dependentYes.checked) {
            dependentDetails.style.display = 'block';
        }
    });

    dependentNo.addEventListener('change', function() {
        if (dependentNo.checked) {
            dependentDetails.style.display = 'none';
        }
    });
});
