// Multi-leg flight support (ticket segmentation) for Book Ticket modal (#bookTicketForm) and Edit Ticket modal (#editTicketForm)
// Each modal has an outbound group and (for round trips) a return group of journey segments.
(function () {
    'use strict';

    var LEG_TEMPLATE = '' +
        '<div class="d-flex align-items-center justify-content-between mb-2">' +
        '    <h6 class="mb-0"><span class="leg-number">Leg {n}</span></h6>' +
        '    <button type="button" class="btn btn-sm btn-outline-danger remove-leg-btn" title="Remove this leg">' +
        '        <i class="feather icon-trash-2"></i>' +
        '    </button>' +
        '</div>' +
        '<div class="form-row">' +
        '    <div class="form-group col-md-3 mb-2">' +
        '        <label class="leg-label">From *</label>' +
        '        <input type="text" class="form-control leg-origin" placeholder="e.g., KBL">' +
        '    </div>' +
        '    <div class="form-group col-md-3 mb-2">' +
        '        <label class="leg-label">To *</label>' +
        '        <input type="text" class="form-control leg-destination" placeholder="e.g., DXB">' +
        '    </div>' +
        '    <div class="form-group col-md-3 mb-2">' +
        '        <label class="leg-label">Airline</label>' +
        '        <input type="text" class="form-control leg-airline" placeholder="e.g., FlyDubai">' +
        '    </div>' +
        '    <div class="form-group col-md-3 mb-2">' +
        '        <label class="leg-label">Flight Number</label>' +
        '        <input type="text" class="form-control leg-flight-number" placeholder="e.g., FZ302">' +
        '    </div>' +
        '</div>' +
        '<div class="form-row">' +
        '    <div class="form-group col-md-3 mb-2">' +
        '        <label class="leg-label">Departure Date</label>' +
        '        <input type="date" class="form-control leg-date">' +
        '    </div>' +
        '    <div class="form-group col-md-3 mb-2">' +
        '        <label class="leg-label">Departure Time</label>' +
        '        <input type="time" class="form-control leg-time">' +
        '    </div>' +
        '    <div class="form-group col-md-3 mb-2">' +
        '        <label class="leg-label">Arrival Date</label>' +
        '        <input type="date" class="form-control leg-arrival-date">' +
        '    </div>' +
        '    <div class="form-group col-md-3 mb-2">' +
        '        <label class="leg-label">Arrival Time</label>' +
        '        <input type="time" class="form-control leg-arrival-time">' +
        '    </div>' +
        '</div>' +
        '<div class="form-row">' +
        '    <div class="form-group col-md-4 mb-2">' +
        '        <label class="leg-label">Duration</label>' +
        '        <input type="text" class="form-control leg-duration" placeholder="e.g., 2h 30m" readonly>' +
        '    </div>' +
        '    <div class="form-group col-md-4 mb-2 leg-stopover-wrap" style="display: none;">' +
        '        <label class="leg-label">Stopover</label>' +
        '        <input type="text" class="form-control leg-stopover" placeholder="e.g., 3h 25m" readonly>' +
        '    </div>' +
        '</div>' +
        '<small class="form-text text-muted">Origin/Destination auto-filled from segments</small>';

    function getLegs(container) {
        var legs = [];
        container.querySelectorAll('.flight-leg-row').forEach(function (row) {
            var leg = {
                origin: row.querySelector('.leg-origin').value.trim(),
                destination: row.querySelector('.leg-destination').value.trim(),
                airline: row.querySelector('.leg-airline').value.trim(),
                flight_number: row.querySelector('.leg-flight-number').value.trim(),
                date: row.querySelector('.leg-date').value,
                time: row.querySelector('.leg-time').value,
                arrival_date: row.querySelector('.leg-arrival-date').value,
                arrival_time: row.querySelector('.leg-arrival-time').value,
                duration: row.querySelector('.leg-duration').value.trim(),
                stopover: row.querySelector('.leg-stopover').value.trim()
            };
            if (leg.origin || leg.destination || leg.airline || leg.flight_number || leg.date || leg.time || leg.arrival_date || leg.arrival_time || leg.duration || leg.stopover) {
                legs.push(leg);
            }
        });
        return legs;
    }

    function setRowValues(row, leg) {
        row.querySelector('.leg-origin').value = leg.origin || '';
        row.querySelector('.leg-destination').value = leg.destination || '';
        row.querySelector('.leg-airline').value = leg.airline || '';
        row.querySelector('.leg-flight-number').value = leg.flight_number || '';
        row.querySelector('.leg-date').value = leg.date || '';
        row.querySelector('.leg-time').value = leg.time || '';
        row.querySelector('.leg-arrival-date').value = leg.arrival_date || '';
        row.querySelector('.leg-arrival-time').value = leg.arrival_time || '';
        row.querySelector('.leg-duration').value = leg.duration || '';
        row.querySelector('.leg-stopover').value = leg.stopover || '';
    }

    // Compute leg duration ("2h 30m") from departure date/time and arrival date/time
    function computeDuration(depDate, depTime, arrDate, arrTime) {
        if (!depDate || !depTime || !arrDate || !arrTime) return '';
        var dep = new Date(depDate + 'T' + depTime);
        var arr = new Date(arrDate + 'T' + arrTime);
        if (isNaN(dep.getTime()) || isNaN(arr.getTime())) return '';
        var diffMinutes = Math.floor((arr - dep) / 60000);
        if (diffMinutes <= 0) return '';
        var days = Math.floor(diffMinutes / 1440);
        var hours = Math.floor((diffMinutes % 1440) / 60);
        var minutes = diffMinutes % 60;
        var parts = [];
        if (days > 0) parts.push(days + 'd');
        parts.push(hours + 'h');
        parts.push(minutes + 'm');
        return parts.join(' ');
    }

    // Auto-fill the leg's duration field whenever departure/arrival date or time changes
    function bindDurationAuto(row) {
        var durationInput = row.querySelector('.leg-duration');
        if (!durationInput) return;

        var dateInput = row.querySelector('.leg-date');
        var timeInput = row.querySelector('.leg-time');
        var arrivalDateInput = row.querySelector('.leg-arrival-date');
        var arrivalTimeInput = row.querySelector('.leg-arrival-time');

        function recompute() {
            durationInput.value = computeDuration(
                dateInput ? dateInput.value : '',
                timeInput ? timeInput.value : '',
                arrivalDateInput ? arrivalDateInput.value : '',
                arrivalTimeInput ? arrivalTimeInput.value : ''
            );
        }

        [dateInput, timeInput, arrivalDateInput, arrivalTimeInput].forEach(function (el) {
            if (el) {
                el.addEventListener('input', recompute);
                el.addEventListener('change', recompute);
            }
        });
    }

    // Recompute each leg's stopover from the previous leg's arrival to this leg's departure
    function recomputeStopovers(container) {
        var rows = container.querySelectorAll('.flight-leg-row');
        for (var i = 1; i < rows.length; i++) {
            var stopoverInput = rows[i].querySelector('.leg-stopover');
            if (!stopoverInput) continue;
            stopoverInput.value = computeDuration(
                rows[i - 1].querySelector('.leg-arrival-date').value,
                rows[i - 1].querySelector('.leg-arrival-time').value,
                rows[i].querySelector('.leg-date').value,
                rows[i].querySelector('.leg-time').value
            );
        }
    }

    function updateLegs(container, previewEl, stopsEl) {
        // Re-number leg labels
        container.querySelectorAll('.flight-leg-row').forEach(function (row, index) {
            row.dataset.leg = index + 1;
            var numberEl = row.querySelector('.leg-number');
            if (numberEl) numberEl.textContent = 'Leg ' + (index + 1);
        });

        var legs = getLegs(container);

        if (previewEl) {
            var cities = legs.map(function (leg) { return leg.origin; });
            var lastDestination = legs.length ? legs[legs.length - 1].destination : '';
            if (lastDestination) cities.push(lastDestination);
            var valid = cities.filter(function (c) { return c; });
            previewEl.textContent = valid.length > 1 ? 'Route: ' + valid.join(' \u2192 ') : '';
        }

        if (stopsEl) {
            if (legs.length > 1) {
                stopsEl.textContent = 'Stops: ' + (legs.length - 1);
                stopsEl.style.display = '';
            } else {
                stopsEl.textContent = '';
                stopsEl.style.display = 'none';
            }
        }
    }

    function addLegRow(container, previewEl, stopsEl, prefilledOrigin) {
        var row = document.createElement('div');
        row.className = 'flight-leg-row border rounded p-3 mb-3';
        row.dataset.leg = container.querySelectorAll('.flight-leg-row').length + 1;
        row.innerHTML = LEG_TEMPLATE.replace(/\{n\}/g, row.dataset.leg);

        var originInput = row.querySelector('.leg-origin');
        if (prefilledOrigin) originInput.value = prefilledOrigin;

        // Stopover applies only to legs 2+
        var stopoverWrap = row.querySelector('.leg-stopover-wrap');
        if (stopoverWrap) stopoverWrap.style.display = '';

        row.querySelector('.remove-leg-btn').addEventListener('click', function () {
            row.remove();
            updateLegs(container, previewEl, stopsEl);
            recomputeStopovers(container);
        });

        row.querySelectorAll('input').forEach(function (el) {
            el.addEventListener('input', function () { updateLegs(container, previewEl, stopsEl); });
            el.addEventListener('change', function () { updateLegs(container, previewEl, stopsEl); });
        });

        bindDurationAuto(row);

        container.appendChild(row);
        updateLegs(container, previewEl, stopsEl);
        recomputeStopovers(container);
    }

    // Build legs JSON string + derive final destination for a given container
    function buildPayload(container) {
        var legs = getLegs(container);
        if (!legs.length) return { json: null, finalDestination: '' };
        var finalDestination = legs[legs.length - 1].destination;
        return { json: JSON.stringify(legs), finalDestination: finalDestination };
    }

    function bindLegEvents(container, previewEl, stopsEl) {
        container.querySelectorAll('input').forEach(function (el) {
            el.addEventListener('input', function () { updateLegs(container, previewEl, stopsEl); });
            el.addEventListener('change', function () { updateLegs(container, previewEl, stopsEl); });
        });
    }

    // Bind one leg group (outbound or return) and return its helpers
    function createLegGroup(config) {
        var container = document.getElementById(config.containerId);
        if (!container) return null;

        var previewEl = document.getElementById(config.previewId);
        var stopsEl = document.getElementById(config.stopsId);
        var addBtn = document.getElementById(config.addBtnId);

        if (addBtn) {
            addBtn.addEventListener('click', function () {
                var legs = getLegs(container);
                var lastDestination = legs.length ? legs[legs.length - 1].destination : '';
                addLegRow(container, previewEl, stopsEl, lastDestination);
            });
        }

        bindLegEvents(container, previewEl, stopsEl);
        container.querySelectorAll('.flight-leg-row').forEach(bindDurationAuto);

        // Recompute stopovers when arrival/departure date-time fields change
        var TIME_FIELD_CLASSES = ['leg-arrival-date', 'leg-arrival-time', 'leg-date', 'leg-time'];
        function onTimeFieldChange(e) {
            if (e.target.classList && TIME_FIELD_CLASSES.some(function (c) { return e.target.classList.contains(c); })) {
                recomputeStopovers(container);
            }
        }
        container.addEventListener('input', onTimeFieldChange);
        container.addEventListener('change', onTimeFieldChange);

        return {
            container: container,
            previewEl: previewEl,
            stopsEl: stopsEl,
            collect: function () { return buildPayload(container).json; },
            finalDestination: function () { return buildPayload(container).finalDestination; },
            resetDynamic: function () {
                container.querySelectorAll('.flight-leg-row[data-leg]:not([data-leg="1"])').forEach(function (row) { row.remove(); });
                updateLegs(container, previewEl, stopsEl);
            }
        };
    }

    function initBookForm() {
        var form = document.getElementById('bookTicketForm');
        if (!form) return;

        var outbound = createLegGroup({
            containerId: 'flightLegsContainer',
            previewId: 'flightRoutePreview',
            stopsId: 'flightStops',
            addBtnId: 'addFlightLegBtn'
        });
        if (!outbound) return;

        var ret = createLegGroup({
            containerId: 'returnFlightLegsContainer',
            previewId: 'returnFlightRoutePreview',
            stopsId: 'returnFlightStops',
            addBtnId: 'addReturnFlightLegBtn'
        });

        // Expose helpers used by ticket-form.js on submit
        window.collectFlightLegs = function () { return outbound.collect(); };
        window.getFlightLegsFinalDestination = function () { return outbound.finalDestination(); };
        window.collectReturnFlightLegs = function () { return ret ? ret.collect() : null; };
        window.getReturnFlightLegsFinalDestination = function () { return ret ? ret.finalDestination() : ''; };

        // Reset dynamic legs when modal closes
        $('#bookTicketModal').on('hidden.bs.modal', function () {
            outbound.resetDynamic();
            if (ret) ret.resetDynamic();
        });
    }

    function initEditForm() {
        var form = document.getElementById('editTicketForm');
        if (!form) return;

        var outbound = createLegGroup({
            containerId: 'editFlightLegsContainer',
            previewId: 'editFlightRoutePreview',
            stopsId: 'editFlightStops',
            addBtnId: 'addEditFlightLegBtn'
        });
        if (!outbound) return;

        var ret = createLegGroup({
            containerId: 'editReturnFlightLegsContainer',
            previewId: 'editReturnFlightRoutePreview',
            stopsId: 'editReturnFlightStops',
            addBtnId: 'addEditReturnFlightLegBtn'
        });

        // Used by edit-ticket.js to populate legs from saved data
        window.populateEditFlightLegs = function (legsJson, fallbackOrigin, fallbackDestination, fallbackAirline, fallbackDate, fallbackTime) {
            var container = outbound.container;
            container.querySelectorAll('.flight-leg-row[data-leg]:not([data-leg="1"])').forEach(function (row) { row.remove(); });

            var legs = [];
            if (legsJson) {
                try { legs = JSON.parse(legsJson); } catch (e) { legs = []; }
            }

            var leg1Origin = document.getElementById('editOrigin');
            var leg1Destination = document.getElementById('editDestination');
            var leg1Airline = document.getElementById('editAirline');
            var leg1FlightNumber = document.getElementById('editFlightNumber');
            var leg1Date = document.getElementById('editDepartureDate');
            var leg1Time = document.getElementById('editDepartureTime');
            var leg1ArrivalDate = document.getElementById('editArrivalDate');
            var leg1ArrivalTime = document.getElementById('editArrivalTime');
            var leg1Duration = document.getElementById('editDuration');
            var leg1Stopover = document.getElementById('editStopover');

            if (!legs.length) {
                if (leg1Origin) leg1Origin.value = fallbackOrigin || '';
                if (leg1Destination) leg1Destination.value = fallbackDestination || '';
                if (leg1Airline) leg1Airline.value = fallbackAirline || '';
                if (leg1FlightNumber) leg1FlightNumber.value = '';
                if (leg1Date) leg1Date.value = fallbackDate || '';
                if (leg1Time) leg1Time.value = fallbackTime || '';
                if (leg1ArrivalDate) leg1ArrivalDate.value = '';
                if (leg1ArrivalTime) leg1ArrivalTime.value = '';
                if (leg1Duration) leg1Duration.value = '';
                if (leg1Stopover) leg1Stopover.value = '';
                updateLegs(container, outbound.previewEl, outbound.stopsEl);
                return;
            }

            // Leg 1 -> original fields
            setRowValues(container.querySelectorAll('.flight-leg-row')[0], legs[0]);
            if (leg1FlightNumber) leg1FlightNumber.value = legs[0].flight_number || '';

            // Additional legs
            for (var i = 1; i < legs.length; i++) {
                addLegRow(container, outbound.previewEl, outbound.stopsEl, legs[i].origin || '');
                setRowValues(container.querySelectorAll('.flight-leg-row')[i], legs[i]);
            }
            updateLegs(container, outbound.previewEl, outbound.stopsEl);
        };

        // Populate return segments from saved data (class-based rows)
        window.populateEditReturnFlightLegs = function (legsJson, fallbackOrigin, fallbackDestination, fallbackDate, fallbackTime) {
            if (!ret) return;
            var container = ret.container;
            container.querySelectorAll('.flight-leg-row[data-leg]:not([data-leg="1"])').forEach(function (row) { row.remove(); });

            var legs = [];
            if (legsJson) {
                try { legs = JSON.parse(legsJson); } catch (e) { legs = []; }
            }

            var fallback = {
                origin: fallbackOrigin || '',
                destination: fallbackDestination || '',
                airline: '',
                flight_number: '',
                date: fallbackDate || '',
                time: fallbackTime || '',
                arrival_date: '',
                arrival_time: '',
                duration: '',
                stopover: ''
            };

            if (!legs.length) {
                setRowValues(container.querySelectorAll('.flight-leg-row')[0], fallback);
                updateLegs(container, ret.previewEl, ret.stopsEl);
                return;
            }

            setRowValues(container.querySelectorAll('.flight-leg-row')[0], legs[0]);
            for (var i = 1; i < legs.length; i++) {
                addLegRow(container, ret.previewEl, ret.stopsEl, legs[i].origin || '');
                setRowValues(container.querySelectorAll('.flight-leg-row')[i], legs[i]);
            }
            updateLegs(container, ret.previewEl, ret.stopsEl);
        };

        window.collectEditFlightLegs = function () { return outbound.collect(); };
        window.getEditFlightLegsFinalDestination = function () { return outbound.finalDestination(); };
        window.collectEditReturnFlightLegs = function () { return ret ? ret.collect() : null; };
        window.getEditReturnFlightLegsFinalDestination = function () { return ret ? ret.finalDestination() : ''; };
    }

    document.addEventListener('DOMContentLoaded', function () {
        initBookForm();
        initEditForm();
    });
})();