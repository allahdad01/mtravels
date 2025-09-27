        // Initialize DataTable
        let dateChangeTable;
        
        document.addEventListener('DOMContentLoaded', function() {
            // Destroy existing DataTable instance if it exists
            if ($.fn.DataTable.isDataTable('#dateChangeTable')) {
                $('#dateChangeTable').DataTable().destroy();
            }
            
            // Initialize DataTable
            dateChangeTable = $('#dateChangeTable').DataTable({
                responsive: true,
                autoWidth: false,
                language: {
                    search: "<?= __('search') ?>:",
                    lengthMenu: "<?= __('show') ?> _MENU_ <?= __('entries') ?>",
                    info: "<?= __('showing') ?> _START_ <?= __('to') ?> _END_ <?= __('of') ?> _TOTAL_ <?= __('entries') ?>",
                    infoEmpty: "<?= __('showing') ?> 0 <?= __('to') ?> 0 <?= __('of') ?> 0 <?= __('entries') ?>",
                    infoFiltered: "(<?= __('filtered_from') ?> _MAX_ <?= __('total_entries') ?>)",
                    paginate: {
                        first: "<?= __('first') ?>",
                        last: "<?= __('last') ?>",
                        next: "<?= __('next') ?>",
                        previous: "<?= __('previous') ?>"
                    }
                },
                columnDefs: [
                    { orderable: false, targets: 'no-sort' }
                ],
                order: [[0, 'asc']]
            });
        });

                // Remove any other DataTables initialization in the file
                $(document).ready(function() {
                    // DataTable initialization is handled in the DOMContentLoaded event above
                });