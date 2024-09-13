//User registration
document.addEventListener('DOMContentLoaded', function() {
    const dependentYes = document.getElementById('signing-dependent-yes');
    const dependentNo = document.getElementById('signing-dependent-no');
    const dependentDetails = document.getElementById('dependent-details');

    if (dependentYes) {
        dependentYes.addEventListener('change', function() {
            if (dependentYes.checked) {
                dependentDetails.style.display = 'block';
            }
        });
    }

    if (dependentNo) {
        dependentNo.addEventListener('change', function() {
            if (dependentNo.checked) {
                dependentDetails.style.display = 'none';
            }
        });
    }
});
