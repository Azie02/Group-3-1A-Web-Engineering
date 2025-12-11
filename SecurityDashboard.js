document.addEventListener('DOMContentLoaded', function() {
     // --- Logout Functionality ---
    const logoutBtn = document.querySelector('.logoutbutton');

    if (logoutBtn) {
        logoutBtn.addEventListener('click', function(event) {
            const confirmLogout = confirm('Are you sure you want to log out?');
            if (!confirmLogout) {
                event.preventDefault();
            }
        });
    }
    
});