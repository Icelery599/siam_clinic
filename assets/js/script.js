// Global app JS — auto-dismiss alerts, small UX helpers.
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.alert').forEach(function (alertEl) {
        setTimeout(function () {
            const alert = bootstrap.Alert.getOrCreateInstance(alertEl);
            if (alert) alert.close();
        }, 6000);
    });
});
