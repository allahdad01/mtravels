$('#passengerCount').change(function() {
    const passengerCount = parseInt($(this).val());
    const container = $('#passengersContainer');
    
    // Save existing passenger data
    const existingData = {};
    $('.passenger-info').each(function() {
        const passengerNum = $(this).data('passenger');
        if (passengerNum <= passengerCount) {
            existingData[passengerNum] = {
                title: $(`#title_${passengerNum}`).val(),
                gender: $(`#gender_${passengerNum}`).val(),
                name: $(`#passengerName_${passengerNum}`).val(),
                fatherName: $(`#fatherName_${passengerNum}`).val(),
                phone: $(`#phone_${passengerNum}`).val()
            };
        }
    });
    
    // Clear container
    container.empty();
    
    // Generate new passenger entries
    for (let i = 1; i <= passengerCount; i++) {
        const passengerHtml = `
            <div class="passenger-info" data-passenger="${i}">
                <h6 class="border-bottom pb-2 mb-3">Passenger ${i}</h6>
                <div class="form-row">
                    <div class="form-group col-md-3">
                        <label for="title_${i}">Title</label>
                        <select class="form-control" id="title_${i}" name="passengers[${i}][title]" required>
                            <option value="Mr" ${existingData[i] && existingData[i].title === 'Mr' ? 'selected' : ''}>Mr</option>
                            <option value="Mrs" ${existingData[i] && existingData[i].title === 'Mrs' ? 'selected' : ''}>Mrs</option>
                            <option value="Child" ${existingData[i] && existingData[i].title === 'Child' ? 'selected' : ''}>Child</option>
                        </select>
                    </div>
                    <div class="form-group col-md-3">
                        <label for="gender_${i}">Gender</label>
                        <select class="form-control" id="gender_${i}" name="passengers[${i}][gender]" required>
                            <option value="Male" ${existingData[i] && existingData[i].gender === 'Male' ? 'selected' : ''}>Male</option>
                            <option value="Female" ${existingData[i] && existingData[i].gender === 'Female' ? 'selected' : ''}>Female</option>
                        </select>
                    </div>
                    <div class="form-group col-md-3">
                        <label for="passengerName_${i}">Passenger Name</label>
                        <input type="text" class="form-control" id="passengerName_${i}" name="passengers[${i}][name]" 
                               value="${existingData[i] && existingData[i].name ? existingData[i].name : ''}" required>
                    </div>
                    <div class="form-group col-md-3">
                        <label for="phone_${i}">Phone</label>
                        <input type="text" class="form-control" id="phone_${i}" name="passengers[${i}][phone]" 
                               value="${existingData[i] && existingData[i].phone ? existingData[i].phone : ''}" required>
                    </div>
                </div>
            </div>
            ${i < passengerCount ? '<hr>' : ''}
        `;
        container.append(passengerHtml);
    }
}); 