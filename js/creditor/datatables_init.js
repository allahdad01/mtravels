$(document).ready(function() {
    // Initialize DataTable for creditors
    $('#creditorsTable').DataTable({
        responsive: true,
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
        pageLength: 10,
        lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "<?= __('all') ?>"]],
        columnDefs: [
            { targets: 'no-sort', orderable: false }
        ],
        order: [[0, 'asc']]
    });

    // Initialize DataTables for transaction tables
    $('.transaction-table').each(function() {
        $(this).DataTable({
            responsive: true,
            language: {
                search: "<?= __('search') ?>:",
                lengthMenu: "<?= __('show') ?> _MENU_",
                info: "<?= __('showing') ?> _START_ <?= __('to') ?> _END_ <?= __('of') ?> _TOTAL_",
                infoEmpty: "<?= __('no_records') ?>",
                paginate: {
                    next: "<?= __('next') ?>",
                    previous: "<?= __('previous') ?>"
                }
            },
            pageLength: 5,
            lengthMenu: [[5, 10, 25, -1], [5, 10, 25, "<?= __('all') ?>"]],
            columnDefs: [
                { targets: 'no-sort', orderable: false }
            ],
            order: [[0, 'desc']]
        });
    });

    // Handle modal open events to fix DataTables layout issues
    $('body').on('shown.bs.modal', function(e) {
        $($.fn.dataTable.tables(true)).DataTable().columns.adjust().responsive.recalc();
    });

    // Handle edit transaction modal buttons (moved here to ensure DataTables are initialized)
    $(document).on('click', 'button[data-transaction-id]', function() {
        var transactionId = $(this).attr('data-transaction-id');
        $('#editTransactionModal_' + transactionId).modal('show');
    });
});
