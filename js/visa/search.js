$(document).ready(function() {
    const searchInput = $('#searchInput');
    const searchBtn = $('#searchBtn');

    // Debounce function to limit the rate of search execution
    function debounce(func, delay) {
        let timeoutId;
        return function() {
            const context = this;
            const args = arguments;
            clearTimeout(timeoutId);
            timeoutId = setTimeout(() => {
                func.apply(context, args);
            }, delay);
        };
    }

    // Function to perform search
    function performSearch() {
        const searchTerm = searchInput.val().trim();

        // If search term is empty, reset to default view
        if (searchTerm === '') {
            window.location.href = 'visa.php';
            return;
        }

        // Redirect with search parameter
        window.location.href = `visa.php?search=${encodeURIComponent(searchTerm)}`;
    }

    // Add event listener for dynamic search
    searchInput.on('input', debounce(performSearch, 500));

    // Existing search button click handler
    searchBtn.on('click', performSearch);
}); 