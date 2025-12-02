(function() {
    // Wait for the DOM to be fully loaded
    document.addEventListener('DOMContentLoaded', function() {
        // Modal Functionality
        const createFamilyBtn = document.getElementById("createFamilyBtn");
        const closeCreateFamilyModal = document.getElementById("closeCreateFamilyModal");
        const exportBtn = document.getElementById("exportBtn");

        // Add event listeners with null checks
        if (createFamilyBtn) {
            createFamilyBtn.addEventListener("click", function() {
                const createFamilyModal = document.getElementById("createFamilyModal");
                if (createFamilyModal) {
                    createFamilyModal.style.display = "block";
                }
            });
        }

        if (closeCreateFamilyModal) {
            closeCreateFamilyModal.addEventListener("click", function() {
                const createFamilyModal = document.getElementById("createFamilyModal");
                if (createFamilyModal) {
                    createFamilyModal.style.display = "none";
                }
            });
        }

        // AJAX Form Submission (Create Family)
        window.submitCreateFamilyForm = function() {
            var formData = new FormData(document.getElementById("createFamilyForm"));
            
            fetch('create_family.php', {
                method: 'POST',
                body: formData
            }).then(response => response.json())
              .then(data => {
                  if(data.success) {
                      alert("Family created successfully");
                      location.reload();
                  } else {
                      alert("Error creating family");
                  }
              });
            return false;
        };

        // Search functionality
        window.searchFamily = function() {
            var input = document.getElementById("searchInput");
            var filter = input.value.toLowerCase();
            var table = document.getElementById("familyTable");
            var rows = table.getElementsByTagName("tr");

            for (var i = 1; i < rows.length; i++) {
                var cells = rows[i].getElementsByTagName("td");
                var familyHead = cells[0].textContent || cells[0].innerText;
                if (familyHead.toLowerCase().indexOf(filter) > -1) {
                    rows[i].style.display = "";
                } else {
                    rows[i].style.display = "none";
                }
            }
        };

        // Export to Excel
        if (exportBtn) {
            exportBtn.addEventListener("click", function() {
                var table = document.getElementById("familyTable");
                var rows = table.rows;
                var csv = [];

                for (var i = 0; i < rows.length; i++) {
                    var cols = rows[i].cells;
                    var row = [];
                    for (var j = 0; j < cols.length; j++) {
                        row.push(cols[j].innerText);
                    }
                    csv.push(row.join(","));
                }

                var csvContent = "data:text/csv;charset=utf-8," + csv.join("\n");
                var encodedUri = encodeURI(csvContent);
                var link = document.createElement("a");
                link.setAttribute("href", encodedUri);
                link.setAttribute("download", "family_bookings.csv");
                link.click();
            });
        }

        // Open Booking Modal
        window.openBookingModal = function(familyId) {
            const familyIdInput = document.getElementById("familyId");
            if (familyIdInput) {
                familyIdInput.value = familyId;
            }
            // Use jQuery for Bootstrap modal if available
            if (typeof $ !== 'undefined' && $.fn.modal) {
                $('#umrahModal').modal('show');
            }
        };



        

        // Toggle Members Function
        window.toggleMembers = function(familyId) {
            var row = document.getElementById("family-members-" + familyId);
            if (row) {
                if (row.style.display === "none") {
                    row.style.display = "table-row"; // Show members
                } else {
                    row.style.display = "none"; // Hide members
                }
            }
        };
    });
})();