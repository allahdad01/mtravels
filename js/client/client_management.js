/**
 * client_management.js
 * Wired to the redesigned UI — uses the new DOM IDs and visual patterns
 * while keeping all original API endpoints and Bootstrap/SweetAlert2 behaviour.
 */

document.addEventListener('DOMContentLoaded', function () {

    // ─── State ────────────────────────────────────────────────────────────────
    let clients     = [];
    let currentTab  = 'active';
    let currentType = '';
    let currentSearch = '';
    let currentPage   = 1;
    const PER_PAGE    = 8;
  
    // ─── Bootstrap modals (jQuery — kept as-is from original) ─────────────────
    const addClientModal  = $('#addClientModal');
    const editClientModal = $('#editClientModal');
  
    // ─── Avatar helpers ────────────────────────────────────────────────────────
    const AVATAR_CLASSES = ['c1','c2','c3','c4','c5','c6'];
  
    function avatarClass(name) {
      let h = 0;
      for (const ch of name) h = (h * 31 + ch.charCodeAt(0)) % AVATAR_CLASSES.length;
      return AVATAR_CLASSES[h];
    }
  
    function initials(name) {
      const parts = name.trim().split(/\s+/);
      return parts.length >= 2
        ? (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
        : name.slice(0, 2).toUpperCase();
    }
  
    // ─── Number formatters ─────────────────────────────────────────────────────
    function fmtUSD(raw) {
      const v = parseFloat(raw || 0);
      if (!v) return '<span class="bal-zero">—</span>';
      const cls = v > 0 ? 'bal-positive' : 'bal-negative';
      return `<span class="${cls}">$${Math.abs(v).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}</span>`;
    }
  
    function fmtAFS(raw) {
      const v = parseFloat(raw || 0);
      if (!v) return '<span class="bal-zero">—</span>';
      const cls = v > 0 ? 'bal-positive' : 'bal-negative';
      return `<span class="${cls}">${Math.abs(v).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})} ؋</span>`;
    }
  
    // ─── Load clients from API ─────────────────────────────────────────────────
    function loadClients() {
      setLoading(true);
  
      fetch('../api/client/getClients.php')
        .then(res => res.json())
        .then(data => {
          if (!Array.isArray(data)) {
            throw new Error(data.error || 'Invalid response from server');
          }
          // Normalise field names: API returns usd_balance / afs_balance / client_type
          // The renderer uses .usd / .afs / .type  — map once here.
          clients = data.map(c => ({
            ...c,
            type : c.client_type,
            usd  : parseFloat(c.usd_balance || 0),
            afs  : parseFloat(c.afs_balance || 0),
          }));
          updateStats();
          buildSparklines();
          render();
        })
        .catch(() => showError('Failed to load clients'))
        .finally(() => setLoading(false));
    }
  
    // ─── Stats bar ────────────────────────────────────────────────────────────
    function updateStats() {
      const total    = clients.length;
      const agencies = clients.filter(c => c.type === 'agency').length;
  
      // Match original logic: USD total = sum of negative balances (money owed to us)
      const totalUsd = clients.reduce((sum, c) => sum + (c.usd < 0 ? Math.abs(c.usd) : 0), 0);
      const totalAfs = clients.reduce((sum, c) => sum + c.afs, 0);
  
      document.getElementById('statTotal').textContent    = total;
      document.getElementById('statAgencies').textContent = agencies;
      document.getElementById('statUSD').textContent =
        '$' + (totalUsd >= 1000 ? (totalUsd / 1000).toFixed(1) + 'k' : totalUsd.toLocaleString());
      document.getElementById('statAFS').textContent =
        totalAfs >= 1_000_000
          ? (totalAfs / 1_000_000).toFixed(1) + 'M'
          : (totalAfs / 1000).toFixed(0) + 'k';
    }
  
    // ─── Sparklines (decorative) ───────────────────────────────────────────────
    function buildSparklines() {
      const configs = [
        { id: 'sparkTotal',    heights: [40,55,45,65,50,70,80,75,90,85,95,100] },
        { id: 'sparkAgencies', heights: [60,55,70,65,80,75,85,80,90,85,90,95]  },
        { id: 'sparkUSD',      heights: [30,45,55,40,60,70,65,80,75,90,85,100] },
        { id: 'sparkAFS',      heights: [80,70,85,75,65,80,70,75,70,65,70,68]  },
      ];
      configs.forEach(({ id, heights }) => {
        const el = document.getElementById(id);
        if (el) el.innerHTML = heights.map(h => `<div class="spark-bar" style="height:${h}%"></div>`).join('');
      });
    }
  
    // ─── Filter helpers ────────────────────────────────────────────────────────
    function getFiltered() {
      const q = currentSearch.toLowerCase();
      return clients.filter(c => {
        if (c.status !== currentTab) return false;
        if (currentType && c.type !== currentType) return false;
        if (q && !c.name.toLowerCase().includes(q) &&
                 !c.email.toLowerCase().includes(q) &&
                 !(c.phone || '').toLowerCase().includes(q)) return false;
        return true;
      });
    }
  
    // ─── Main render ──────────────────────────────────────────────────────────
    function render() {
      const filtered = getFiltered();
      const total    = filtered.length;
      const pages    = Math.ceil(total / PER_PAGE) || 1;
      currentPage    = Math.min(currentPage, pages);
  
      const slice  = filtered.slice((currentPage - 1) * PER_PAGE, currentPage * PER_PAGE);
      const tbody  = document.getElementById('tableBody');
      const empty  = document.getElementById('emptyState');
  
      if (!tbody || !empty) return;
  
      if (!slice.length) {
        tbody.innerHTML = '';
        empty.style.display = 'block';
      } else {
        empty.style.display = 'none';
        tbody.innerHTML = slice.map(c => buildRow(c)).join('');
      }
  
      // Tab counts
      const activeCountEl = document.getElementById('activeCount');
      const inactiveCountEl = document.getElementById('inactiveCount');
      if (activeCountEl) activeCountEl.textContent = `(${clients.filter(c => c.status === 'active').length})`;
      if (inactiveCountEl) inactiveCountEl.textContent = `(${clients.filter(c => c.status === 'inactive').length})`;
  
      // Footer
      const footCountEl = document.getElementById('footCount');
      if (footCountEl) {
        const start = total ? (currentPage - 1) * PER_PAGE + 1 : 0;
        const end   = Math.min(currentPage * PER_PAGE, total);
        footCountEl.innerHTML = `Showing <strong>${start}–${end}</strong> of <strong>${total}</strong> clients`;
      }
  
      renderPagination(pages);
    }
  
    function buildRow(c) {
      const typeLabel = (clientTypeTranslations && clientTypeTranslations[c.type]) || c.type;
      return `
        <tr data-id="${c.id}">
          <td>
            <div class="client-cell">
              <div class="avatar ${avatarClass(c.name)}">${initials(c.name)}</div>
              <div class="client-info">
                <div class="client-name">${escHtml(c.name)}</div>
                <div class="client-id">${escHtml(String(c.id))}</div>
              </div>
            </div>
          </td>
          <td><span class="${escHtml(c.type)}">${escHtml(typeLabel)}</span></td>
          <td class="col-email">
            <div class="contact-cell">
              <span class="contact-email">${escHtml(c.email)}</span>
              <span class="contact-phone">${escHtml(c.phone || '—')}</span>
            </div>
          </td>
          <td class="num">${fmtUSD(c.usd)}</td>
          <td class="num">${fmtAFS(c.afs)}</td>
          <td><span class="${c.status}">${c.status.charAt(0).toUpperCase() + c.status.slice(1)}</span></td>
          <td class="actions-cell">
            <div class="action-row">
              <button class="act-btn primary" onclick="editClient(${c.id})" title="Edit">
                <i class="fas fa-pen-to-square"></i>
              </button>
              <button class="act-btn ${c.can_delete == '1' ? 'danger' : ''}" onclick="deleteClient(${c.id})" title="${c.can_delete == '1' ? 'Delete' : 'Cannot delete — client has related transactions, payments, or bookings'}" ${c.can_delete == '1' ? '' : 'disabled style="opacity:0.35;cursor:not-allowed"'}>
                <i class="fas ${c.can_delete == '1' ? 'fa-trash-can' : 'fa-lock'}"></i>
              </button>
            </div>
          </td>
        </tr>`;
    }
  
    // ─── Pagination ────────────────────────────────────────────────────────────
    function renderPagination(pages) {
      const pg = document.getElementById('pagination');
      if (!pg) return;
      if (pages <= 1) { pg.innerHTML = ''; return; }
  
      let html = `<button class="page-btn" onclick="goPage(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}>
                    <i class="fas fa-chevron-left" style="font-size:10px"></i></button>`;
      for (let i = 1; i <= pages; i++) {
        html += `<button class="page-btn ${i === currentPage ? 'active' : ''}" onclick="goPage(${i})">${i}</button>`;
      }
      html += `<button class="page-btn" onclick="goPage(${currentPage + 1})" ${currentPage === pages ? 'disabled' : ''}>
                 <i class="fas fa-chevron-right" style="font-size:10px"></i></button>`;
      pg.innerHTML = html;
    }
  
    window.goPage = function (p) { currentPage = p; render(); };
  
    // ─── Add Client ────────────────────────────────────────────────────────────
    const addClientBtnEl = document.getElementById('addClientBtn');
    if (addClientBtnEl) {
      addClientBtnEl.addEventListener('click', () => {
        addClientModal.modal('show');
      });
    }
  
    const addClientFormEl = document.getElementById('addClientForm');
    if (addClientFormEl) {
      addClientFormEl.addEventListener('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(this);
  
        fetch('../api/client/add_clients.php', { method: 'POST', body: formData })
          .then(res => res.json())
          .then(data => {
            addClientModal.modal('hide');
            setTimeout(() => {
              if (data.status === 'success' || data.success) {
                Swal.fire({
                  icon: 'success', title: 'Client Added',
                  text: 'New client has been saved.',
                  timer: 1500, showConfirmButton: false,
                }).then(() => {
                  this.reset(); loadClients();
                  if (typeof __hasExistingClients !== 'undefined' && !__hasExistingClients) {
                    __hasExistingClients = true;
                    setTimeout(function() {
                      window.location.href = 'tutorial.php';
                    }, 600);
                  }
                });
              } else {
                showError(data.message || 'Failed to add client');
              }
            }, 300);
          })
          .catch(err => {
            addClientModal.modal('hide');
            setTimeout(() => showError(err.message || 'Failed to add client'), 300);
          });
      });
    }
  
    // ─── Edit Client ───────────────────────────────────────────────────────────
    window.editClient = function (clientId) {
      const client = clients.find(c => c.id === clientId);
      if (!client) return;

      const editClientIdEl = document.getElementById('editClientId');
      const editNameEl = document.getElementById('editName');
      const editEmailEl = document.getElementById('editEmail');
      const editPhoneEl = document.getElementById('editPhone');
      const editAddressEl = document.getElementById('editAddress');
      const editTypeEl = document.getElementById('editType');
      const editStatusEl = document.getElementById('editStatus');

      if (!editClientIdEl || !editNameEl || !editEmailEl) return;

      editClientIdEl.value = client.id;
      editNameEl.value = client.name;
      editEmailEl.value = client.email;
      if (editPhoneEl) editPhoneEl.value = client.phone || '';
      if (editAddressEl) editAddressEl.value = client.address || '';
      if (editTypeEl) editTypeEl.value = client.type;
      if (editStatusEl) editStatusEl.value = client.status;

      // Disable client_type if transactions exist
      const hasTxn = client.has_transactions == '1';
      if (editTypeEl) editTypeEl.disabled = hasTxn;

      // Show/hide lock note
      var note = document.getElementById('editClientLockNote');
      if (hasTxn) {
        if (!note) {
          note = document.createElement('div');
          note.id = 'editClientLockNote';
          note.className = 'alert alert-warning py-2 px-3 mb-0 mt-2';
          note.style.fontSize = '12px';
          note.innerHTML = '<i class="fas fa-lock mr-1"></i> Client type locked — transactions exist';
          if (editTypeEl) {
            var formGroup = editTypeEl.closest('.mb-3');
            if (formGroup) formGroup.parentNode.insertBefore(note, formGroup.nextSibling);
          }
        }
      } else if (note) {
        note.remove();
      }

      editClientModal.modal('show');
    };
  
    const editClientFormEl = document.getElementById('editClientForm');
    if (editClientFormEl) {
      editClientFormEl.addEventListener('submit', function (e) {
        e.preventDefault();

        // Re-enable disabled fields so their values are submitted
        [].slice.call(this.querySelectorAll('[disabled]')).forEach(function(el) { el.disabled = false; });

        const payload = {
          id         : document.getElementById('editClientId').value,
          name       : document.getElementById('editName').value,
          email      : document.getElementById('editEmail').value,
          phone      : document.getElementById('editPhone').value,
          address    : document.getElementById('editAddress').value,
          client_type: document.getElementById('editType').value,
          status     : document.getElementById('editStatus').value,
          csrf_token : window.csrfToken,
        };

        fetch('../api/client/update_client.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
        })
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              editClientModal.modal('hide');
              showToast('Client updated successfully');
              loadClients();
            } else {
              throw new Error(data.message || 'Failed to update client');
            }
          })
          .catch(err => showError(err.message));
      });
    }
  
    // ─── Delete Client ─────────────────────────────────────────────────────────
    window.deleteClient = function (clientId) {
      const client = clients.find(c => c.id === clientId);
      if (!client) return;

      if (client.can_delete != '1') {
        Swal.fire({
          icon: 'info', title: 'Cannot Delete',
          text: 'This client has related transactions, payments, or bookings. Remove or reassign them first.',
          confirmButtonColor: '#6C737F',
        });
        return;
      }
  
      Swal.fire({
        title: 'Delete Client?',
        html: `<span style="color:#6C737F">This will permanently remove <strong>${escHtml(client.name)}</strong>.<br>This action cannot be undone.</span>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#E11D48',
        cancelButtonColor: '#6C737F',
        confirmButtonText: 'Yes, delete',
        cancelButtonText: 'Cancel',
      }).then(result => {
        if (!result.isConfirmed) return;
  
        fetch('../api/client/delete_client.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id: clientId, csrf_token: window.csrfToken }),
        })
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              showToast(`${client.name} removed`);
              loadClients();
            } else {
              throw new Error(data.message || 'Failed to delete client');
            }
          })
          .catch(err => showError(err.message));
      });
    };
  
    // ─── Search & filter event listeners ──────────────────────────────────────
    const searchInputEl = document.getElementById('searchInput');
    if (searchInputEl) {
      searchInputEl.addEventListener('input', e => {
        currentSearch = e.target.value;
        currentPage   = 1;
        render();
      });
    }
  
    const filterPills = document.querySelectorAll('.filter-pill');
    if (filterPills.length) {
      filterPills.forEach(pill => {
        pill.addEventListener('click', function () {
          document.querySelectorAll('.filter-pill').forEach(p => p.classList.remove('active'));
          this.classList.add('active');
          currentType = this.dataset.type;
          currentPage = 1;
          render();
        });
      });
    }
  
    const tabBtns = document.querySelectorAll('.tab-btn');
    if (tabBtns.length) {
      tabBtns.forEach(btn => {
        btn.addEventListener('click', function () {
          document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
          this.classList.add('active');
          currentTab  = this.dataset.tab;
          currentPage = 1;
          render();
        });
      });
    }
  
    // ─── Toast notification ────────────────────────────────────────────────────
    function showToast(msg) {
      const toast = document.getElementById('toast');
      const toastMsgEl = document.getElementById('toastMsg');
      if (!toast || !toastMsgEl) return;
      toastMsgEl.textContent = msg;
      toast.classList.add('show');
      clearTimeout(toast._timer);
      toast._timer = setTimeout(() => toast.classList.remove('show'), 2600);
    }
  
    // ─── Loading overlay (uses existing pcoded preloader if present) ───────────
    function setLoading(on) {
      const el = document.querySelector('.loader-bg');
      if (!el) return;
      if (on) {
        el.style.display = 'block';
        el.classList.remove('fade-out');
      } else {
        el.classList.add('fade-out');
        setTimeout(() => (el.style.display = 'none'), 300);
      }
    }
  
    // ─── Error helper ──────────────────────────────────────────────────────────
    function showError(msg) {
      Swal.fire({ icon: 'error', title: 'Error', text: msg });
    }
  
    // ─── XSS guard ────────────────────────────────────────────────────────────
    function escHtml(str) {
      return String(str)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
    }
  
    // ─── Init ─────────────────────────────────────────────────────────────────
    buildSparklines();
    loadClients();
  });