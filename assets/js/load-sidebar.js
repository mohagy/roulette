/**
 * Loads the sidebar component into the page
 * This ensures that the sidebar is managed in one place and changes are reflected across all pages
 */
document.addEventListener('DOMContentLoaded', function() {
    console.log('Loading sidebar component...');
    
    // Find the sidebar container in the current page
    const sidebarContainer = document.getElementById('sidebar-container');
    
    if (sidebarContainer) {
        console.log('Sidebar container found, fetching sidebar content...');
        
        // Use a relative path instead of absolute path
        fetch('../management/sidebar.html')
            .then(response => {
                if (!response.ok) {
                    throw new Error('Failed to load sidebar: ' + response.status);
                }
                console.log('Sidebar content fetched successfully');
                return response.text();
            })
            .then(html => {
                // Insert the sidebar HTML into the container
                sidebarContainer.innerHTML = html;
                console.log('Sidebar content inserted into container');
            })
            .catch(error => {
                console.error('Error loading sidebar:', error);
                sidebarContainer.innerHTML = '<div class="alert alert-danger">Failed to load sidebar. Please refresh the page.</div>';
            });
    } else {
        console.error('Sidebar container not found! Make sure you have an element with id="sidebar-container"');
    }
}); 