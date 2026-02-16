// Toggle between direct and indirect flight forms
document.addEventListener('DOMContentLoaded', function() {
    const directRadio = document.getElementById('directFlight');
    const indirectRadio = document.getElementById('indirectFlight');
    const directFields = document.getElementById('directFlightFields');
    const indirectFields = document.getElementById('indirectFlightFields');
    const directDates = document.getElementById('directFlightDates');

    function toggleFlightType() {
        if (directRadio.checked) {
            directFields.style.display = 'block';
            directDates.style.display = 'block';
            indirectFields.style.display = 'none';
            
            // Make direct flight fields required
            directFields.querySelectorAll('input').forEach(input => input.required = true);
            directDates.querySelectorAll('input').forEach(input => input.required = true);
            
            // Remove required from indirect fields
            indirectFields.querySelectorAll('input').forEach(input => input.required = false);
        } else {
            directFields.style.display = 'none';
            directDates.style.display = 'none';
            indirectFields.style.display = 'block';
            
            // Make indirect flight fields required
            indirectFields.querySelectorAll('input').forEach(input => input.required = true);
            
            // Remove required from direct fields
            directFields.querySelectorAll('input').forEach(input => input.required = false);
            directDates.querySelectorAll('input').forEach(input => input.required = false);
        }
    }

    directRadio.addEventListener('change', toggleFlightType);
    indirectRadio.addEventListener('change', toggleFlightType);

    // Calculate stopover duration
    function calculateStopover() {
        const leg1Arrival = document.getElementById('leg1ArrivalDate').value + ' ' + document.getElementById('leg1ArrivalTime').value;
        const leg2Departure = document.getElementById('leg2DepartureDate').value + ' ' + document.getElementById('leg2DepartureTime').value;
        
        if (leg1Arrival && leg2Departure) {
            const arrival = new Date(leg1Arrival);
            const departure = new Date(leg2Departure);
            const diffMs = departure - arrival;
            const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
            const diffMins = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));
            
            document.getElementById('stopoverDuration').textContent = `${diffHours}h ${diffMins}m`;
        }
    }

    // Add event listeners for stopover calculation
    ['leg1ArrivalDate', 'leg1ArrivalTime', 'leg2DepartureDate', 'leg2DepartureTime'].forEach(id => {
        document.getElementById(id).addEventListener('change', calculateStopover);
    });

    // Initialize
    toggleFlightType();
});
