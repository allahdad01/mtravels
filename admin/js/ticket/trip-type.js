// Trip type toggle for new booking form
document.getElementById('tripType').addEventListener('change', function() {
    const tripType = this.value;
    const returnJourneyFields = document.getElementById('returnJourneyFields');
    const returnDateField = document.getElementById('returnDateField');
    
    if (tripType === 'round_trip') {
        returnJourneyFields.style.display = 'block';
        returnDateField.style.display = 'block';
        // Make return fields required when visible
        document.getElementById('returnOrigin').required = true;
        document.getElementById('returnDate').required = true;
    } else {
        returnJourneyFields.style.display = 'none';
        returnDateField.style.display = 'none';
        // Remove required attribute when hidden
        document.getElementById('returnOrigin').required = false;
        document.getElementById('returnDate').required = false;
    }
});

// Trip type toggle for edit form
document.getElementById('editTripType').addEventListener('change', function() {
    const tripType = this.value;
    const returnJourneyFields = document.getElementById('editReturnJourneyFields');
    const returnDateField = document.getElementById('editReturnDateField');
    
    if (tripType === 'round_trip') {
        returnJourneyFields.style.display = 'block';
        returnDateField.style.display = 'block';
        // Make return fields required when visible
        document.getElementById('editReturnOrigin').required = true;
        document.getElementById('editReturnDate').required = true;
    } else {
        returnJourneyFields.style.display = 'none';
        returnDateField.style.display = 'none';
        // Remove required attribute when hidden
        document.getElementById('editReturnOrigin').required = false;
        document.getElementById('editReturnDate').required = false;
    }
}); 