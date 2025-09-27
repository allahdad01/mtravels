// Client Search and Pagination
document.addEventListener('DOMContentLoaded', function() {
    // Get the client section elements
    const clientSearch = document.getElementById('clientSearch');
    const clientPerPage = document.getElementById('clientPerPage');
    
    // Find the client section and container
    const clientContainer = document.querySelector('.client-cards-container');

    // Debug logging for initial element selection
    console.log('Elements found:', {
        searchInput: clientSearch,
        perPageSelect: clientPerPage,
        container: clientContainer,
        allCards: clientContainer?.querySelectorAll('.col-md-4')?.length || 0
    });

    // Detailed error checking
    const missingElements = [];
    if (!clientSearch) missingElements.push('Search input (#clientSearch)');
    if (!clientPerPage) missingElements.push('Per page select (#clientPerPage)');
    if (!clientContainer) missingElements.push('Client container (.client-cards-container)');

    if (missingElements.length > 0) {
        console.error('Missing required elements:', missingElements);
        return;
    }

    let currentPage = 1;

    function filterClients() {
        const searchTerm = clientSearch.value.toLowerCase().trim();
        const perPage = parseInt(clientPerPage.value) || 5;
        const allClientCards = Array.from(clientContainer.querySelectorAll('.col-md-4'));

        // Debug logging
        console.log('Filtering clients:', {
            searchTerm,
            perPage,
            totalCards: allClientCards.length
        });

        // Remove existing pagination and no results
        const existingPagination = clientContainer.querySelector('.client-pagination');
        if (existingPagination) existingPagination.remove();
        const existingNoResults = clientContainer.querySelector('.no-results');
        if (existingNoResults) existingNoResults.remove();
        const existingResults = clientContainer.querySelector('.search-results-info');
        if (existingResults) existingResults.remove();

        // Filter cards based on search term
        const filteredCards = allClientCards.filter(card => {
            // Get the client card elements
            const cardContent = card.querySelector('.card');
            if (!cardContent) return false;

            const nameElement = cardContent.querySelector('.card-header h5.text-success');
            const balanceElements = cardContent.querySelectorAll('.card-body .col-6 h6.mb-0');
            const statusElement = cardContent.querySelector('.badge');

            // Extract text content
            const clientName = nameElement?.textContent?.replace(/[\n\r\s]+/g, ' ').trim() || '';
            const usdBalance = balanceElements[0]?.textContent?.replace(/[\n\r\s]+/g, ' ').trim() || '';
            const afsBalance = balanceElements[1]?.textContent?.replace(/[\n\r\s]+/g, ' ').trim() || '';
            const status = statusElement?.textContent?.replace(/[\n\r\s]+/g, ' ').trim() || '';

            const searchText = `${clientName} ${usdBalance} ${afsBalance} ${status}`.toLowerCase();
            const isMatch = searchText.includes(searchTerm);

            return isMatch;
        });

        // Hide all cards first
        allClientCards.forEach(card => {
            card.style.display = 'none';
        });

        // Add search results info
        const resultsInfo = document.createElement('div');
        resultsInfo.className = 'col-12 mb-3 search-results-info';
        
        if (searchTerm) {
            resultsInfo.innerHTML = `
                <div class="alert alert-info border-0 shadow-sm d-flex align-items-center" role="alert">
                    <i class="feather icon-info mr-2"></i>
                    <span>Found ${filteredCards.length} matching clients for "${searchTerm}"</span>
                </div>
            `;
            clientContainer.insertBefore(resultsInfo, clientContainer.firstChild);
        }

        if (filteredCards.length === 0) {
            // Show no results message
            const noResults = document.createElement('div');
            noResults.className = 'col-12 text-center py-5 no-results';
            noResults.innerHTML = `
                <div class="alert alert-warning border-0 shadow-sm" role="alert">
                    <i class="feather icon-alert-circle mr-2"></i>
                    <span>No clients found matching "${searchTerm}"</span>
                </div>
            `;
            clientContainer.appendChild(noResults);
            return;
        }

        // Calculate pagination
        const totalPages = Math.ceil(filteredCards.length / perPage);
        currentPage = Math.min(currentPage, totalPages);
        const start = (currentPage - 1) * perPage;
        const end = Math.min(start + perPage, filteredCards.length);

        // Show current page cards
        filteredCards.slice(start, end).forEach(card => {
            card.style.display = '';
        });

        // Add pagination controls if needed
        if (totalPages > 1) {
            const paginationDiv = document.createElement('div');
            paginationDiv.className = 'col-12 text-center mt-4 mb-2 client-pagination';
            paginationDiv.innerHTML = `
                <nav aria-label="Client navigation">
                    <div class="btn-group shadow-sm">
                        <button class="btn btn-light border-0 ${currentPage === 1 ? 'disabled' : ''}" 
                                style="border-top-left-radius: 20px; border-bottom-left-radius: 20px;">
                            <i class="feather icon-chevron-left"></i>
                        </button>
                        <button class="btn btn-light border-0" disabled>
                            <span class="text-muted">Page ${currentPage} of ${totalPages}</span>
                        </button>
                        <button class="btn btn-light border-0 ${currentPage === totalPages ? 'disabled' : ''}"
                                style="border-top-right-radius: 20px; border-bottom-right-radius: 20px;">
                            <i class="feather icon-chevron-right"></i>
                        </button>
                    </div>
                </nav>
            `;

            // Add click handlers
            const [prevBtn, , nextBtn] = paginationDiv.querySelectorAll('button');
            prevBtn.addEventListener('click', () => {
                if (currentPage > 1) {
                    currentPage--;
                    filterClients();
                }
            });
            nextBtn.addEventListener('click', () => {
                if (currentPage < totalPages) {
                    currentPage++;
                    filterClients();
                }
            });

            clientContainer.appendChild(paginationDiv);
        }
    }

    // Add focus styles to search input
    clientSearch.addEventListener('focus', () => {
        clientSearch.parentElement.classList.add('shadow');
    });

    clientSearch.addEventListener('blur', () => {
        clientSearch.parentElement.classList.remove('shadow');
    });

    // Event Listeners
    clientSearch.addEventListener('input', () => {
        currentPage = 1;
        filterClients();
    });

    clientPerPage.addEventListener('change', () => {
        currentPage = 1;
        filterClients();
    });

    // Initialize on page load
    filterClients();
}); 