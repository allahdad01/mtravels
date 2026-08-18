// Trip type toggle for new booking form and edit form
(function () {
    'use strict';

    function toggleReturnGroup(select, groupId, containerId) {
        var isRoundTrip = select.value === 'round_trip';
        var group = document.getElementById(groupId);
        var container = document.getElementById(containerId);

        if (group) group.style.display = isRoundTrip ? 'block' : 'none';
        if (container) {
            container.querySelectorAll('.flight-leg-row .leg-origin, .flight-leg-row .leg-destination').forEach(function (input) {
                input.required = isRoundTrip;
            });
        }
    }

    var tripTypeSelect = document.getElementById('tripType');
    if (tripTypeSelect) {
        tripTypeSelect.addEventListener('change', function () {
            toggleReturnGroup(this, 'returnFlightSegmentsGroup', 'returnFlightLegsContainer');
        });
    }

    var editTripTypeSelect = document.getElementById('editTripType');
    if (editTripTypeSelect) {
        editTripTypeSelect.addEventListener('change', function () {
            toggleReturnGroup(this, 'editReturnFlightSegmentsGroup', 'editReturnFlightLegsContainer');
        });
    }
})();