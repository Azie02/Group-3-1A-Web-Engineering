document.addEventListener('DOMContentLoaded', function() {
    // Select the logout button
    const logoutBtn = document.querySelector('.logoutbutton');

    // Add click event listener if the button exists
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function(event) {
            // Confirm logout
            const confirmLogout = confirm('Are you sure you want to log out?');
            
            // If user cancels, prevent the link navigation
            if (!confirmLogout) {
                event.preventDefault();
            }
        });
    }
});