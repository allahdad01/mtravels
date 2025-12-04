  // Family Cancellation Modal Functions
  var familyMembersData = [];

  // Function to open family cancellation modal
  function openFamilyCancellationModal(familyId, bookingId = null) {
      console.log('Opening family cancellation modal with familyId:', familyId, 'bookingId:', bookingId);
      
      // Validate familyId
      if (!familyId || familyId === '' || familyId === 'undefined') {
          console.error('Invalid family ID provided:', familyId);
          Swal.fire({
              icon: 'error',
              title: 'Invalid Family ID',
              text: 'Please provide a valid family ID.'
          });
          return;
      }
      
      // Set the family ID and booking ID
      $('#familyCancellationFamilyId').val(familyId);
      $('#familyCancellationBookingId').val(bookingId || '');
      
      // Clear previous data
      $('#familyMembersDocuments').html('');
      $('#familyNameDisplay').text('');
      $('#totalMembersDisplay').text('');
      $('#packageTypeDisplay').text('');
      
      // Show the modal first
      $('#familyCancellationDetailsModal').modal('show');
      
      // Load family members data after modal is shown
      setTimeout(function() {
          loadFamilyMembersForCancellation(familyId);
      }, 300);
  }

  // Function to load family members data
  function loadFamilyMembersForCancellation(familyId) {
      console.group('Load Family Members Debug');
      console.log('Function called with familyId:', familyId);
      console.log('Current page URL:', window.location.href);
      console.log('jQuery version:', $.fn.jquery);
      console.log('Modal exists:', $('#familyCancellationDetailsModal').length > 0);
      console.log('Family ID input exists:', $('#familyCancellationFamilyId').length > 0);
      
      // Validate inputs
      if (!familyId) {
          console.error('Invalid familyId provided');
          $('#familyMembersDocuments').html('<div class="alert alert-danger">Invalid Family ID</div>');
          console.groupEnd();
          return;
      }
      
      // Show loading state
      $('#familyMembersDocuments').html('<div class="text-center p-4"><i class="feather icon-loader spinning"></i> Loading family members...</div>');
      
      // Determine the correct AJAX URL with more logging
      var possiblePaths = [
          '../api/umrah/get_family_members1.php'
      ];
      
      // Try to find the correct path dynamically
      function findValidPath(paths) {
          for (var i = 0; i < paths.length; i++) {
              var testPath = paths[i];
              console.log('Attempting path:', testPath);
              
              try {
                  var xhr = $.ajax({
                      url: testPath,
                      type: 'HEAD',
                      async: false
                  });
                  
                  if (xhr.status === 200) {
                      console.log('Valid path found:', testPath);
                      return testPath;
                  }
              } catch (e) {
                  console.warn('Path test failed:', testPath, e);
              }
          }
          console.warn('No valid path found, defaulting to first path');
          return paths[0]; // Default to first path if no valid path found
      }
      
      var ajaxUrl = findValidPath(possiblePaths);
      
      console.log('Final AJAX URL:', ajaxUrl);
      
      // Perform AJAX request with extensive logging
      $.ajax({
          url: ajaxUrl,
          type: 'GET',
          data: { 
              family_id: familyId,
              action: 'get_family_members' 
          },
          dataType: 'json',
          timeout: 30000, // 30 second timeout
          beforeSend: function(xhr) {
              console.log('AJAX request started for family ID:', familyId);
              console.log('Request headers:', xhr.getAllResponseHeaders());
          },
          success: function(response, textStatus, xhr) {
              console.log('AJAX Success Response:', response);
              console.log('Response Status:', textStatus);
              console.log('XHR Object:', xhr);
              
              if (response && response.success && response.data) {
                  // Store family members data globally
                  window.familyMembersData = response.data.members || [];
                  
                  console.log('Family members loaded:', window.familyMembersData.length);
                  
                  // Update family information display
                  $('#familyNameDisplay').text(response.data.family_name || 'N/A');
                  $('#totalMembersDisplay').text(window.familyMembersData.length);
                  $('#packageTypeDisplay').text(response.data.package_type || 'N/A');
                  
                  // Set the booking ID to the first member's booking ID
                  if (window.familyMembersData.length > 0) {
                      var firstMemberBookingId = window.familyMembersData[0].booking_id;
                      $('#familyCancellationBookingId').val(firstMemberBookingId);
                      console.log('Set booking ID to first member:', firstMemberBookingId);
                  }
                  
                  // Generate member document sections
                  generateFamilyMemberDocumentSections();
              } else {
                  console.error('Invalid response structure:', response);
                  var errorMsg = 'Error loading family members: ' + (response.message || 'Invalid response structure');
                  $('#familyMembersDocuments').html('<div class="alert alert-danger">' + errorMsg + '</div>');
              }
          },
          error: function(xhr, status, error) {
              console.error('AJAX Error Details:');
              console.error('Status:', status);
              console.error('Error:', error);
              console.error('Response Text:', xhr.responseText);
              console.error('Status Code:', xhr.status);
              console.error('Request URL:', ajaxUrl);
              console.error('Request Parameters:', { 
                  family_id: familyId, 
                  action: 'get_family_members' 
              });
              
              var errorMessage = 'Failed to load family members.';
              
              if (xhr.status === 404) {
                  errorMessage = 'AJAX endpoint not found. Please check the file path: ' + ajaxUrl;
              } else if (xhr.status === 500) {
                  errorMessage = 'Server error occurred. Check server logs.';
              } else if (xhr.status === 403) {
                  errorMessage = 'Access denied. Please check your permissions.';
              } else if (status === 'timeout') {
                  errorMessage = 'Request timed out. Please try again.';
              } else if (xhr.responseText) {
                  try {
                      var errorResponse = JSON.parse(xhr.responseText);
                      errorMessage = errorResponse.message || errorMessage;
                  } catch (e) {
                      errorMessage = 'Server returned: ' + xhr.responseText.substring(0, 100) + '...';
                  }
              }
              
              $('#familyMembersDocuments').html(
                  '<div class="alert alert-danger">' + 
                  '<strong>Error:</strong> ' + errorMessage + 
                  '<br><small>Status Code: ' + xhr.status + ' | Status: ' + status + 
                  '<br>URL: ' + ajaxUrl + '</small>' +
                  '</div>'
              );
              
              // Optional: Show a more user-friendly error toast
              if (typeof Swal !== 'undefined') {
                  Swal.fire({
                      icon: 'error',
                      title: 'Loading Error',
                      text: errorMessage,
                      confirmButtonColor: '#dc3545'
                  });
              } else {
                  alert(errorMessage);
              }
          },
          complete: function(xhr, status) {
              console.log('AJAX request completed with status:', status);
              console.groupEnd();
          }
      });
  }

  // Function to generate document sections for each family member
  function generateFamilyMemberDocumentSections() {
      console.log('Generating document sections for', familyMembersData.length, 'members');
      
      var sectionsHtml = '';
      
      if (familyMembersData.length === 0) {
          $('#familyMembersDocuments').html('<div class="alert alert-warning">No family members found.</div>');
          return;
      }
      
      familyMembersData.forEach(function(member, index) {
          console.log('Processing member:', member.name, 'ID:', member.booking_id);
          
          var template = $('#memberDocumentTemplate').html();
          if (!template) {
              console.error('Member document template not found!');
              $('#familyMembersDocuments').html('<div class="alert alert-danger">Template not found. Please refresh the page.</div>');
              return;
          }
          
          var memberSection = $(template);
          
          // Update member information
          memberSection.find('.member-name').text(member.name || 'Unknown');
          memberSection.find('.member-passport').text(member.passport_number || 'N/A');
          memberSection.find('.member-booking-id').text(member.booking_id || 'N/A');
          
          // Update input IDs and names for this member
          var memberId = member.booking_id || index;
          
          // Ensure unique IDs for each input
          memberSection.find('.member-return-checkbox').each(function() {
              var docType = $(this).data('doc-type');
              var uniqueId = 'member_' + memberId + '_return_' + docType;
              
              // Set unique ID and data attributes
              $(this)
                  .attr('id', uniqueId)
                  .attr('name', uniqueId)
                  .attr('data-member-id', memberId)
                  .attr('data-doc-type', docType);
              
              // Update corresponding label
              $(this).next('label').attr('for', uniqueId);
          });
          
          memberSection.find('.member-condition-select').each(function() {
              var docType = $(this).data('doc-type');
              var uniqueId = 'member_' + memberId + '_condition_' + docType;
              
              // Set unique ID and data attributes
              $(this)
                  .attr('id', uniqueId)
                  .attr('name', uniqueId)
                  .attr('data-member-id', memberId)
                  .attr('data-doc-type', docType);
          });
          
          memberSection.find('.member-notes-input').each(function() {
              var docType = $(this).data('doc-type');
              var uniqueId = 'member_' + memberId + '_notes_' + docType;
              
              // Set unique ID and data attributes
              $(this)
                  .attr('id', uniqueId)
                  .attr('name', uniqueId)
                  .attr('data-member-id', memberId)
                  .attr('data-doc-type', docType);
          });
          
          sectionsHtml += memberSection.prop('outerHTML');
      });
      
      $('#familyMembersDocuments').html(sectionsHtml);
      console.log('Document sections generated successfully');
  }

  // Main cancellation form generation handler
  $(document).on('click', '#familyGenerateCancellationFormBtn', function() {
      console.log('Generate cancellation form button clicked');
      
      // Validate form
      var form = $('#familyCancellationDetailsForm');
      if (!form[0].checkValidity()) {
          form[0].reportValidity();
          return;
      }
      
      // Check if at least one document is marked as returned for at least one member
      var hasReturnedDocuments = false;
      $('.member-return-checkbox:checked').each(function() {
          hasReturnedDocuments = true;
          return false; // Break the loop
      });
      
      if (!hasReturnedDocuments) {
          Swal.fire({
              icon: 'warning',
              title: 'No Documents Returned',
              text: 'Please mark at least one document as returned for at least one family member.',
              confirmButtonColor: '#dc3545'
          });
          return;
      }
      
      // Validate cancellation reason
      var cancellationReason = $('#familyCancellationReason').val().trim();
      if (!cancellationReason) {
          Swal.fire({
              icon: 'warning',
              title: 'Cancellation Reason Required',
              text: 'Please provide a detailed reason for the family cancellation.',
              confirmButtonColor: '#dc3545'
          });
          $('#familyCancellationReason').focus();
          return;
      }
      
      // Show confirmation dialog
      Swal.fire({
          title: 'Generate Family Cancellation Form',
          text: 'This will generate a cancellation form for all ' + familyMembersData.length + ' family members. Continue?',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#dc3545',
          cancelButtonColor: '#6c757d',
          confirmButtonText: 'Yes, Generate Form',
          cancelButtonText: 'Cancel'
      }).then((result) => {
          if (result.isConfirmed) {
              generateFamilyCancellationForm();
          }
      });
  });

  // Function to generate the actual cancellation form
  function generateFamilyCancellationForm() {
      console.group('Family Cancellation Form Generation');
      console.log('Starting form generation process');
      
      try {
          // Show loading state
          $('#familyGenerateCancellationFormBtn')
              .html('<i class="feather icon-loader spinning"></i> Generating...')
              .prop('disabled', true);
          
          // Collect all form data
          var familyId = $('#familyCancellationFamilyId').val();
          var bookingId = $('#familyCancellationBookingId').val();
          var cancellationReason = $('#familyCancellationReason').val().trim();
          
          console.log('Form data:', { familyId, bookingId, cancellationReason });
          
          // Validate inputs
          if (!familyId) {
              throw new Error('Family ID is required');
          }
          
          if (!bookingId) {
              throw new Error('Booking ID is required');
          }
          
          if (!cancellationReason) {
              throw new Error('Cancellation reason is required');
          }
          
          // Use window.familyMembersData instead of local variable
          var familyMembersData = window.familyMembersData || [];
          
          console.log('Family Members Data:', familyMembersData);
          
          // Collect returned items and their conditions for each member
          var returnedItems = {};
          var itemConditions = {};
          var itemNotes = {};
          
          console.group('Returned Items Collection');
          
          familyMembersData.forEach(function(member) {
              var memberId = member.booking_id;
              var memberPrefix = 'member_' + memberId + '_';
              
              console.log('Processing member:', member);
              console.log('Member Prefix:', memberPrefix);
              
              // Document types
              var docTypes = ['passport', 'id_card', 'photos', 'other_docs'];
              
              docTypes.forEach(function(docType) {
                  var returnCheckbox = $('#member_' + memberId + '_return_' + docType);
                  var conditionSelect = $('#member_' + memberId + '_condition_' + docType);
                  var notesInput = $('#member_' + memberId + '_notes_' + docType);
                  
                  console.log('Checking document type:', docType);
                  console.log('Return Checkbox:', returnCheckbox.length, returnCheckbox.is(':checked'));
                  console.log('Condition Select:', conditionSelect.length, conditionSelect.val());
                  console.log('Notes Input:', notesInput.length, notesInput.val());
                  
                  if (returnCheckbox.length) {
                      returnedItems[memberPrefix + docType] = returnCheckbox.is(':checked') ? '1' : '0';
                  }
                  if (conditionSelect.length) {
                      itemConditions[memberPrefix + docType] = conditionSelect.val() || '';
                  }
                  if (notesInput.length) {
                      itemNotes[memberPrefix + docType] = notesInput.val() || '';
                  }
              });
          });
          
          console.log('Collected Returned Items:', returnedItems);
          console.log('Collected Item Conditions:', itemConditions);
          console.log('Collected Item Notes:', itemNotes);
          console.groupEnd();
          
          // Determine current language, default to 'en' if not set
          var currentLang = typeof currentLang !== 'undefined' ? currentLang : 'en';
          
          // Build URL with parameters
          var url = '../api/umrah/generate_family_cancellation.php?family_id=' + encodeURIComponent(familyId);
          url += '&booking_id=' + encodeURIComponent(bookingId);
          url += '&cancellation_reason=' + encodeURIComponent(cancellationReason);
          url += '&returned_items=' + encodeURIComponent(JSON.stringify(returnedItems));
          url += '&item_condition=' + encodeURIComponent(JSON.stringify(itemConditions));
          url += '&item_notes=' + encodeURIComponent(JSON.stringify(itemNotes));
          url += '&lang=' + currentLang;
          
          console.log('Generation URL:', url);
          
          // AJAX request to generate cancellation form
          $.ajax({
              url: url,
              type: 'GET',
              dataType: 'json',
              timeout: 60000, // 60 second timeout for PDF generation
              xhr: function() {
                  var xhr = new window.XMLHttpRequest();
                  xhr.responseType = 'text'; // Receive as text first
                  return xhr;
              },
              success: function(response, textStatus, xhr) {
                  console.group('Cancellation Form Generation Response');
                  console.log('Raw Response:', xhr.responseText);
                  
                  try {
                      // If response is not already an object (e.g., from dataType: 'json'), parse it
                      if (typeof response === 'string') {
                          response = JSON.parse(xhr.responseText);
                      }
                      
                      console.log('Parsed Response:', response);
                      
                      // Reset button state
                      $('#familyGenerateCancellationFormBtn')
                          .html('<i class="feather icon-file-text mr-2"></i>Generate Family Cancellation Form')
                          .prop('disabled', false);
                      
                      if (response.success) {
                          Swal.fire({
                              icon: 'success',
                              title: 'Family Cancellation Form Generated',
                              html: response.message + '<br><small>Family Members: ' + (response.family_members_count || familyMembersData.length) + '</small>',
                              showCancelButton: true,
                              confirmButtonText: 'Download PDF',
                              cancelButtonText: 'Close',
                              confirmButtonColor: '#28a745'
                          }).then((result) => {
                              if (result.isConfirmed && response.file_url) {
                                  // Verify file exists before attempting download
                                  $.ajax({
                                      url: response.file_url,
                                      type: 'HEAD',
                                      success: function() {
                                          // File exists, open in new window
                                          window.open(response.file_url, '_blank');
                                      },
                                      error: function() {
                                          // File not found, show error message
                                          Swal.fire({
                                              icon: 'error',
                                              title: 'Download Error',
                                              text: 'The PDF file could not be found. Please contact support.',
                                              confirmButtonColor: '#dc3545'
                                          });

                                          // Log the error for debugging
                                          console.error('PDF File Not Found:', response.file_url);
                                      }
                                  });
                              }
                          });
                          
                          // Close the modal
                          $('#familyCancellationDetailsModal').modal('hide');
                          
                          // Refresh the page or update the UI as needed
                          if (typeof refreshBookingsTable === 'function') {
                              refreshBookingsTable();
                          }
                      } else {
                          Swal.fire({
                              icon: 'error',
                              title: 'Generation Failed',
                              text: response.message || 'Failed to generate family cancellation form',
                              confirmButtonColor: '#dc3545'
                          });
                      }
                  } catch (parseError) {
                      console.error('JSON Parsing Error:', parseError);
                      
                      // Log the raw response for debugging
                      console.error('Raw Response Text:', xhr.responseText);
                      
                      Swal.fire({
                          icon: 'error',
                          title: 'Response Error',
                          html: 'Failed to parse server response. Please contact support.<br>' +
                              '<small>Error: ' + parseError.message + '</small><br>' +
                              '<small>Response: ' + xhr.responseText.substring(0, 200) + '...</small>',
                          confirmButtonColor: '#dc3545'
                      });
                  }
                  
                  console.groupEnd();
              },
              error: function(xhr, status, error) {
                  console.group('Cancellation Form Generation Error');
                  console.error('Status:', status);
                  console.error('Error:', error);
                  console.error('Response Text:', xhr.responseText);
                  console.error('Status Code:', xhr.status);
                  console.groupEnd();
                  
                  // Reset button state
                  $('#familyGenerateCancellationFormBtn')
                      .html('<i class="feather icon-file-text mr-2"></i>Generate Family Cancellation Form')
                      .prop('disabled', false);
                  
                  var errorMessage = 'Failed to generate family cancellation form.';
                  
                  if (xhr.status === 404) {
                      errorMessage = 'Generation endpoint not found.';
                  } else if (xhr.status === 500) {
                      errorMessage = 'Server error occurred. Check server logs.';
                  } else if (status === 'parsererror') {
                      errorMessage = 'Invalid response from server. Response could not be parsed.';
                      
                      // Try to extract meaningful error message
                      try {
                          var responseText = xhr.responseText;
                          console.error('Unparseable Response:', responseText);
                          
                          // If it looks like HTML, extract the body
                          if (responseText.includes('<!DOCTYPE') || responseText.includes('<html>')) {
                              var bodyMatch = responseText.match(/<body[^>]*>([\s\S]*)<\/body>/i);
                              if (bodyMatch) {
                                  responseText = bodyMatch[1];
                              }
                          }
                          
                          errorMessage += ' Server returned: ' + responseText.substring(0, 200) + '...';
                      } catch (e) {
                          console.error('Error extracting error message:', e);
                      }
                  }
                  
                  Swal.fire({
                      icon: 'error',
                      title: 'Generation Error',
                      html: errorMessage,
                      confirmButtonColor: '#dc3545'
                  });
              },
              complete: function() {
                  console.groupEnd(); // Close the main generation group
              }
          });
      } catch (error) {
          console.error('Form Generation Error:', error);
          
          // Reset button state
          $('#familyGenerateCancellationFormBtn')
              .html('<i class="feather icon-file-text mr-2"></i>Generate Family Cancellation Form')
              .prop('disabled', false);
          
          Swal.fire({
              icon: 'error',
              title: 'Generation Error',
              text: error.message,
              confirmButtonColor: '#dc3545'
          });
          
          console.groupEnd(); // Close the main generation group
      }
  }


// Language selection for family cancellation form


$(document).on('click', '#familyGenerateCancellationFormBtn', function() {
    // Validate form first
    var form = $('#familyCancellationDetailsForm');
    if (!form[0].checkValidity()) {
        form[0].reportValidity();
        return;
    }

    // Check if at least one document is marked as returned for at least one member
    var hasReturnedDocuments = false;
    $('.member-return-checkbox:checked').each(function() {
        hasReturnedDocuments = true;
        return false; // Break the loop
    });
    
    if (!hasReturnedDocuments) {
        Swal.fire({
            icon: 'warning',
            title: 'No Documents Returned',
            text: 'Please mark at least one document as returned for at least one family member.',
            confirmButtonColor: '#dc3545'
        });
        return;
    }

    // Open language selection modal
    $('#familyCancellationLanguageModal').modal('show');
});

// Handle language selection
$(document).on('click', '#familyCancellationLanguageModal .language-select', function() {
    var selectedLang = $(this).data('lang');
    
    // Close language selection modal
    $('#familyCancellationLanguageModal').modal('hide');
    
    // Proceed with form generation
    generateFamilyCancellationForm(selectedLang);
});

// Modify generateFamilyCancellationForm to accept language parameter
function generateFamilyCancellationForm(lang) {
    console.group('Family Cancellation Form Generation');
    console.log('Starting form generation process with language:', lang);
    
    try {
        // Show loading state
        $('#familyGenerateCancellationFormBtn')
            .html('<i class="feather icon-loader spinning"></i> Generating...')
            .prop('disabled', true);
        
        // Collect all form data
        var familyId = $('#familyCancellationFamilyId').val();
        var bookingId = $('#familyCancellationBookingId').val();
        var cancellationReason = $('#familyCancellationReason').val().trim();
        
        console.log('Form data:', { familyId, bookingId, cancellationReason, lang });
        
        // Validate inputs
        if (!familyId) {
            throw new Error('Family ID is required');
        }
        
        if (!bookingId) {
            throw new Error('Booking ID is required');
        }
        
        if (!cancellationReason) {
            throw new Error('Cancellation reason is required');
        }
        
        // Use window.familyMembersData instead of local variable
        var familyMembersData = window.familyMembersData || [];
        
        console.log('Family Members Data:', familyMembersData);
        
        // Collect returned items and their conditions for each member
        var returnedItems = {};
        var itemConditions = {};
        var itemNotes = {};
        
        console.group('Returned Items Collection');
        
        familyMembersData.forEach(function(member) {
            var memberId = member.booking_id;
            var memberPrefix = 'member_' + memberId + '_';
            
            console.log('Processing member:', member);
            console.log('Member Prefix:', memberPrefix);
            
            // Document types
            var docTypes = ['passport', 'id_card', 'photos', 'other_docs'];
            
            docTypes.forEach(function(docType) {
                var returnCheckbox = $('#member_' + memberId + '_return_' + docType);
                var conditionSelect = $('#member_' + memberId + '_condition_' + docType);
                var notesInput = $('#member_' + memberId + '_notes_' + docType);
                
                console.log('Checking document type:', docType);
                console.log('Return Checkbox:', returnCheckbox.length, returnCheckbox.is(':checked'));
                console.log('Condition Select:', conditionSelect.length, conditionSelect.val());
                console.log('Notes Input:', notesInput.length, notesInput.val());
                
                if (returnCheckbox.length) {
                    returnedItems[memberPrefix + docType] = returnCheckbox.is(':checked') ? '1' : '0';
                }
                if (conditionSelect.length) {
                    itemConditions[memberPrefix + docType] = conditionSelect.val() || '';
                }
                if (notesInput.length) {
                    itemNotes[memberPrefix + docType] = notesInput.val() || '';
                }
            });
        });
        
        console.log('Collected Returned Items:', returnedItems);
        console.log('Collected Item Conditions:', itemConditions);
        console.log('Collected Item Notes:', itemNotes);
        console.groupEnd();
        
        // Build URL with parameters
        var url = '../api/umrah/generate_family_cancellation.php?family_id=' + encodeURIComponent(familyId);
        url += '&booking_id=' + encodeURIComponent(bookingId);
        url += '&cancellation_reason=' + encodeURIComponent(cancellationReason);
        url += '&returned_items=' + encodeURIComponent(JSON.stringify(returnedItems));
        url += '&item_condition=' + encodeURIComponent(JSON.stringify(itemConditions));
        url += '&item_notes=' + encodeURIComponent(JSON.stringify(itemNotes));
        url += '&lang=' + encodeURIComponent(lang);
        
        console.log('Generation URL:', url);
        
        // AJAX request to generate cancellation form
        $.ajax({
            url: url,
            type: 'GET',
            dataType: 'json',
            timeout: 60000, // 60 second timeout for PDF generation
            xhr: function() {
                var xhr = new window.XMLHttpRequest();
                xhr.responseType = 'text'; // Receive as text first
                return xhr;
            },
            success: function(response, textStatus, xhr) {
                console.group('Cancellation Form Generation Response');
                console.log('Raw Response:', xhr.responseText);
                
                try {
                    // If response is not already an object (e.g., from dataType: 'json'), parse it
                    if (typeof response === 'string') {
                        response = JSON.parse(xhr.responseText);
                    }
                    
                    console.log('Parsed Response:', response);
                    
                    // Reset button state
                    $('#familyGenerateCancellationFormBtn')
                        .html('<i class="feather icon-file-text mr-2"></i>Generate Family Cancellation Form')
                        .prop('disabled', false);
                    
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Family Cancellation Form Generated',
                            html: response.message + '<br><small>Family Members: ' + (response.family_members_count || familyMembersData.length) + '</small>',
                            showCancelButton: true,
                            confirmButtonText: 'Download PDF',
                            cancelButtonText: 'Close',
                            confirmButtonColor: '#28a745'
                        }).then((result) => {
                            if (result.isConfirmed && response.file_url) {
                                // Verify file exists before attempting download
                                $.ajax({
                                    url: response.file_url,
                                    type: 'HEAD',
                                    success: function() {
                                        // File exists, open in new window
                                        window.open(response.file_url, '_blank');
                                    },
                                    error: function() {
                                        // File not found, show error message
                                        Swal.fire({
                                            icon: 'error',
                                            title: 'Download Error',
                                            text: 'The PDF file could not be found. Please contact support.',
                                            confirmButtonColor: '#dc3545'
                                        });

                                        // Log the error for debugging
                                        console.error('PDF File Not Found:', response.file_url);
                                    }
                                });
                            }
                        });
                        
                        // Close the modal
                        $('#familyCancellationDetailsModal').modal('hide');
                        
                        // Refresh the page or update the UI as needed
                        if (typeof refreshBookingsTable === 'function') {
                            refreshBookingsTable();
                        }
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Generation Failed',
                            text: response.message || 'Failed to generate family cancellation form',
                            confirmButtonColor: '#dc3545'
                        });
                    }
                } catch (parseError) {
                    console.error('JSON Parsing Error:', parseError);
                    
                    // Log the raw response for debugging
                    console.error('Raw Response Text:', xhr.responseText);
                    
                    Swal.fire({
                        icon: 'error',
                        title: 'Response Error',
                        html: 'Failed to parse server response. Please contact support.<br>' +
                              '<small>Error: ' + parseError.message + '</small><br>' +
                              '<small>Response: ' + xhr.responseText.substring(0, 200) + '...</small>',
                        confirmButtonColor: '#dc3545'
                    });
                }
                
                console.groupEnd();
            },
            error: function(xhr, status, error) {
                console.group('Cancellation Form Generation Error');
                console.error('Status:', status);
                console.error('Error:', error);
                console.error('Response Text:', xhr.responseText);
                console.error('Status Code:', xhr.status);
                console.groupEnd();
                
                // Reset button state
                $('#familyGenerateCancellationFormBtn')
                    .html('<i class="feather icon-file-text mr-2"></i>Generate Family Cancellation Form')
                    .prop('disabled', false);
                
                var errorMessage = 'Failed to generate family cancellation form.';
                
                if (xhr.status === 404) {
                    errorMessage = 'Generation endpoint not found.';
                } else if (xhr.status === 500) {
                    errorMessage = 'Server error occurred. Check server logs.';
                } else if (status === 'parsererror') {
                    errorMessage = 'Invalid response from server. Response could not be parsed.';
                    
                    // Try to extract meaningful error message
                    try {
                        var responseText = xhr.responseText;
                        console.error('Unparseable Response:', responseText);
                        
                        // If it looks like HTML, extract the body
                        if (responseText.includes('<!DOCTYPE') || responseText.includes('<html>')) {
                            var bodyMatch = responseText.match(/<body[^>]*>([\s\S]*)<\/body>/i);
                            if (bodyMatch) {
                                responseText = bodyMatch[1];
                            }
                        }
                        
                        errorMessage += ' Server returned: ' + responseText.substring(0, 200) + '...';
                    } catch (e) {
                        console.error('Error extracting error message:', e);
                    }
                }
                
                Swal.fire({
                    icon: 'error',
                    title: 'Generation Error',
                    html: errorMessage,
                    confirmButtonColor: '#dc3545'
                });
            },
            complete: function() {
                console.groupEnd(); // Close the main generation group
            }
        });
    } catch (error) {
        console.error('Form Generation Error:', error);
        
        // Reset button state
        $('#familyGenerateCancellationFormBtn')
            .html('<i class="feather icon-file-text mr-2"></i>Generate Family Cancellation Form')
            .prop('disabled', false);
        
        Swal.fire({
            icon: 'error',
            title: 'Generation Error',
            text: error.message,
            confirmButtonColor: '#dc3545'
        });
        
        console.groupEnd(); // Close the main generation group
    }
}