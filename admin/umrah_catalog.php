<?php
/**
 * Services & Packages — admin/umrah_catalog.php
 * Combined service master + package management (tabs). Writes go through
 * api/umrah/services/save_service.php and api/umrah/packages/save_package.php
 * (CSRF + service_manage / package_manage).
 */
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
session_start();
require_once __DIR__ . '/includes/umrah_permissions.php';
umrah_require('service_manage', 'page');
umrah_require('package_manage', 'page');

$umrahCssVersion = 0;
$umrahJsVersion = 0;
foreach (require '../css/bundle_files.php' as $bundleFile) {
    $bundleMtime = @filemtime('../css/' . $bundleFile);
    if ($bundleMtime > $umrahCssVersion) { $umrahCssVersion = $bundleMtime; }
}
foreach (require '../js/umrah/bundle_files.php' as $bundleFile) {
    $bundleMtime = @filemtime('../js/' . $bundleFile);
    if ($bundleMtime > $umrahJsVersion) { $umrahJsVersion = $bundleMtime; }
}
?>
<?php include '../includes/header.php'; ?>
<link rel="stylesheet" href="../css/bundle.php?v=<?= $umrahCssVersion ?>">
<style>
    .uh-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 3px 12px;
        border-radius: 999px;
        font-size: .72rem;
        font-weight: 600;
        letter-spacing: .02em;
        line-height: 1.5;
        border: 1px solid;
        background: #fff;
    }
    .uh-badge::before {
        content: '';
        width: 8px;
        height: 8px;
        border-radius: 50%;
        flex: 0 0 8px;
        background: currentColor;
    }
    .uh-badge--green  { color: #059669; border-color: #a7f3d0; background: #ecfdf5; }
    .uh-badge--blue   { color: #2563eb; border-color: #bfdbfe; background: #eff6ff; }
    .uh-badge--red    { color: #dc2626; border-color: #fecaca; background: #fef2f2; }
    .uh-badge--amber  { color: #d97706; border-color: #fde68a; background: #fffbeb; }
    .uh-badge--slate  { color: #64748b; border-color: #cbd5e1; background: #f1f5f9; }
    .pkg-card { border: 1px solid #e2e8f0; border-radius: 14px; background: #fff; overflow: hidden; }
    .pkg-card-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; padding: 16px 18px 12px; }
    .pkg-card-code { font-family: var(--font-mono, monospace); font-size: .72rem; color: #64748b; }
    /* ── modals ── */
    .uh-modal { border: none; border-radius: 18px; box-shadow: 0 24px 64px rgba(15,23,42,.20); overflow: hidden; }
    .uh-modal .modal-header { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 18px 24px; border-bottom: 1px solid #eef2f7; background: #fff; }
    .uh-modal-title { display: flex; align-items: center; gap: 13px; min-width: 0; }
    .uh-modal-ic { width: 42px; height: 42px; flex: 0 0 42px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 19px; color: #fff; background: linear-gradient(135deg, #0e7490, #22d3ee); box-shadow: 0 6px 14px rgba(14,116,144,.28); }
    .uh-modal .modal-header h5 { margin: 0; font-weight: 700; font-size: 1.02rem; }
    .uh-modal-sub { display: block; margin-top: 1px; font-size: .75rem; font-weight: 400; color: #64748b; }
    .uh-modal-close { opacity: .55; padding: 6px 8px; transition: opacity .2s ease, transform .2s ease; }
    .uh-modal-close:hover { opacity: 1; transform: rotate(90deg); }
    .uh-modal-body { padding: 22px 24px; background: #fff; }
    .uh-modal-body--scroll { max-height: 66vh; overflow-y: auto; }
    .uh-modal-body--scroll::-webkit-scrollbar { width: 6px; }
    .uh-modal-body--scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 999px; }
    .uh-modal .modal-footer { display: flex; justify-content: flex-end; gap: 10px; padding: 14px 24px; border-top: 1px solid #eef2f7; background: #fff; }
    .uh-label { display: block; margin-bottom: 6px; font-size: .76rem; font-weight: 600; color: #475569; }
    .uh-modal .form-control { border: 1px solid #e2e8f0; border-radius: 10px; padding: .48rem .75rem; font-size: .85rem; transition: border-color .2s ease, box-shadow .2s ease; }
    .uh-modal .form-control:focus { border-color: #0e7490; box-shadow: 0 0 0 3px rgba(14,116,144,.13); }
    .uh-modal textarea.form-control { resize: vertical; }
    .uh-btn-primary { background: #0e7490; border-color: #0e7490; border-radius: 10px; padding: .48rem 1.15rem; font-size: .85rem; font-weight: 600; }
    .uh-btn-primary:hover, .uh-btn-primary:focus { background: #155e75; border-color: #155e75; box-shadow: 0 6px 14px rgba(14,116,144,.28); }
    .uh-btn-outline { background: #fff; border: 1px solid #0e7490; color: #0e7490; border-radius: 10px; font-weight: 600; }
    .uh-btn-outline:hover, .uh-btn-outline:focus { background: #ecfeff; border-color: #155e75; color: #155e75; }
    .uh-btn-ghost { background: #fff; border: 1px solid #e2e8f0; color: #475569; border-radius: 10px; padding: .48rem 1.15rem; font-size: .85rem; }
    .uh-btn-ghost:hover { background: #f8fafc; color: #1e293b; }
    /* line editor */
    .uh-lines-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 12px; }
    .uh-line-card { border: 1px solid #e2e8f0; border-radius: 12px; background: #f8fafc; padding: 12px 14px; margin-bottom: 10px; }
    .uh-line-card:last-child { margin-bottom: 0; }
    .uh-line-top { display: grid; grid-template-columns: 1fr 36px; gap: 8px; align-items: center; }
    .uh-line-top select { width: 100%; }
    .uh-line-del { width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; }
    .uh-line-hint { display: block; font-size: .72rem; color: #94a3b8; margin-bottom: 12px; }
    .uh-tab-btn { border: 1px solid #e2e8f0; background: #fff; color: #334155; }
    .uh-tab-btn:hover { background: #f1f5f9; }
    .uh-tab-btn.active { background: #0e7490; border-color: #0e7490; color: #fff; }
    .uh-tab-count { display: inline-block; min-width: 18px; padding: 0 5px; border-radius: 999px; background: rgba(0,0,0,.14); font-size: .68rem; }
</style>
<meta name="csrf-token" content="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
<div class="pcoded-main-container">
  <div class="pcoded-wrapper">
    <div class="main-body">
<div class="page-wrapper">
    <div class="content container-fluid">
        <div class="page-header d-flex flex-wrap align-items-center mb-3">
            <div>
                <h5 class="mb-1" style="font-weight: 700;">
                    <i class="feather icon-layers mr-2" style="color: #0e7490;"></i><?= __('services_packages') ?>
                </h5>
                <p class="text-muted mb-0" style="font-size: 0.85rem;"><?= __('catalog_hint') ?></p>
            </div>
        </div>

        <div class="mb-3">
            <div class="btn-group btn-group-sm" role="group" aria-label="Tabs">
                <button type="button" class="btn uh-tab-btn active" data-tab="services">
                    <i class="feather icon-server mr-1"></i><?= __('services') ?> <span class="uh-tab-count" id="tabServicesCount"></span>
                </button>
                <button type="button" class="btn uh-tab-btn" data-tab="packages">
                    <i class="feather icon-package mr-1"></i><?= __('packages') ?> <span class="uh-tab-count" id="tabPackagesCount"></span>
                </button>
            </div>
        </div>

        <div id="tab-services">
            <div class="card shadow-none border" style="border-radius:14px;">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <h6 class="mb-0" style="font-weight:700;"><?= __('services') ?></h6>
                        <div class="ml-auto">
                            <button class="btn btn-sm btn-primary" type="button" id="btnAddService">
                                <i class="feather icon-plus mr-1"></i><?= __('add_service') ?>
                            </button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover table-bordered mb-0" style="font-size:.83rem;">
                            <thead><tr>
                                <th><?= __('service') ?></th>
                                <th class="text-center"><?= __('actions') ?></th>
                            </tr></thead>
                            <tbody id="servicesList"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div id="tab-packages" class="d-none">
            <div class="d-flex align-items-center mb-3">
                <div class="ml-auto">
                    <button class="btn btn-sm btn-primary" type="button" id="btnAddPackage">
                        <i class="feather icon-plus mr-1"></i><?= __('add_package') ?>
                    </button>
                </div>
            </div>
            <div class="row" id="packagesGrid">
                <div class="col-12">
                    <div class="text-muted py-4 text-center"><?= __('loading') ?>…</div>
                </div>
            </div>
        </div>
    </div>
</div>
    </div>
  </div>
</div>

<!-- Service modal -->
<div class="modal fade" id="serviceModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content uh-modal">
            <div class="modal-header">
                <div class="uh-modal-title">
                    <span class="uh-modal-ic"><i class="feather icon-server"></i></span>
                    <div>
                        <h5 id="serviceModalTitle"><?= __('service') ?></h5>
                        <small class="uh-modal-sub"><?= __('catalog_hint') ?></small>
                    </div>
                </div>
                <button type="button" class="close uh-modal-close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <form id="serviceForm">
                <input type="hidden" name="entity" value="service">
                <input type="hidden" name="id" id="sfId">
                <div class="modal-body uh-modal-body uh-modal-body--scroll">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="uh-label" for="sfName"><?= __('name') ?> *</label>
                                <input type="text" class="form-control" name="name" id="sfName" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="uh-label" for="sfCode"><?= __('code') ?></label>
                                <input type="text" class="form-control" name="code" id="sfCode">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="uh-label" for="sfActive"><?= __('status') ?></label>
                                <select class="form-control" name="is_active" id="sfActive">
                                    <option value="1"><?= __('active') ?></option>
                                    <option value="0"><?= __('inactive') ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group mb-0">
                                <label class="uh-label" for="sfDescription"><?= __('description') ?></label>
                                <textarea class="form-control" name="description" id="sfDescription" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn uh-btn-ghost" data-dismiss="modal"><?= __('close') ?></button>
                    <button type="submit" class="btn uh-btn-primary"><i class="feather icon-save mr-1"></i><?= __('save') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Package modal -->
<div class="modal fade" id="packageModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content uh-modal">
            <div class="modal-header">
                <div class="uh-modal-title">
                    <span class="uh-modal-ic"><i class="feather icon-package"></i></span>
                    <div>
                        <h5 id="pkgModalTitle"><?= __('package') ?></h5>
                        <small class="uh-modal-sub"><?= __('services_in_package') ?></small>
                    </div>
                </div>
                <button type="button" class="close uh-modal-close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <form id="packageForm">
                <input type="hidden" name="entity" value="package">
                <input type="hidden" name="id" id="pfId">
                <div class="modal-body uh-modal-body uh-modal-body--scroll">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="uh-label" for="pfName"><?= __('name') ?> *</label>
                                <input type="text" class="form-control" name="name" id="pfName" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="uh-label" for="pfCode"><?= __('code') ?> *</label>
                                <input type="text" class="form-control" name="code" id="pfCode" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="uh-label" for="pfStatus"><?= __('status') ?></label>
                                <select class="form-control" name="status" id="pfStatus">
                                    <option value="active"><?= __('active') ?></option>
                                    <option value="inactive"><?= __('inactive') ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                <label class="uh-label" for="pfDescription"><?= __('description') ?></label>
                                <textarea class="form-control" name="description" id="pfDescription" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                    <hr style="border-color:#eef2f7;">
                    <div class="uh-lines-head">
                        <div>
                            <span class="uh-label mb-1"><?= __('services_in_package') ?></span>
                            <small class="uh-line-hint mb-0"><?= __('select_service') ?></small>
                        </div>
                        <button type="button" class="btn btn-sm uh-btn-outline" id="btnAddLine"><i class="feather icon-plus mr-1"></i><?= __('add_line') ?></button>
                    </div>
                    <div id="linesEditor"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn uh-btn-ghost" data-dismiss="modal"><?= __('close') ?></button>
                    <button type="submit" class="btn uh-btn-primary"><i class="feather icon-save mr-1"></i><?= __('save') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
<script src="../js/umrah/bundle.php?v=<?= $umrahJsVersion ?>"></script>
<script>
    window.csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';
    const uhLabels = <?= json_encode([
        // shared
        'saving' => __('saving'),
        'save_failed' => __('save_failed'),
        'save' => __('save'),
        'delete' => __('delete'),
        'toggle_confirm' => __('toggle_confirm'),
        'active' => __('active'),
        'inactive' => __('inactive'),
        'service' => __('service'),
        'no_services' => __('no_services'),
        // services
        'add_service' => __('add_service'),
        'edit_service' => __('edit_service'),
        'service_created' => __('service_created'),
        'service_updated' => __('service_updated'),
        'service_deleted' => __('service_deleted'),
        'status_updated' => __('status_updated'),
        'confirm_delete_svc' => __('confirm_delete_svc'),
        'service_in_use' => __('service_in_use'),
        'confirm_delete' => __('confirm_delete'),
        // packages
        'add_package' => __('add_package'),
        'edit_package' => __('edit_package'),
        'package_created' => __('package_created'),
        'package_updated' => __('package_updated'),
        'selected' => __('selected'),
        'confirm_delete_pkg' => __('confirm_delete_pkg'),
        'delete_pkg_blocked' => __('delete_pkg_blocked'),
        'no_lines' => __('no_lines'),
        'all_services' => __('all_services'),
        'services_in_package' => __('services_in_package'),
        'no_packages' => __('no_packages'),
        'select_service' => __('select_service'),
    ]) ?>;
    window.svcLabels = uhLabels;
    window.pkLabels = uhLabels;

    // ── tab switching ──
    function switchUhTab(tab) {
        document.querySelectorAll('.uh-tab-btn').forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
        document.getElementById('tab-services').classList.toggle('d-none', tab !== 'services');
        document.getElementById('tab-packages').classList.toggle('d-none', tab !== 'packages');
    }
    document.querySelectorAll('.uh-tab-btn').forEach(b => b.addEventListener('click', () => switchUhTab(b.dataset.tab)));
</script>