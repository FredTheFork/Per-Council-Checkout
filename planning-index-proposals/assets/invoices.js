jQuery(($) => {
  const endpoint = PI_Invoices.rest_base;
  const nonce = PI_Invoices.nonce;
  let invoices = [];
  const statuses = PI_Invoices.statuses;

  const $list = $('#pi-invoices-list');
  const $grid = $('#pi-stats-grid');
  const $bulk = $('#pi-bulk-actions');
  const $selectAll = $('#pi-select-all');
  const $bulkStatus = $('.pi-bulk-status');
  const $bulkApply = $('.pi-bulk-apply');
  const $searchInput = $('#pi-search-input');
  const $statusFilter = $('#pi-status-filter');
  const $createButton = $('#pi-create-proposal');
  const $exportButton = $('#pi-export');
  const $pagination = $('.pi-pagination');

  let currentPage = 1;
  const perPage = 10;
  let allSelected = false;

  function escapeHtml(unsafe) {
    if (!unsafe) return '';
    return String(unsafe)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  // === Stats Template (synced with pipeline stages) ===
  const statsHTML = (stats) => `
    <div class="pi-stats-box">
      <div class="pi-stats-value">£${stats.total_proposed}</div>
      <div class="pi-stats-label">Total Proposed</div>
    </div>
    <div class="pi-stats-box">
      <div class="pi-stats-value">${stats.contacted || 0}</div>
      <div class="pi-stats-label">Contacted</div>
    </div>
    <div class="pi-stats-box">
      <div class="pi-stats-value">£${stats.won_value}</div>
      <div class="pi-stats-label">Won Deals</div>
    </div>
    <div class="pi-stats-box">
      <div class="pi-stats-value">${stats.negotiation || 0}</div>
      <div class="pi-stats-label">In Negotiation</div>
    </div>
    <div class="pi-stats-box">
      <div class="pi-stats-value">£${stats.average_amount}</div>
      <div class="pi-stats-label">Average Amount</div>
    </div>
  `;

  // === Invoice Row Template - READ ONLY (no editing from invoices page) ===
  const rowHTML = (inv) => {
    // Stage labels - synced from pipeline stages
    const stageLabels = {
      'draft': 'Proposal Sent',
      'proposal_sent': 'Proposal Sent',
      'contacted': 'Contacted',
      'negotiation': 'Negotiation',
      'won': 'Won',
      'mailed': 'Proposal Sent',
      'lost': 'Lost'
    };
    
    const statusLabel = stageLabels[inv.status] || inv.status.charAt(0).toUpperCase() + inv.status.slice(1);
    const statusBadgeClass = `pi-status-${inv.status}`;
    const hasPdf = inv.pdf_url && inv.pdf_url !== '';
    
    return `
      <tr class="pi-invoice-row" data-id="${inv.id}">
        <td class="pi-col-check"><input type="checkbox" class="pi-row-check"></td>
        <td class="pi-col-id">#${inv.id}</td>
        <td class="pi-col-address">${escapeHtml(inv.address)}</td>
        <td class="pi-col-date">${escapeHtml(inv.created)}</td>
        <td class="pi-col-amount">£${parseFloat(inv.amount || 0).toFixed(2)}</td>
        <td class="pi-col-status">
          <span class="pi-status-badge ${statusBadgeClass}">${statusLabel}</span>
        </td>
        <td class="pi-col-actions">
          ${hasPdf ? `<a href="${inv.pdf_url}?t=${new Date().getTime()}" target="_blank" class="pi-action-btn" title="View PDF">View PDF</a>` : ''}
          <button class="pi-action-btn pi-delete-invoice" title="Delete">Delete</button>
        </td>
      </tr>
    `;
  };

  // Render pagination
  const paginationHTML = (total, current, per) => {
    const start = (current - 1) * per + 1;
    const end = Math.min(start + per - 1, total);
    const pages = Math.ceil(total / per);
    return `
      Showing ${start} to ${end} of ${total} proposals
      <button class="pi-page-btn pi-prev" ${current === 1 ? 'disabled' : ''}>Prev</button>
      <button class="pi-page-btn pi-next" ${current === pages ? 'disabled' : ''}>Next</button>
    `;
  };

  const updateCount = () => {
    $('[data-stage-count="invoices"]').text(invoices.length);
  };

  const render = (page = 1) => {
    $list.empty();
    const filtered = getFilteredInvoices();
    const start = (page - 1) * perPage;
    const end = start + perPage;
    filtered.slice(start, end).forEach(inv => $list.append(rowHTML(inv)));
    if (allSelected) {
      $('.pi-row-check').prop('checked', true);
    }
    bindEvents();
    updateCount();
    $pagination.html(paginationHTML(filtered.length, page, perPage));
    currentPage = page;
  };

  async function loadStats() {
    try {
      const r = await fetch(`${endpoint}/stats`, { headers: { 'X-WP-Nonce': nonce } });
      const stats = await r.json();
      $grid.html(statsHTML(stats));
      
      // Parse amounts for cross-page sync
      const totalProposed = parseFloat(stats.total_proposed.replace(/,/g, '')) || 0;
      const wonValue = parseFloat(stats.won_value.replace(/,/g, '')) || 0;
      const averageAmount = parseFloat(stats.average_amount.replace(/,/g, '')) || 0;
      
      // Sync stats to localStorage for cross-page consistency
      const crmStats = {
        totalProposed,
        wonValue,
        mailed: stats.mailed,
        lost: stats.lost,
        averageAmount,
        invoiceCount: invoices.length,
        source: 'invoices',
        updatedAt: Date.now()
      };
      localStorage.setItem('pi_crm_stats', JSON.stringify(crmStats));
      
      console.log('[PI Invoices] Stats updated:', crmStats);
    } catch (err) {
      console.error('[PI Invoices] Stats load failed:', err);
    }
  }

  async function load() {
    try {
      const r = await fetch(`${endpoint}`, { headers: { 'X-WP-Nonce': nonce } });
      let invoicesData = await r.json();

      const enhancedPromises = invoicesData.map(async (inv) => {
        if (inv.address && inv.address !== 'Unknown Address') return inv;

        const resp = await fetch(`/wp-json/wp/v2/planning_app/${inv.lead_id}`);
        if (!resp.ok) return inv;

        const pd = await resp.json();
        const meta = pd.meta || {};
        const temp = document.createElement('div');
        temp.innerHTML = pd.content?.rendered || '';
        let desc = '';
        let addrFromContent = '';
        temp.querySelectorAll('p').forEach(p => {
          const t = p.textContent.trim();
          if (/^Address:/i.test(t)) {
            addrFromContent = t.replace(/^Address:\s*/i, '');
          } else if (!desc && t.length > 0) {
            desc = t;
          }
        });

        const rawAddress = meta.address || addrFromContent || pd.title?.rendered || pd.slug || 'Unknown Address';
        const addr = rawAddress.toLowerCase().replace(
          /(^|[\s\-\/\(\)\,\.])([a-z0-9])/g,
          (m, p1, p2) => p1 + p2.toUpperCase()
        );

        inv.address = addr;
        return inv;
      });

      invoices = await Promise.all(enhancedPromises);
      allSelected = false;
      render();
    } catch (err) {
      console.error('Invoices load failed', err);
    }
  }

  function getFilteredInvoices() {
    const query = $searchInput.val().trim().toLowerCase();
    const filterStatus = $statusFilter.val();
    return invoices.filter(inv => {
      const text = (inv.address + ' ' + inv.created + ' ' + inv.amount + ' ' + inv.status + ' ' + (inv.notes || '')).toLowerCase();
      const statusMatch = !filterStatus || inv.status === filterStatus;
      const queryMatch = !query || text.includes(query);
      return statusMatch && queryMatch;
    });
  }

  function bindEvents() {
    // Accordion
    $('.pi-accordion-toggle').off().on('click', function() {
      const $btn = $(this);
      const $content = $btn.next('.pi-accordion-content');
      const expanded = $btn.attr('aria-expanded') === 'true';
      $btn.attr('aria-expanded', !expanded);
      $content.slideToggle(180);
    });

    // REMOVED: Inline Status Change - status now syncs from lead stage only
    // REMOVED: Amount inline edit - amount now syncs from lead pricing only

    // Delete
    $('.pi-delete-invoice').off('click').on('click', async function() {
      const id = parseInt($(this).closest('.pi-invoice-row').data('id'), 10);
      await fetch(`${endpoint}/delete`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
        body: JSON.stringify({ inv_id: id })
      });
      invoices = invoices.filter(x => x.id !== id);
      render(currentPage);
      loadStats();
    });

    // Bulk selection
    $('.pi-row-check').off('change').on('change', function() {
      if (allSelected && !this.checked) {
        allSelected = false;
      }
      updateSelectedCount();
    });

    $selectAll.off('change').on('change', function() {
      $('.pi-row-check').prop('checked', this.checked);
      allSelected = false;
      updateSelectedCount();
      if (!this.checked) {
        $bulk.hide();
      }
    });

    $('.pi-select-all-link').off('click').on('click', function(e) {
      e.preventDefault();
      allSelected = true;
      $('.pi-row-check').prop('checked', true);
      updateSelectedCount();
    });

    $('.pi-bulk-delete').off('click').on('click', async () => bulkAction('delete'));
    $('.pi-bulk-print').off('click').on('click', () => bulkAction('print'));
    $bulkApply.off('click').on('click', async () => bulkAction('set_status', $bulkStatus.val()));

    // Pagination
    $('.pi-prev').off('click').on('click', () => {
      if (currentPage > 1) render(currentPage - 1);
    });
    $('.pi-next').off('click').on('click', () => {
      if (currentPage * perPage < getFilteredInvoices().length) render(currentPage + 1);
    });

    // Create
    $createButton.off('click').on('click', () => alert('Create new proposal coming soon'));

    // Export CSV
    $exportButton.off('click').on('click', () => {
      let csv = 'ID,Address,Created,Amount,Status,Notes\n';
      invoices.forEach(inv => {
        csv += `${inv.id},"${(inv.address || '').replace(/"/g, '""')}",${inv.created},${inv.amount},${inv.status},"${(inv.notes || '').replace(/"/g, '""')}"\n`;
      });
      const blob = new Blob([csv], { type: 'text/csv' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'proposals.csv';
      a.click();
    });

    // === Manual PDF Edit with pdf.js rendering and pdf-lib saving ===
    $('.pi-manual-edit-pdf').off('click').on('click', async function() {
      const $button = $(this);
      const $row = $button.closest('tr');
      const id = parseInt($row.data('id'), 10);
      const inv = invoices.find(i => i.id === id);
      if (!inv || !inv.pdf_url) {
        alert('Generate PDF first before custom editing.');
        return;
      }

      // Editable fields configuration
      const EDITABLE_FIELDS = {
        amount: {
          patterns: [/^£[\d,]+\.?\d*$/, /^[\d,]+\.?\d*$/],
          contextBefore: ['amount', 'investment', 'total', 'proposed'],
          label: 'Amount'
        },
        date: {
          patterns: [/^\d{1,2}\/\d{1,2}\/\d{2,4}$/],
          contextBefore: ['date:', 'date issued'],
          label: 'Date'
        },
        valid_until: {
          patterns: [/^\d{1,2}\/\d{1,2}\/\d{2,4}$/],
          contextBefore: ['valid until', 'valid until:'],
          label: 'Valid Until'
        },
        address: {
          patterns: [],
          contextBefore: ['to:', 'prepared for', 'to'],
          multiLine: true,
          label: 'Client Address'
        },
        re_line: {
          patterns: [],
          contextBefore: ['re:', 'subject:', 'project reference', 'reference'],
          label: 'Reference/Subject Line'
        },
        description: {
          patterns: [],
          contextBefore: ['description', 'scope of work', 'proposed works', 'to whom it may concern'],
          multiLine: true,
          label: 'Description'
        },
        notes: {
          patterns: [],
          contextBefore: ['notes:', 'notes', 'inclusions', 'additional'],
          multiLine: true,
          label: 'Notes'
        },
        terms: {
          patterns: [],
          contextBefore: ['terms & conditions', 'terms and conditions', 'terms:', 'terms of engagement'],
          multiLine: true,
          label: 'Terms & Conditions'
        },
        warranty: {
          patterns: [],
          contextBefore: ['warranty', 'quality assurance', 'guarantee'],
          multiLine: true,
          label: 'Warranty'
        }
      };

      const $modal = $(`
        <div class="pi-pdf-editor-modal-overlay" style="
          position: fixed;
          inset: 0;
          background: rgba(0, 0, 0, 0.75);
          backdrop-filter: blur(4px);
          z-index: 99999;
          display: flex;
          align-items: center;
          justify-content: center;
          padding: 20px;
          animation: fadeIn 0.2s ease-out;
        ">
          <div class="pi-pdf-editor-modal-content" style="
            background: #ffffff;
            border-radius: 16px;
            width: 100%;
            max-width: 1100px;
            height: 95vh;
            max-height: 95vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
            overflow: hidden;
            animation: scaleIn 0.25s ease-out;
          ">
            <!-- Header -->
            <div style="
              padding: 20px 28px;
              border-bottom: 1px solid #e2e8f0;
              display: flex;
              justify-content: space-between;
              align-items: center;
              background: #f8fafc;
            ">
              <h2 style="margin: 0; font-size: 22px; font-weight: 600; color: #1e293b;">
                Edit Proposal PDF
              </h2>
              <button class="pi-modal-close" style="
                background: none;
                border: none;
                font-size: 32px;
                cursor: pointer;
                color: #64748b;
                line-height: 1;
                padding: 4px;
                border-radius: 8px;
                transition: all 0.2s;
              ">×</button>
            </div>

            <!-- Toolbar -->
            <div style="
              padding: 16px 28px;
              background: #ffffff;
              border-bottom: 1px solid #e2e8f0;
              display: flex;
              align-items: center;
              gap: 16px;
              flex-wrap: wrap;
            ">
              <button class="pi-edit-mode-btn" style="
                padding: 10px 20px;
                background: #3b82f6;
                color: white;
                border: none;
                border-radius: 8px;
                font-weight: 500;
                cursor: pointer;
                transition: background 0.2s;
              ">Enable Edit Mode</button>

              <button class="pi-save-pdf-btn" style="
                padding: 10px 20px;
                background: #10b981;
                color: white;
                border: none;
                border-radius: 8px;
                font-weight: 500;
                cursor: pointer;
                transition: background 0.2s;
              ">Save Changes</button>

              <span class="pi-edit-status" style="
                margin-left: auto;
                font-size: 14px;
                color: #64748b;
              ">Click "Enable Edit Mode" to start editing</span>
            </div>

            <!-- Legend -->
            <div style="
              padding: 10px 28px;
              background: #fffbeb;
              border-bottom: 1px solid #fcd34d;
              font-size: 13px;
              color: #92400e;
            ">
              <strong>Editable fields:</strong> Amount, Date, Valid Until, Address, Reference, Description, Notes, Terms, Warranty
            </div>

            <!-- PDF Container -->
            <div id="pi-pdf-editor-container" style="
              flex: 1;
              overflow: auto;
              background: #f8fafc;
              padding: 32px;
            "></div>
          </div>
        </div>

        <style>
          @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
          }
          @keyframes scaleIn {
            from { transform: scale(0.95); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
          }
        </style>
      `);
      $('body').append($modal);

      const container = document.getElementById('pi-pdf-editor-container');

      $modal.find('.pi-modal-close').on('click', function() {
        $modal.remove();
      });

      try {
        // Fetch PDF bytes
        const pdfUrl = `${PI_Invoices.rest_base}/get-pdf?id=${id}&_wpnonce=${PI_Invoices.nonce}`;
        const response = await fetch(pdfUrl, {
          method: 'GET',
          headers: { 'X-WP-Nonce': PI_Invoices.nonce }
        });
        if (!response.ok) throw new Error('PDF fetch failed: ' + response.statusText);
        const pdfBytes = await response.arrayBuffer();

        // Setup pdf.js
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.worker.min.js';

        const loadingTask = pdfjsLib.getDocument({ data: pdfBytes });
        const pdf = await loadingTask.promise;

        let editMode = false;
        let allTextItems = [];
        let fieldSpans = [];

        // Render all pages
        for (let pageNum = 1; pageNum <= pdf.numPages; pageNum++) {
          const page = await pdf.getPage(pageNum);
          const viewport = page.getViewport({ scale: 1.5 });

          const pageWrapper = document.createElement('div');
          pageWrapper.style.position = 'relative';
          pageWrapper.style.marginBottom = '20px';
          container.appendChild(pageWrapper);

          const canvas = document.createElement('canvas');
          canvas.className = 'pi-pdf-canvas';
          canvas.height = viewport.height;
          canvas.width = viewport.width;
          canvas.style.display = 'block';
          canvas.style.margin = '0 auto';
          canvas.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
          pageWrapper.appendChild(canvas);

          const context = canvas.getContext('2d');
          await page.render({ canvasContext: context, viewport }).promise;

          const textLayer = document.createElement('div');
          textLayer.className = 'pi-text-layer';
          textLayer.style.cssText = `
            position:absolute; left:50%; top:0;
            width:${viewport.width}px; height:${viewport.height}px;
            transform:translateX(-50%); pointer-events:none;
          `;
          pageWrapper.appendChild(textLayer);

          const textContent = await page.getTextContent();
          
          textContent.items.forEach((item, idx) => {
            const text = (item.str || '').trim();
            if (!text) return;

            allTextItems.push({ text, pageNum, idx, item });

            const span = document.createElement('span');
            span.textContent = item.str;
            span.className = 'pi-text-overlay';
            span.dataset.pageNum = pageNum;
            span.dataset.itemIdx = idx;
            span.dataset.originalText = item.str;
            
            const x = item.transform[4] * 1.5;
            const y = viewport.height - item.transform[5] * 1.5 - (item.height || 12) * 1.5;
            
            span.style.cssText = `
              position:absolute;
              left:${x}px; top:${y}px;
              font-size:${(item.height || 12) * 1.5}px;
              white-space:pre;
              pointer-events:none;
              color:transparent;
              background:transparent;
              padding:2px 4px;
              margin:-2px -4px;
              border-radius:3px;
              transition:all 0.2s;
            `;
            
            textLayer.appendChild(span);
          });
        }

        // Smart field detection
        function detectFieldType(text, prevTexts) {
          const lowerText = text.toLowerCase().trim();
          const prevContext = prevTexts.slice(-3).join(' ').toLowerCase();

          for (const [fieldName, config] of Object.entries(EDITABLE_FIELDS)) {
            for (const pattern of config.patterns || []) {
              if (pattern.test(text.trim())) {
                if (fieldName === 'date' || fieldName === 'valid_until') {
                  if (prevContext.includes('valid until')) return 'valid_until';
                  if (prevContext.includes('date')) return 'date';
                }
                return fieldName;
              }
            }

            for (const ctx of config.contextBefore || []) {
              if (prevContext.includes(ctx.toLowerCase())) {
                if (!lowerText.includes(ctx.toLowerCase().replace(':', ''))) {
                  return fieldName;
                }
              }
            }
          }

          return null;
        }

        // Mark editable fields
        const textOverlays = container.querySelectorAll('.pi-text-overlay');
        const prevTexts = [];
        
        textOverlays.forEach((span) => {
          const text = span.dataset.originalText || '';
          prevTexts.push(text);
          
          const fieldType = detectFieldType(text, prevTexts);
          
          if (fieldType) {
            span.dataset.fieldType = fieldType;
            span.dataset.fieldLabel = EDITABLE_FIELDS[fieldType]?.label || fieldType;
            fieldSpans.push(span);
          }
        });

        // Mark long text blocks as description/notes
        textOverlays.forEach((span) => {
          const text = (span.dataset.originalText || '').trim();
          if (text.length > 20 && !span.dataset.fieldType) {
            const lowerText = text.toLowerCase();
            if (lowerText.includes('pleased to') || lowerText.includes('proposal') || lowerText.includes('works')) {
              span.dataset.fieldType = 'description';
              span.dataset.fieldLabel = 'Description';
              fieldSpans.push(span);
            }
          }
        });

        // Edit mode toggle
        $modal.find('.pi-edit-mode-btn').on('click', function() {
          editMode = !editMode;
          $(this).text(editMode ? 'View Mode' : 'Enable Edit Mode');
          $(this).css('background', editMode ? '#ef4444' : '#3b82f6');
          
          $modal.find('.pi-edit-status').text(
            editMode ? `Editing ${fieldSpans.length} editable fields` : 'Click "Enable Edit Mode" to start editing'
          );

          textOverlays.forEach(span => {
            const isField = span.dataset.fieldType;
            
            if (editMode && isField) {
              // ONLY editable fields become interactive
              span.contentEditable = true;
              span.style.pointerEvents = 'auto';
              span.style.color = '#000';
              span.style.background = 'rgba(59,130,246,0.2)';
              span.style.border = '1px dashed #3b82f6';
              span.style.cursor = 'text';
              span.title = `Edit: ${span.dataset.fieldLabel}`;
            } else {
              // Non-fields stay completely hidden/non-interactive
              span.contentEditable = false;
              span.style.pointerEvents = 'none';
              span.style.color = 'transparent';
              span.style.background = 'transparent';
              span.style.border = 'none';
              span.title = '';
            }
          });

          container.querySelectorAll('.pi-pdf-canvas').forEach(canvas => {
            canvas.style.opacity = editMode ? '0.001' : '1';
          });
        });

        // Save handler - uses pdf-lib on backend
        $modal.find('.pi-save-pdf-btn').on('click', async function() {
          const $btn = $(this);
          const originalText = $btn.text();
          $btn.prop('disabled', true).text('Saving...');

          const edits = [];
          const processedFields = new Set();

          textOverlays.forEach(span => {
            const originalText = span.dataset.originalText || '';
            const currentText = span.textContent || '';
            const fieldType = span.dataset.fieldType;

            if (currentText !== originalText) {
              if (fieldType && !processedFields.has(fieldType)) {
                edits.push({
                  field: fieldType,
                  text: currentText.trim(),
                  original: originalText
                });
                processedFields.add(fieldType);
              } else if (!fieldType) {
                const detected = detectFieldType(currentText, [originalText]);
                if (detected && !processedFields.has(detected)) {
                  edits.push({
                    field: detected,
                    text: currentText.trim(),
                    original: originalText
                  });
                  processedFields.add(detected);
                }
              }
            }
          });

          if (edits.length === 0) {
            alert('No changes detected.');
            $btn.prop('disabled', false).text(originalText);
            return;
          }

          try {
            const resp = await fetch(`${PI_Invoices.rest_base}/save-edited-pdf`, {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': PI_Invoices.nonce
              },
              body: JSON.stringify({ id, edits })
            });

            if (!resp.ok) throw new Error(await resp.text());

            const data = await resp.json();
            
            // Update local data
            const invIdx = invoices.findIndex(i => i.id === id);
            if (invIdx > -1) {
              edits.forEach(e => {
                if (e.field === 'amount') {
                  invoices[invIdx].amount = parseFloat(e.text.replace(/[^\d.]/g, '')) || 0;
                } else {
                  invoices[invIdx][e.field] = e.text;
                }
              });
              if (data.pdf_url) {
                invoices[invIdx].pdf_url = data.pdf_url;
              }
            }

            // Update View PDF link
            if (data.pdf_url) {
              $row.find('a[title="View PDF"]').attr('href', data.pdf_url);
            }
            
            alert('PDF saved successfully!');
            $modal.remove();
            render(currentPage);
            loadStats();

          } catch (err) {
            console.error('Save failed:', err);
            alert('Failed to save: ' + err.message);
            $btn.prop('disabled', false).text(originalText);
          }
        });

      } catch (err) {
        console.error('PDF load error:', err);
        container.innerHTML = `<p style="color:red;padding:20px;">Failed to load PDF: ${err.message}</p>`;
      }
    });

    // Search & Filter
    $searchInput.off('input').on('input', () => {
      allSelected = false;
      render(1);
    });
    $statusFilter.off('change').on('change', () => {
      allSelected = false;
      render(1);
    });
  }

  function updateSelectedCount() {
    let count = 0;
    if (allSelected) {
      count = getFilteredInvoices().length;
      $('.pi-select-message').html(`All ${count} proposals selected.`);
    } else {
      count = $('.pi-row-check:checked').length;
      const visibleTotal = $('.pi-row-check').length;
      if (count === visibleTotal && visibleTotal > 0) {
        const totalFiltered = getFilteredInvoices().length;
        if (totalFiltered > visibleTotal) {
          $('.pi-select-message').html(`All ${visibleTotal} on this page selected. <a href="#" class="pi-select-all-link">Select all ${totalFiltered} proposals</a>`);
        } else {
          $('.pi-select-message').empty();
        }
      } else {
        $('.pi-select-message').empty();
      }
    }
    $('.pi-selected-count').text(`Selected: ${count}`);
    $bulk.toggle(count > 0);
  }

  async function bulkAction(action, value = '') {
    let ids = [];
    if (allSelected) {
      ids = getFilteredInvoices().map(inv => inv.id);
    } else {
      ids = $('.pi-row-check:checked').map(function() {
        return parseInt($(this).closest('.pi-invoice-row').data('id'), 10);
      }).get();
    }
    if (ids.length === 0) return;

    const resp = await fetch(`${endpoint}/bulk`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
      body: JSON.stringify({ ids, action, value })
    });

    if (resp.ok) {
      load();
      loadStats();
      allSelected = false;
      $selectAll.prop('checked', false);
      $('.pi-row-check').prop('checked', false);
      $('.pi-select-message').empty();
      $bulk.hide();
    }
  }

  // === Cross-tab sync listener ===
  window.addEventListener('storage', function(e) {
    if (e.key === 'pi_crm_last_update') {
      try {
        const update = JSON.parse(e.newValue || '{}');
        console.log('[PI Invoices] Cross-tab update received:', update);
        
        // Reload data if invoice was created or synced from lead page
        if (update.type === 'invoice-created' || update.type === 'invoice-synced' || update.type === 'lead-updated') {
          console.log('[PI Invoices] Reloading due to cross-tab update:', update.type);
          load();
          loadStats();
        }
        
        // Handle lead deletion - refresh invoices list since linked invoice was deleted
        if (update.type === 'lead-deleted') {
          console.log('[PI Invoices] Lead deleted, refreshing invoices list. Invoice deleted:', update.invoiceDeleted);
          load();
          loadStats();
        }
      } catch (err) {
        console.warn('[PI Invoices] Failed to parse cross-tab update:', err);
      }
    }
  });
  
  // === Same-page event listener for immediate updates ===
  window.addEventListener('pi:invoice-synced', function(e) {
    console.log('[PI Invoices] Same-page invoice sync event received:', e.detail);
    load();
    loadStats();
  });
  
  window.addEventListener('pi:invoice-created', function(e) {
    console.log('[PI Invoices] Same-page invoice created event received:', e.detail);
    load();
    loadStats();
  });

  // Initialize
  loadStats();
  load();
});
