// Function to perform search
function performSearch() {
    const filter = document.getElementById('pnrFilter').value.trim();
    if (filter.length > 0) {
        window.location.href = '?search=' + encodeURIComponent(filter);
    }
}

// Search input event listener - only respond to Enter key
document.getElementById('pnrFilter').addEventListener('keyup', function(e) {
    // If Enter key is pressed, perform search
    if (e.key === 'Enter' && this.value.trim().length > 0) {
        performSearch();
    }
});

// Search button event listener
document.getElementById('searchBtn').addEventListener('click', performSearch); 