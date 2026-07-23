<?php
if (!defined('ABSPATH')) exit;

add_shortcode('planning_invoices', function () {
    if (!is_user_logged_in()) {
        return '<p>Please log in to view your proposals.</p>';
    }

    ob_start(); ?>
    
    <div id="pi-invoices-wrapper" class="pi-invoices-wrapper">
        <!-- Stats Boxes -->
        <div class="pi-stats-grid" id="pi-stats-grid">
            <!-- Filled by JS -->
        </div>

        <!-- Toolbar: Search, Filters, Create, Export -->
        <div class="pi-toolbar">
            <input type="text" id="pi-search-input" placeholder="Search proposals...">
            <select id="pi-status-filter">
                <option value="">All Statuses</option>
                <option value="draft">Draft</option>
                <option value="mailed">Mailed</option>
                <option value="won">Won</option>
                <option value="lost">Lost</option>
            </select>
            <button id="pi-create-proposal" class="pi-btn pi-btn-primary">+ Create Proposal</button>
            <button id="pi-export" class="pi-btn pi-btn-secondary">Export</button>
        </div>

        <!-- Bulk Actions Bar (hidden until selection) -->
        <div class="pi-bulk-actions" id="pi-bulk-actions" style="display:none;">
            <span class="pi-selected-count">Selected: 0</span>
            <button class="pi-bulk-btn pi-bulk-delete">Delete Selected</button>
            <button class="pi-bulk-btn pi-bulk-print">Print Selected</button>
            <select class="pi-bulk-status">
                <option value="">Set Status...</option>
                <option value="draft">Draft</option>
                <option value="mailed">Mailed</option>
                <option value="won">Won</option>
                <option value="lost">Lost</option>
            </select>
            <button class="pi-bulk-apply">Apply</button>
        </div>

        <!-- Table Structure -->
        <table id="pi-invoices-table" class="pi-table">
            <thead>
                <tr>
                    <th class="pi-col-check"><input type="checkbox" id="pi-select-all"></th>
                    <th class="pi-col-id">Proposal ID</th>
                    <th class="pi-col-address">Address</th>
                    <th class="pi-col-date">Date Created</th>
                    <th class="pi-col-amount">Proposed Amount (£)</th>
                    <th class="pi-col-status">Status</th>
                    <th class="pi-col-actions" style="text-align: center;">Actions</th>
                </tr>
            </thead>
            <tbody id="pi-invoices-list">
                <!-- Rows appended by JS -->
            </tbody>
        </table>

        <!-- Pagination (manual, filled by JS) -->
        <div class="pi-pagination">
            <!-- Filled by JS: Showing 1 to 10 of 50, Prev/Next buttons -->
        </div>
    </div>
    <?php
    return ob_get_clean();
});

add_shortcode('pi_business_settings', function() {
    if (!is_user_logged_in()) return '<div class="pi-settings-notice"><p>Log in to edit settings.</p></div>';

    $user_id = get_current_user_id();
    $business_info = get_user_meta($user_id, '_pi_business_info', true) ?: [];
    // Dummy data for previews (matches your placeholders)
    $dummy_data = [
        'logo' => 'http://planningindex.co.uk/wp-content/uploads/2026/01/white-on-tp-logo.png',  // Replace with a real placeholder image if needed
        'company_name' => 'Sample Company Ltd',
        'company_address' => "123 Business St\nLondon, SW1A 1AA",
        'phone' => '020 1234 5678',
        'email' => 'info@sample.com',
        'website' => 'www.sample.com',
        'date' => date('d/m/Y'),
        'valid_until' => date('d/m/Y', strtotime('+30 days')),
        'address' => "Client Name\n456 Client Rd\nLondon, EC1A 1BB",
        're_line' => 'Sample Project: Extension at Client Address',
        'description' => 'Detailed description of proposed works including materials and timeline.',
        'notes' => 'Additional notes: Subject to site survey. Prices exclude VAT.',
        'amount' => '5,000.00',
        'warranty' => '5-year structural warranty on all works.',
        'terms' => "30% deposit upon acceptance.\nBalance due on completion.\nAll works per building regulations.",
        'signature' => 'https://via.placeholder.com/200x50?text=Signature'  // Placeholder image
    ];
    // Handle form submit
    if (isset($_POST['pi_save_business'])) {
        check_admin_referer('pi_business_nonce');

        // Get existing business info to preserve logo/signature if not re-uploading
        $existing_info = get_user_meta($user_id, '_pi_business_info', true) ?: [];

        $business_info = [
            'company_name'     => sanitize_text_field($_POST['company_name'] ?? ''),
            'company_address'  => sanitize_textarea_field($_POST['company_address'] ?? ''),
            'phone'            => sanitize_text_field($_POST['phone'] ?? ''),
            'email'            => sanitize_email($_POST['email'] ?? ''),
            'website'          => esc_url_raw($_POST['website'] ?? ''),
            'default_terms'    => sanitize_textarea_field($_POST['default_terms'] ?? ''),
            'default_warranty' => sanitize_text_field($_POST['default_warranty'] ?? ''),
            'default_template' => sanitize_key($_POST['default_template'] ?? 'basic'),
            // Preserve existing logo and signature by default
            'logo'             => $existing_info['logo'] ?? '',
            'signature'        => $existing_info['signature'] ?? '',
            // Mark settings as updated from Settings page (takes precedence over checkout)
            // This timestamp is the AUTHORITATIVE marker that Settings have been saved
            'settings_updated_at' => current_time('mysql'),
        ];

        // Handle logo upload - only update if new file provided
        if (!empty($_FILES['logo']['name']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $upload = wp_upload_bits($_FILES['logo']['name'], null, file_get_contents($_FILES['logo']['tmp_name']));
            if (!$upload['error']) {
                $business_info['logo'] = $upload['url'];
            }
        }

        // Handle signature upload - only update if new file provided
        if (!empty($_FILES['signature']['name']) && $_FILES['signature']['error'] === UPLOAD_ERR_OK) {
            $upload = wp_upload_bits($_FILES['signature']['name'], null, file_get_contents($_FILES['signature']['tmp_name']));
            if (!$upload['error']) {
                $business_info['signature'] = $upload['url'];
            }
        }

        // ══════════════════════════════════════════════════════════════════════════════
        // STEP 1: BULLETPROOF SAVE — Delete ALL rows then add fresh
        // This prevents duplicate meta rows from causing stale reads
        // ══════════════════════════════════════════════════════════════════════════════
        global $wpdb;
        
        // Nuclear option: delete ALL _pi_business_info rows for this user via direct DB
        $wpdb->delete(
            $wpdb->usermeta,
            ['user_id' => $user_id, 'meta_key' => '_pi_business_info'],
            ['%d', '%s']
        );
        
        // Clear ALL caches after delete
        clean_user_cache($user_id);
        wp_cache_delete($user_id, 'user_meta');
        wp_cache_flush();
        
        // Add fresh single row
        add_user_meta($user_id, '_pi_business_info', $business_info, true);
        
        // Verify it actually saved by reading directly from DB
        $verify = $wpdb->get_var($wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = '_pi_business_info' LIMIT 1",
            $user_id
        ));
        
        if (!$verify) {
            // Fallback: try update_user_meta if add failed
            error_log("[PI Settings CRITICAL] add_user_meta failed for user #$user_id, trying update_user_meta");
            update_user_meta($user_id, '_pi_business_info', $business_info);
        }
        
        // Clear caches again after write to ensure fresh reads everywhere
        clean_user_cache($user_id);
        wp_cache_delete($user_id, 'user_meta');
        wp_cache_flush();
        
        // ══════════════════════════════════════════════════════════════════════════════
        // STEP 2: Clean up any old checkout-specific meta keys
        // ══════════════════════════════════════════════════════════════════════════════
        delete_user_meta($user_id, 'pmpc_default_template');
        delete_user_meta($user_id, 'pmpc_business_info');
        delete_user_meta($user_id, 'pmpe_default_template');
        delete_user_meta($user_id, 'pmpe_business_info');
        delete_user_meta($user_id, 'pmrb_default_template');
        delete_user_meta($user_id, 'pmrb_business_info');
        delete_user_meta($user_id, 'pmpc_trial_business_info');
        delete_user_meta($user_id, 'pmpc_trial_default_template');
        
        // ══════════════════════════════════════════════════════════════════════════════
        // STEP 3: Update all existing invoices to use the new template AND business info
        // ══════════════════════════════════════════════════════════════════════════════
        $invoices = get_user_meta($user_id, PII_INVOICES_META, true) ?: [];
        $new_template = $business_info['default_template'];
        $invoices_updated = false;
        
        foreach ($invoices as &$inv) {
            $inv['tmpl_key'] = $new_template;
            $invoices_updated = true;
            $inv['terms'] = $business_info['default_terms'] ?? '30% deposit, balance on completion.';
            $inv['warranty'] = $business_info['default_warranty'] ?? '5 years';
            $inv['company_name'] = $business_info['company_name'] ?? '';
            $inv['company_address'] = $business_info['company_address'] ?? '';
            $inv['phone'] = $business_info['phone'] ?? '';
            $inv['email'] = $business_info['email'] ?? '';
            $inv['website'] = $business_info['website'] ?? '';
            $inv['logo'] = $business_info['logo'] ?? '';
            $inv['signature'] = $business_info['signature'] ?? '';
        }
        unset($inv);
        
        if ($invoices_updated || !empty($invoices)) {
            update_user_meta($user_id, PII_INVOICES_META, $invoices);
        }
        
        // Verify the save worked
        $saved = get_user_meta($user_id, '_pi_business_info', true);
        error_log("[PI Settings SAVE] User #$user_id - Template: " . ($saved['default_template'] ?? 'none') . ", Company: " . ($saved['company_name'] ?? 'none') . ", Updated: " . ($saved['settings_updated_at'] ?? 'unknown'));

        // Fire action for other plugins/code that need to know settings changed
        do_action('pi_business_info_updated', $user_id, $business_info);
        do_action('pi_settings_saved', $user_id, $business_info);
    }

    // Get templates for preview
    $templates = defined('PI_PDF_TEMPLATES') ? PI_PDF_TEMPLATES : [];
    $current_template = $business_info['default_template'] ?? 'basic';

    ob_start(); ?>
    <div class="pi-settings-wrapper">
        <!-- Header -->
        <div class="pi-settings-hero">
            <div class="pi-settings-hero-content">
                <h1 class="pi-settings-title">Settings</h1>
                <p class="pi-settings-subtitle">Personalise your proposals and company presence</p>
            </div>
        </div>

        <!-- Tabs Navigation -->
        <div class="pi-settings-tabs">
            <button class="pi-tab-btn pi-tab-active" data-tab="company">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                Company
            </button>
            <button class="pi-tab-btn" data-tab="branding">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                Branding
            </button>
            <button class="pi-tab-btn" data-tab="templates">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                Templates
            </button>
            <button class="pi-tab-btn" data-tab="defaults">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                Defaults
            </button>
        </div>

        <!-- Success Message with Regenerate Option -->
        <?php if (isset($_POST['pi_save_business'])): ?>
        <div class="pi-settings-success" id="pi-settings-success">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <span class="pi-success-text">Settings saved successfully!</span>
            <button type="button" id="pi-regenerate-all" class="pi-regenerate-btn" style="margin-left: 20px; padding: 8px 16px; background: #3b82f6; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 500; display: inline-flex; align-items: center; gap: 8px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 4v6h-6"/><path d="M1 20v-6h6"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                Refresh All Existing Proposals
            </button>
        </div>
        <script>
        (function() {
            const regenerateBtn = document.getElementById('pi-regenerate-all');
            if (regenerateBtn) {
                regenerateBtn.addEventListener('click', async function() {
                    const btn = this;
                    const originalText = btn.innerHTML;
                    btn.disabled = true;
                    btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="pi-spin"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg> Regenerating...';
                    btn.style.background = '#6b7280';
                    
                    try {
                        const response = await fetch('/wp-json/pi/v1/workspace/invoices/regenerate-all', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>'
                            }
                        });
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg> ' + result.regenerated + ' Proposals Updated!';
                            btn.style.background = '#10b981';
                            
                            // Update success message
                            const successText = document.querySelector('.pi-success-text');
                            if (successText) {
                                successText.textContent = 'Settings saved and ' + result.regenerated + ' existing proposals refreshed with new settings!';
                            }
                        } else {
                            btn.innerHTML = 'Error - Try Again';
                            btn.style.background = '#ef4444';
                            btn.disabled = false;
                        }
                    } catch (error) {
                        console.error('Regenerate error:', error);
                        btn.innerHTML = 'Error - Try Again';
                        btn.style.background = '#ef4444';
                        btn.disabled = false;
                    }
                });
            }
        })();
        </script>
        <style>
        @keyframes pi-spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .pi-spin {
            animation: pi-spin 1s linear infinite;
        }
        .pi-regenerate-btn:hover {
            opacity: 0.9;
        }
        </style>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" class="pi-settings-form-new" id="pi-settings-form">
            <?php wp_nonce_field('pi_business_nonce'); ?>

            <!-- Company Tab -->
            <div class="pi-tab-content pi-tab-visible" data-tab="company">
                <div class="pi-settings-panel">
                    <div class="pi-panel-header">
                        <h2>Company Information</h2>
                        <p>Your business details appear on all proposals</p>
                    </div>
                    
                    <div class="pi-form-grid">
                        <div class="pi-form-field pi-full-width">
                            <label for="company_name">
                                <span class="pi-field-label">Company Name</span>
                                <span class="pi-field-required">*</span>
                            </label>
                            <div class="pi-input-wrapper">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                                <input type="text" id="company_name" name="company_name" 
                                       value="<?php echo esc_attr($business_info['company_name'] ?? ''); ?>" 
                                       required placeholder="Your Company Name">
                            </div>
                        </div>

                        <div class="pi-form-field pi-full-width">
                            <label for="company_address">
                                <span class="pi-field-label">Business Address</span>
                            </label>
                            <div class="pi-input-wrapper pi-input-textarea">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                <textarea id="company_address" name="company_address" rows="3" 
                                          placeholder="123 Business Street, City, Postcode"><?php echo esc_textarea($business_info['company_address'] ?? ''); ?></textarea>
                            </div>
                            <span class="pi-field-hint">This address will appear in your proposal letterhead</span>
                        </div>

                        <div class="pi-form-field">
                            <label for="phone">
                                <span class="pi-field-label">Phone Number</span>
                            </label>
                            <div class="pi-input-wrapper">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                                <input type="tel" id="phone" name="phone" 
                                       value="<?php echo esc_attr($business_info['phone'] ?? ''); ?>" 
                                       placeholder="+44 20 1234 5678">
                            </div>
                        </div>

                        <div class="pi-form-field">
                            <label for="email">
                                <span class="pi-field-label">Email Address</span>
                            </label>
                            <div class="pi-input-wrapper">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                                <input type="email" id="email" name="email" 
                                       value="<?php echo esc_attr($business_info['email'] ?? ''); ?>" 
                                       placeholder="hello@yourcompany.com">
                            </div>
                        </div>

                        <div class="pi-form-field pi-full-width">
                            <label for="website">
                                <span class="pi-field-label">Website</span>
                            </label>
                            <div class="pi-input-wrapper">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                                <input type="url" id="website" name="website" 
                                       value="<?php echo esc_attr($business_info['website'] ?? ''); ?>" 
                                       placeholder="https://yourcompany.com">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Branding Tab -->
            <div class="pi-tab-content" data-tab="branding">
                <div class="pi-settings-panel">
                    <div class="pi-panel-header">
                        <h2>Brand Assets</h2>
                        <p>Upload your logo and signature for professional proposals</p>
                    </div>

                    <div class="pi-upload-grid">
                        <!-- Logo Upload -->
                        <div class="pi-upload-card">
                            <div class="pi-upload-preview" id="logo-preview">
                                <?php if (!empty($business_info['logo'])): ?>
                                    <img src="<?php echo esc_url($business_info['logo']); ?>" alt="Company Logo">
                                    <button type="button" class="pi-remove-upload" data-target="logo">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                    </button>
                                <?php else: ?>
                                    <div class="pi-upload-placeholder">
                                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="pi-upload-info">
                                <h3>Company Logo</h3>
                                <p>Appears in proposal headers. PNG or JPG recommended.</p>
                                <label class="pi-upload-btn">
                                    <input type="file" id="logo" name="logo" accept="image/*">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                    <span>Choose File</span>
                                </label>
                            </div>
                        </div>

                        <!-- Signature Upload -->
                        <div class="pi-upload-card">
                            <div class="pi-upload-preview" id="signature-preview">
                                <?php if (!empty($business_info['signature'])): ?>
                                    <img src="<?php echo esc_url($business_info['signature']); ?>" alt="Signature">
                                    <button type="button" class="pi-remove-upload" data-target="signature">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                    </button>
                                <?php else: ?>
                                    <div class="pi-upload-placeholder">
                                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="pi-upload-info">
                                <h3>Signature</h3>
                                <p>Your digital signature for proposal sign-off.</p>
                                <label class="pi-upload-btn">
                                    <input type="file" id="signature" name="signature" accept="image/*">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                    <span>Choose File</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Templates Tab -->
            <div class="pi-tab-content" data-tab="templates">
                <div class="pi-settings-panel">
                    <div class="pi-panel-header">
                        <h2>Proposal Templates</h2>
                        <p>Choose a default template style for your proposals</p>
                    </div>

                    <div class="pi-template-grid">
                        <?php foreach ($templates as $key => $tmpl): ?>
                        <div class="pi-template-card <?php echo $current_template === $key ? 'pi-template-selected' : ''; ?>" data-template="<?php echo esc_attr($key); ?>">
                            <input type="radio" name="default_template" value="<?php echo esc_attr($key); ?>" 
                                   <?php checked($current_template, $key); ?> id="template-<?php echo esc_attr($key); ?>">
                            <label for="template-<?php echo esc_attr($key); ?>">
                                <div class="pi-template-preview">
                                    <div class="pi-template-preview-content" data-template="<?php echo esc_attr($key); ?>">
                                        <!-- Mini preview of template -->
                                        <div class="pi-mini-doc">
                                            <div class="pi-mini-header"></div>
                                            <div class="pi-mini-line pi-mini-line-title"></div>
                                            <div class="pi-mini-line"></div>
                                            <div class="pi-mini-line pi-mini-line-short"></div>
                                            <div class="pi-mini-block"></div>
                                            <div class="pi-mini-line"></div>
                                            <div class="pi-mini-line pi-mini-line-short"></div>
                                        </div>
                                    </div>
                                    <div class="pi-template-check">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                                    </div>
                                </div>
                                <div class="pi-template-name"><?php echo esc_html($tmpl['name']); ?></div>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="pi-template-preview-large" id="template-preview-large">
                        <div class="pi-preview-header">
                            <h3>Template Preview</h3>
                            <span class="pi-preview-name"><?php echo esc_html($templates[$current_template]['name'] ?? 'Basic Proposal'); ?></span>
                        </div>
                            <div class="pi-preview-document" id="template-preview-content">
                                <!-- Live preview will be rendered here -->
                            </div>
                    </div>
                </div>
            </div>

            <!-- Defaults Tab -->
            <div class="pi-tab-content" data-tab="defaults">
                <div class="pi-settings-panel">
                    <div class="pi-panel-header">
                        <h2>Proposal Defaults</h2>
                        <p>Set default values for new proposals</p>
                    </div>

                    <div class="pi-form-grid">
                        <div class="pi-form-field">
                            <label for="default_warranty">
                                <span class="pi-field-label">Standard Warranty</span>
                            </label>
                            <div class="pi-input-wrapper">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                <input type="text" id="default_warranty" name="default_warranty" 
                                       value="<?php echo esc_attr($business_info['default_warranty'] ?? '5 years'); ?>" 
                                       placeholder="e.g., 5 years">
                            </div>
                            <span class="pi-field-hint">The warranty period shown on all proposals</span>
                        </div>

                        <div class="pi-form-field pi-full-width">
                            <label for="default_terms">
                                <span class="pi-field-label">Terms & Conditions</span>
                            </label>
                            <div class="pi-input-wrapper pi-input-textarea">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                                <textarea id="default_terms" name="default_terms" rows="6" 
                                          placeholder="Enter your standard terms and conditions..."><?php echo esc_textarea($business_info['default_terms'] ?? '30% deposit, balance on completion.'); ?></textarea>
                            </div>
                            <div class="pi-char-count">
                                <span id="terms-count">0</span> characters
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sticky Save Bar -->
            <div class="pi-save-bar">
                <div class="pi-save-bar-content">
                    <div class="pi-save-info">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                        <span>Changes apply to all future proposals</span>
                    </div>
                    <button type="submit" name="pi_save_business" class="pi-save-settings-btn">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                        Save Settings
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Define template data FIRST before using it -->
    <script>
    const PI_TEMPLATES = <?php echo json_encode(PI_PDF_TEMPLATES); ?>;
    const PI_DUMMY_DATA = <?php echo json_encode($dummy_data); ?>;
    </script>
    
    <script>
    (function() {
        // Function to replace placeholders in HTML
        function replacePlaceholders(html, data) {
            return html.replace(/\[([a-z_]+)\]/g, (match, key) => {
                return data[key] || match;
            });
        }

        // Function to render large preview
        function renderLargePreview(templateKey) {
            if (typeof PI_TEMPLATES === 'undefined') return;
            const template = PI_TEMPLATES[templateKey];
            if (!template || !template.html) return;

            const processedHtml = replacePlaceholders(template.html, PI_DUMMY_DATA || {});
            const previewContent = document.getElementById('template-preview-content');
            if (previewContent) {
                previewContent.innerHTML = `
                    <div style="
                        background: white;
                        padding: 20px;
                        border: 1px solid #e5e5e5;
                        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                        max-height: 600px;
                        overflow-y: auto;
                        font-size: 10px;
                        transform: scale(0.9);
                        transform-origin: top left;
                    ">
                        ${processedHtml}
                    </div>
                `;
            }

            const previewName = document.querySelector('.pi-preview-name');
            if (previewName) {
                previewName.textContent = template.name;
            }
        }

        // Tab switching
        const tabBtns = document.querySelectorAll('.pi-tab-btn');
        const tabContents = document.querySelectorAll('.pi-tab-content');
        
        tabBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const tab = btn.dataset.tab;
                
                tabBtns.forEach(b => b.classList.remove('pi-tab-active'));
                tabContents.forEach(c => c.classList.remove('pi-tab-visible'));
                
                btn.classList.add('pi-tab-active');
                document.querySelector(`.pi-tab-content[data-tab="${tab}"]`).classList.add('pi-tab-visible');
            });
        });

        // File upload preview - use correct preview container IDs
        document.querySelectorAll('input[type="file"]').forEach(input => {
            input.addEventListener('change', function() {
                const file = this.files[0];
                if (!file) return;
                
                // The preview IDs are 'logo-preview' and 'signature-preview'
                const previewId = this.id + '-preview';
                const preview = document.getElementById(previewId);
                
                if (file && preview) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        preview.innerHTML = `
                            <img src="${e.target.result}" alt="Preview">
                            <button type="button" class="pi-remove-upload" data-target="${input.id}">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            </button>
                        `;
                    };
                    reader.readAsDataURL(file);
                }
                
                // Update button text
                const label = this.closest('label');
                if (label) {
                    const span = label.querySelector('span');
                    if (span) span.textContent = file.name;
                }
            });
        });

        // Remove upload handler
        document.addEventListener('click', function(e) {
            if (e.target.closest('.pi-remove-upload')) {
                const btn = e.target.closest('.pi-remove-upload');
                const targetId = btn.dataset.target;
                const input = document.getElementById(targetId);
                const preview = document.getElementById(targetId + '-preview');
                
                if (input) input.value = '';
                if (preview) {
                    preview.innerHTML = `
                        <div class="pi-upload-placeholder">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                        </div>
                    `;
                }
            }
        });

        // Template selection - single event handler
        document.querySelectorAll('.pi-template-card').forEach(card => {
            card.addEventListener('click', function() {
                document.querySelectorAll('.pi-template-card').forEach(c => c.classList.remove('pi-template-selected'));
                this.classList.add('pi-template-selected');
                this.querySelector('input[type="radio"]').checked = true;
                
                // Update preview with selected template
                const templateKey = this.dataset.template;
                renderLargePreview(templateKey);
            });
        });

        // Character count for terms
        const termsTextarea = document.getElementById('default_terms');
        const termsCount = document.getElementById('terms-count');
        if (termsTextarea && termsCount) {
            const updateCount = () => termsCount.textContent = termsTextarea.value.length;
            termsTextarea.addEventListener('input', updateCount);
            updateCount();
        }

        // Dismiss success message
        const successMsg = document.querySelector('.pi-settings-success');
        if (successMsg) {
            setTimeout(() => {
                successMsg.style.opacity = '0';
                successMsg.style.transform = 'translateY(-10px)';
                setTimeout(() => successMsg.remove(), 300);
            }, 4000);
        }

        // Initial render for default template on page load
        const defaultTemplate = document.querySelector('input[name="default_template"]:checked')?.value || 'basic';
        renderLargePreview(defaultTemplate);
    })();
    </script>
    <?php
    return ob_get_clean();
});
