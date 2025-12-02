// Initialize airline selection functionality
function initAirlineSelect() {
    const airlineSelects = document.querySelectorAll('#airline, #editAirline');
    
    airlineSelects.forEach(select => {
        // Clear existing options
        select.innerHTML = '';
        
        // Add default option
        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = 'Select Airline';
        select.appendChild(defaultOption);
        
        // Sort airlines alphabetically by name
        const sortedAirlines = AIRLINES.sort((a, b) => a.name.localeCompare(b.name));
        
        // Add airline options
        sortedAirlines.forEach(airline => {
            const option = document.createElement('option');
            option.value = airline.code;
            option.textContent = airline.name;
            option.setAttribute('data-tokens', `${airline.name} ${airline.code}`); // Add search tokens
            select.appendChild(option);
        });

        // Initialize bootstrap-select
        $(select).addClass('selectpicker')
                .attr('data-live-search', 'true')
                .attr('data-style', 'btn-light')
                .selectpicker('refresh');
    });
}

// Initialize when document is ready
document.addEventListener('DOMContentLoaded', function() {
    initAirlineSelect();
}); 