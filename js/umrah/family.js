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
             const submitBtn = document.querySelector("#createFamilyForm button[type='submit']");
             
             // Disable button
             if (submitBtn) {
                 submitBtn.disabled = true;
                 submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
             }
             
             fetch('../api/umrah/create_family.php', {
                 method: 'POST',
                 body: formData
             }).then(response => response.json())
               .then(data => {
                   if(data.success) {
                       showToast('success', 'Family created successfully');
                       document.getElementById("createFamilyForm").reset();
                       document.getElementById("createFamilyModal").style.display = "none";
                       setTimeout(() => {
                        location.reload();
                       }, 1000);
                   } else {
                       showToast('error', data.message || 'Error creating family');
                       if (submitBtn) {
                           submitBtn.disabled = false;
                           submitBtn.innerHTML = 'Create';
                       }
                   }
               })
               .catch(error => {
                   showToast('error', 'An error occurred');
                   if (submitBtn) {
                       submitBtn.disabled = false;
                       submitBtn.innerHTML = 'Create';
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

        // Open Booking Modal (member already has a family)
        window.openBookingModal = function(familyId) {
            const chooser = document.getElementById("familyChooserCard");
            if (chooser) {
                chooser.style.display = "none";
            }
            const familyIdInput = document.getElementById("familyId");
            if (familyIdInput) {
                familyIdInput.value = familyId;
            }
            // Use jQuery for Bootstrap modal if available
            if (typeof $ !== 'undefined' && $.fn.modal) {
                $('#umrahModal').modal('show');
            }
        };

        // Open Add Member Modal from a Group card (no family yet):
        // family chooser at top — add a new family OR pick an existing one,
        // then add members to it inside the same modal.
        window.openAddMemberModal = function(groupId) {
            const chooser = document.getElementById("familyChooserCard");
            if (chooser) {
                chooser.style.display = "block";
            }

            // Default to "Add New Family" mode
            setUmrahFamilyMode('new');

            const familyIdInput = document.getElementById("familyId");
            if (familyIdInput) familyIdInput.value = "";

            // Preselect the group we came from in the New Family form
            const groupSelect = document.querySelector('#umrahForm select[name="group_id"]');
            preselectUmrahGroup(groupSelect, groupId);

            // Restrict the Existing Family list to families of this group when possible
            const existingSelect = document.getElementById("existingFamilySelect");
            if (existingSelect) {
                existingSelect.value = "";
                let matched = 0;
                Array.prototype.forEach.call(existingSelect.options, function(opt) {
                    if (!opt.value) return;
                    const match = String(opt.getAttribute('data-group-id')) === String(groupId);
                    opt.style.display = match ? "" : "none";
                    if (match) matched++;
                });
                // No families in this group yet — show all families
                if (matched === 0) {
                    Array.prototype.forEach.call(existingSelect.options, function(opt) {
                        if (opt.value) opt.style.display = "";
                    });
                }
            }

            // Use jQuery for Bootstrap modal if available
            if (typeof $ !== 'undefined' && $.fn.modal) {
                $('#umrahModal').modal('show');
            }
        };

        function preselectUmrahGroup(groupSelect, groupId, attempt) {
            if (!groupSelect || !groupId) return;
            attempt = attempt || 0;
            if (groupSelect.options.length === 0) {
                // groups.js fills the select async — retry shortly
                if (attempt < 10) {
                    setTimeout(function() { preselectUmrahGroup(groupSelect, groupId, attempt + 1); }, 250);
                }
                return;
            }
            groupSelect.value = String(groupId);
            // Re-select any group option that was disabled by the initial fill
            Array.prototype.forEach.call(groupSelect.options, function(opt) {
                opt.disabled = false;
            });
        }

        // Switch between "Add New Family" and "Select Existing Family"
        window.setUmrahFamilyMode = function(mode) {
            const newPanel = document.getElementById("newFamilyPanel");
            const existingPanel = document.getElementById("existingFamilyPanel");
            const newBtn = document.getElementById("familyModeNewBtn");
            const existingBtn = document.getElementById("familyModeExistingBtn");
            const familyIdInput = document.getElementById("familyId");
            const existingSelect = document.getElementById("existingFamilySelect");

            function activate(btn, on) {
                if (!btn) return;
                if (on) {
                    btn.classList.add("active", "btn-primary");
                    btn.classList.remove("btn-outline-primary");
                } else {
                    btn.classList.remove("active", "btn-primary");
                    btn.classList.add("btn-outline-primary");
                }
            }

            if (mode === "existing") {
                if (newPanel) newPanel.style.display = "none";
                if (existingPanel) existingPanel.style.display = "block";
                if (familyIdInput) familyIdInput.value = existingSelect ? (existingSelect.value || "") : "";
                activate(newBtn, false);
                activate(existingBtn, true);
            } else {
                if (existingPanel) existingPanel.style.display = "none";
                if (newPanel) newPanel.style.display = "block";
                if (familyIdInput) familyIdInput.value = "";
                activate(existingBtn, false);
                activate(newBtn, true);
            }
        };

        window.setUmrahFamilyFromSelect = function(select) {
            const familyIdInput = document.getElementById("familyId");
            if (familyIdInput) familyIdInput.value = select ? select.value : "";
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
