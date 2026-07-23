<?php
if (!defined('ABSPATH')) {
    exit;
}
// CRITICAL: Ensure no session/checkout data interferes with Settings
add_action('rest_api_init', function() {
    // Guard against early execution when core functions aren't loaded yet
    if (!function_exists('is_user_logged_in') || !did_action('init')) {
        return;
    }
    // Clear any checkout session data for the current user on REST API calls
    // This prevents checkout data from leaking into Settings-aware endpoints
    if (is_user_logged_in() && session_id()) {
        $user_id = get_current_user_id();
        if (pmpc_should_use_settings($user_id)) {
            unset($_SESSION['pmpc_checkout_session']);
            unset($_SESSION['pmpe_checkout_session']);
            unset($_SESSION['pmrb_checkout_session']);
            unset($_SESSION['pmpc_trial_session']);
        }
    }
}, 1);
// Load the new AcroForm PDF editor (this is the magic)
require_once __DIR__ . '/pdf-editor.php';
// ══════════════════════════════════════════════════════════════════════════════
// CRITICAL: Template Selection Always Uses Current Settings
// NEVER use stored invoice template - always use current settings
// ══════════════════════════════════════════════════════════════════════════════
// ══════════════════════════════════════════════════════════════════════════════
// HELPER: Get FRESH business info, bypassing all caches
// This is the ONLY function that should be used to get business info for PDFs
// It ensures Settings page data ALWAYS takes precedence over checkout data
// ══════════════════════════════════════════════════════════════════════════════
if (!function_exists('pi_get_fresh_business_info')) {
    function pi_get_fresh_business_info(int $user_id): array {
        global $wpdb;
        
        // Clear ALL cached data aggressively
        clean_user_cache($user_id);
        wp_cache_delete($user_id, 'user_meta');
        wp_cache_flush();
        
        // DIRECT DB QUERY - bypass WordPress meta cache entirely
        // This is the ONLY way to guarantee we read the latest saved value
        $raw = $wpdb->get_var($wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = '_pi_business_info' ORDER BY umeta_id DESC LIMIT 1",
            $user_id
        ));
        
        $business = [];
        if ($raw) {
            $unserialized = maybe_unserialize($raw);
            if (is_array($unserialized)) {
                $business = $unserialized;
            }
        }
        
        // Provide defaults for required fields
        $defaults = [
            'company_name' => 'Your Company',
            'company_address' => '',
            'phone' => '',
            'email' => '',
            'website' => '',
            'default_terms' => '30% deposit, balance on completion.',
            'default_warranty' => '5 years',
            'default_template' => 'basic',
            'logo' => '',
            'signature' => '',
        ];
        
        $result = array_merge($defaults, $business);
        
        error_log("[PI Fresh Info] User #$user_id template: " . $result['default_template'] . " | source: " . ($result['source'] ?? 'unknown') . " | settings_updated: " . ($result['settings_updated_at'] ?? 'never'));
        
        return $result;
    }
}
// ══════════════════════════════════════════════════════════════════════════════
// CRITICAL: Template Selection Always Uses Current Settings
// ══════════════════════════════════════════════════════════════════════════════
function pi_get_current_template_from_settings(int $user_id, string $app_desc = ''): string {
    $business = pi_get_fresh_business_info($user_id);
    $tmpl_key = $business['default_template'] ?? 'basic';
    
    // Override with window template only if auto-detect and keywords match
    if ($tmpl_key === 'auto' && stripos($app_desc, 'window') !== false) {
        $tmpl_key = 'window';
    }
    
    return $tmpl_key;
}
// ══════════════════════════════════════════════════════════════════════════════
// CRITICAL: Aggressive Cache Clearing
// ══════════════════════════════════════════════════════════════════════════════
if (!function_exists('pi_clear_all_user_caches')) {
    function pi_clear_all_user_caches(int $user_id): void {
        // WordPress core caches
        clean_user_cache($user_id);
        wp_cache_delete($user_id, 'user_meta');
        wp_cache_flush();
        
        // Common object cache plugins
        if (function_exists('wp_cache_delete_group')) {
            wp_cache_delete_group('user_meta');
        }
        
        // Transients
        delete_transient('pi_business_info_' . $user_id);
        delete_transient('pi_user_settings_' . $user_id);
        
        // Session data
        if (session_id()) {
            unset($_SESSION['pmpc_checkout_session']);
            unset($_SESSION['pmpe_checkout_session']);
            unset($_SESSION['pmrb_checkout_session']);
            unset($_SESSION['pmpc_trial_session']);
        }
        
        error_log("[PI Cache] Cleared all caches for user #$user_id");
    }
}
// === Helper: convert a stored pdf_url into a reliable absolute filesystem path
if (!function_exists('pi_get_pdf_path_from_url')) {
    function pi_get_pdf_path_from_url(string $pdf_url): string {
        $upload = wp_upload_dir();
        $basedir = rtrim($upload['basedir'], '/');
        $baseurl = rtrim($upload['baseurl'], '/');
        // If it's already a filesystem path, return as-is
        if (file_exists($pdf_url)) {
            return $pdf_url;
        }
        // If the pdf_url contains the uploads baseurl, map it directly
        if (strpos($pdf_url, $baseurl) !== false) {
            $rel = substr($pdf_url, strlen($baseurl));
            return $basedir . $rel;
        }
        // Fallback: try wp_make_link_relative and join with basedir safely
        $rel = wp_make_link_relative($pdf_url);
        $rel = '/' . ltrim($rel, '/');
        return $basedir . $rel;
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// HELPER: Format UK address for professional letter display
// Parses raw address string and formats it with proper line breaks:
// - Street address (number + street name)
// - Town/City
// - Postcode
// - United Kingdom
// ══════════════════════════════════════════════════════════════════════════════
if (!function_exists('pi_format_uk_address')) {
    function pi_format_uk_address(string $raw_address): string {
        // Clean up the raw address
        $address = trim($raw_address);
        
        // Remove trailing "United Kingdom" or "UK" if already present
        $address = preg_replace('/,?
*\s*(?:united kingdom|uk|u\.k\.?)$/i', '', $address);
        
        // Normalize commas and whitespace
        $address = preg_replace('/\s*,\s*/', ', ', $address);
        
        // UK postcode pattern: matches formats like E16 3HH, SW1A 1AA, etc.
        // Format: Area code (1-2 letters, optional 1 digit, optional 1 letter) + space + 1 digit + 2 letters
        $postcode_pattern = '/[A-Z]{1,2}[0-9][A-Z0-9]?\s?[0-9][A-Z]{2}/i';
        
        $lines = [];
        $postcode_found = false;
        $postcode = '';
        
        // Extract postcode if present
        if (preg_match($postcode_pattern, $address, $matches)) {
            $postcode = $matches[0];
            $postcode_found = true;
            // Remove postcode from address for now
            $address = preg_replace($postcode_pattern, '', $address, 1);
            $address = trim(preg_replace('/\s*,\s*$/', '', $address));
        }
        
        // Split remaining address by commas
        $parts = array_map('trim', explode(',', $address));
        $parts = array_filter($parts); // Remove empty parts
        
        // Rebuild the address with proper structure
        $street_parts = [];
        $town = '';
        
        // Common UK towns/localities often found at the end before postcode
        // These are typically the last non-postcode element
        $common_localities = [
            'london', 'birmingham', 'manchester', 'leeds', 'glasgow', 'sheffield', 'bradford',
            'liverpool', 'edinburgh', 'bristol', 'cardiff', 'belfast', 'leicester', 'coventry',
            'kingston upon hull', 'bolton', 'sunderland', 'wolverhampton', 'swansea', 'derby',
            'plymouth', 'aberdeen', 'westminster', 'cambridge', 'oxford', 'southampton',
            'york', 'portsmouth', 'exeter', 'bath', 'norwich', 'preston', 'blackpool',
            'nottingham', 'reading', 'ipswich', 'colchester', 'southend', 'hastings',
            'cheltenham', 'gloucester', 'swindon', 'salisbury', 'winchester', 'chichester',
            'brighton', 'hove', 'eastbourne', 'worthing', 'crawley', 'redhill', 'guildford',
            'farnham', 'alton', 'basingstoke', 'newbury', 'wokingham', 'bracknell', 'maidenhead',
            'slough', 'high wycombe', 'hemel hempstead', 'watford', 'st albans', 'stevenage',
            'hertford', 'bishops stortford', 'harlow', 'chelmsford', 'southend', 'basildon',
            'grays', 'dartford', 'maidstone', 'canterbury', 'ashford', 'folkestone', 'dover',
            'crawley', 'horsham', 'lewes', 'east grinstead', 'tonbridge', 'tunbridge wells',
            'sevenoaks', 'orpington', 'bromley', 'croydon', 'sutton', 'wimbledon', 'kingston',
            'richmond', 'twickenham', 'hounslow', 'feltham', 'staines', 'sunbury', 'weybridge',
            'epsom', 'banstead', 'reigate', 'dorking', 'leatherhead', 'esher', 'walton',
            'weybridge', 'chertsey', 'woking', 'camberley', 'farnborough', 'aldershot',
            'fleet', 'bordon', 'haslemere', 'godalming', 'hindhead', 'petworth', 'midhurst',
            'petersfield', 'liss', 'liphook', 'bordon', 'alton', 'four marks', 'bentworth',
            'medstead', 'east tisted', 'privett', 'west tisted', 'froyle', 'crondall',
            'odiham', 'hook', 'hartley wintney', 'farnham', 'frensham', 'churt', 'hindhead'
        ];
        
        if (!empty($parts)) {
            // Check if the last part is a known town/locality
            $last_part_lower = strtolower(end($parts));
            $is_locality = false;
            
            foreach ($common_localities as $locality) {
                if (strpos($last_part_lower, $locality) !== false) {
                    $is_locality = true;
                    break;
                }
            }
            
            // Also check if it looks like a town (single word or short phrase, not containing numbers)
            if (!$is_locality && !preg_match('/\d/', $last_part_lower)) {
                // Could be a town - check if it's a reasonable length for a town
                if (strlen($last_part_lower) > 2 && strlen($last_part_lower) < 30) {
                    $is_locality = true;
                }
            }
            
            if ($is_locality) {
                // Last part is the town
                $town = array_pop($parts);
            }
            
            // Everything else is the street address
            if (!empty($parts)) {
                // Join street parts with newlines if there are multiple
                $street_address = implode(', ', $parts);
                $lines[] = $street_address;
            }
            
            // Add town if found
            if (!empty($town)) {
                $lines[] = $town;
            }
        }
        
        // Add postcode if found
        if ($postcode_found && !empty($postcode)) {
            $lines[] = strtoupper($postcode);
        }
        
        // Add United Kingdom
        $lines[] = 'United Kingdom';
        
        // Join all lines with proper line breaks
        $formatted_address = implode("\n", $lines);
        
        // Clean up any double commas or extra whitespace
        $formatted_address = preg_replace('/,\s*,/', ',', $formatted_address);
        $formatted_address = preg_replace('/\n+/', "\n", $formatted_address);
        
        return trim($formatted_address);
    }
}
add_action('rest_api_init', function () {
    $namespace = 'pi/v1';
    
    // ═══════════════════════════════════════════════════════════════
    // GET PROPOSAL TEMPLATES - For checkout and settings
    // ═══════════════════════════════════════════════════════════════
    register_rest_route($namespace, '/templates', [
        'methods' => 'GET',
        'permission_callback' => '__return_true', // Public - needed for checkout
        'callback' => function () {
            $templates = [];
            
            // Get templates from PI_PDF_TEMPLATES if defined
            if (defined('PI_PDF_TEMPLATES') && is_array(PI_PDF_TEMPLATES)) {
                foreach (PI_PDF_TEMPLATES as $key => $tmpl) {
                    $templates[$key] = [
                        'name' => $tmpl['name'] ?? ucfirst($key),
                        'description' => $tmpl['description'] ?? '',
                        'html' => $tmpl['html'] ?? '', // Include HTML for preview
                    ];
                }
            }
            
            // Fallback to basic templates if PI_PDF_TEMPLATES not available
            if (empty($templates)) {
                $templates = [
                    'basic' => [
                        'name' => 'Basic',
                        'description' => 'Clean and simple proposal layout',
                        'html' => ''
                    ],
                    'professional' => [
                        'name' => 'Professional',
                        'description' => 'Formal business layout with letterhead',
                        'html' => ''
                    ],
                    'modern' => [
                        'name' => 'Modern',
                        'description' => 'Contemporary design with sidebar',
                        'html' => ''
                    ],
                    'window' => [
                        'name' => 'Window Specialist',
                        'description' => 'Tailored for window installation quotes',
                        'html' => ''
                    ]
                ];
            }
            
            // Get user's current template selection if logged in
            $current_template = 'basic';
            if (is_user_logged_in()) {
                $user_id = get_current_user_id();
                $business_info = get_user_meta($user_id, '_pi_business_info', true) ?: [];
                $current_template = $business_info['default_template'] ?? 'basic';
            }
            
            return rest_ensure_response([
                'templates' => $templates,
                'current' => $current_template,
                'dummy_data' => [
                    'logo' => '',
                    'company_name' => 'Your Company Ltd',
                    'company_address' => "123 Business Street\nLondon, SW1A 1AA",
                    'phone' => '020 1234 5678',
                    'email' => 'info@yourcompany.com',
                    'website' => 'www.yourcompany.com',
                    'date' => date('d/m/Y'),
                    'valid_until' => date('d/m/Y', strtotime('+30 days')),
                    'address' => "Client Name\n456 Client Road\nLondon, EC1A 1BB",
                    're_line' => 'Proposal for Works at Client Address',
                    'description' => 'We are pleased to submit our proposal for works at the above address.',
                    'notes' => 'Additional notes and specifications for the project.',
                    'amount' => '2,500.00',
                    'warranty' => '5-year structural warranty',
                    'terms' => "30% deposit upon acceptance.\nBalance due on completion.",
                    'signature' => ''
                ]
            ]);
        }
    ]);
    
    // Get invoices
    register_rest_route($namespace, '/workspace/invoices', [
        'methods' => 'GET',
        'permission_callback' => fn() => is_user_logged_in(),
        'callback' => function () {
            $invoices = get_user_meta(get_current_user_id(), PII_INVOICES_META, true);
            if (!is_array($invoices)) {
                $invoices = [];
            }
            return rest_ensure_response($invoices);
        }
    ]);
    // Stats - now synced with pipeline stages
    register_rest_route($namespace, '/workspace/invoices/stats', [
        'methods' => 'GET',
        'permission_callback' => fn() => is_user_logged_in(),
        'callback' => function () {
            $invoices = get_user_meta(get_current_user_id(), PII_INVOICES_META, true) ?: [];
            $total_proposed = $contacted = $negotiation = $won_value = $average_amount = 0;
            $count = count($invoices);
            
            foreach ($invoices as $inv) {
                $amount = floatval($inv['amount'] ?? 0);
                $total_proposed += $amount;
                
                // Count by synced stage status
                if ($inv['status'] === 'contacted') {
                    $contacted++;
                }
                if ($inv['status'] === 'negotiation') {
                    $negotiation++;
                }
                if ($inv['status'] === 'won') {
                    $won_value += $amount;
                }
            }
            
            if ($count > 0) {
                $average_amount = $total_proposed / $count;
            }
            
            return [
                'total_proposed' => number_format($total_proposed, 2),
                'contacted' => $contacted,
                'negotiation' => $negotiation,
                'won_value' => number_format($won_value, 2),
                'average_amount' => number_format($average_amount, 2)
            ];
        }
    ]);
    // Add invoice from lead - FULL PRICING SYNC FROM LEAD PAGE
    // This endpoint MUST use the exact totals passed from lead-single.js
    register_rest_route($namespace, '/workspace/invoices/add', [
        'methods' => 'POST',
        'permission_callback' => fn() => is_user_logged_in(),
        'callback' => function (WP_REST_Request $req) {
            $user = get_current_user_id();
            
            // Get lead identifiers
            $pi_lead_id = intval($req['pi_lead_id'] ?? $req['lead_id'] ?? 0);
            $planning_app_id = intval($req['planning_app_id'] ?? 0);
            
            // CRITICAL: Get the exact amounts passed from lead-single.js
            // These MUST be used as-is, not recalculated
            $grand_total = floatval($req['est'] ?? 0);
            $subtotal = floatval($req['subtotal'] ?? 0);
            $vat = floatval($req['vat'] ?? 0);
            $pricing_details = $req['pricing_details'] ?? [];
            $due = sanitize_text_field($req['due'] ?? '');
            $notes = sanitize_textarea_field($req['notes'] ?? '');
            
            error_log("[PI Invoice Add] Received request - pi_lead_id: $pi_lead_id, planning_app_id: $planning_app_id, grand_total: $grand_total, subtotal: $subtotal, vat: $vat");
            
            // If pi_lead_id provided, get planning_app_id from lead if not already provided
            if ($pi_lead_id > 0 && get_post_type($pi_lead_id) === PI_LEAD_CPT) {
                $lead_meta = get_post_meta($pi_lead_id);
                
                // Get planning_app_id from lead if not provided
                if (!$planning_app_id) {
                    $planning_app_id = intval($lead_meta[PI_LEAD_META_PREFIX . 'linked_planning_app_id'][0] ?? 0);
                }
                
                // If no amount provided from request, calculate from lead pricing_details
                // This is a fallback - normally the frontend should provide exact amounts
                if ($grand_total <= 0) {
                    $lead_pricing = maybe_unserialize($lead_meta[PI_LEAD_META_PREFIX . 'pricing_details'][0] ?? 'a:0:{}');
                    if (is_array($lead_pricing) && count($lead_pricing) > 0) {
                        $subtotal = 0;
                        foreach ($lead_pricing as $item) {
                            $subtotal += (floatval($item['price'] ?? 0) * intval($item['qty'] ?? 1));
                        }
                        $vat = $subtotal * 0.20;
                        $grand_total = $subtotal + $vat;
                        $pricing_details = $lead_pricing;
                        error_log("[PI Invoice Add] Calculated from lead pricing - subtotal: $subtotal, vat: $vat, grand_total: $grand_total");
                    } else {
                        // Last resort - use estimated_value from lead meta
                        $grand_total = floatval($lead_meta[PI_LEAD_META_PREFIX . 'estimated_value'][0] ?? 0);
                        error_log("[PI Invoice Add] Using lead estimated_value: $grand_total");
                    }
                }
                
                // Get notes from lead if not provided
                if (empty($notes)) {
                    $notes = $lead_meta[PI_LEAD_META_PREFIX . 'notes'][0] ?? '';
                }
                
                // Get due date from lead if not provided
                if (empty($due)) {
                    $due = $lead_meta[PI_LEAD_META_PREFIX . 'due_date'][0] ?? '';
                }
            }
            
            // Validate planning_app exists
            $lead_id = $planning_app_id ?: $pi_lead_id;
            if (!$planning_app_id || get_post_type($planning_app_id) !== 'planning_app') {
                // Try using pi_lead_id's linked planning app
                if ($pi_lead_id > 0) {
                    $planning_app_id = intval(get_post_meta($pi_lead_id, PI_LEAD_META_PREFIX . 'linked_planning_app_id', true));
                }
            }
            
            if (!$planning_app_id || get_post_type($planning_app_id) !== 'planning_app') {
                return new WP_Error('invalid_lead', 'No valid planning application found', ['status' => 400]);
            }
            
            $post = get_post($planning_app_id);
            $meta = get_post_meta($planning_app_id);
            
            // Address logic - format for professional letter display
            $rawAddress = $meta['address'][0] ?? '';
            if (!$rawAddress && $post->post_content) {
                $dom = new DOMDocument();
                @$dom->loadHTML($post->post_content);
                foreach ($dom->getElementsByTagName('p') as $p) {
                    $t = trim($p->textContent);
                    if (preg_match('/^Address:/i', $t)) {
                        $rawAddress = preg_replace('/^Address:\s*/i', '', $t);
                        break;
                    }
                }
            }
            if (!$rawAddress) {
                $rawAddress = $post->post_title ?: 'Unknown Address';
            }
            // Capitalize each word for display
            $capitalizedAddress = preg_replace_callback(
                '/(^|[\s\-\/\(\)\,\.])([a-z0-9])/i',
                fn($m) => $m[1] . strtoupper($m[2]),
                strtolower($rawAddress)
            );
            // Format address for professional letter (line breaks + United Kingdom)
            $address = pi_format_uk_address($capitalizedAddress);
            $app_desc = $meta['description'][0] ?? strip_tags($post->post_content);
            
            // Get FRESH business info - always bypasses cache
            // This ensures Settings page data takes precedence over checkout data
            $business = pi_get_fresh_business_info($user);
            $tmpl_key = $business['default_template'] ?? 'basic';
            if ($tmpl_key === 'auto' && stripos($app_desc, 'window') !== false) {
                $tmpl_key = 'window';
            }
            
            error_log("[PI Invoice Add] Using template: $tmpl_key, Company: " . ($business['company_name'] ?? 'none'));
            
            // Check for existing invoice for this lead to prevent duplicates
            $invoices = get_user_meta($user, PII_INVOICES_META, true) ?: [];
            foreach ($invoices as $existing) {
                if ($existing['lead_id'] === $planning_app_id || 
                    ($pi_lead_id > 0 && isset($existing['pi_lead_id']) && $existing['pi_lead_id'] === $pi_lead_id)) {
                    return new WP_Error('duplicate', 'Invoice already exists for this lead', ['status' => 400]);
                }
            }
            
            // Generate new invoice ID
            $new_id = count($invoices) + 1;
            
            // Build re_line
            $re_line_value = ($tmpl_key === 'window')
                ? "Proposal for Window Installation at {$address}."
                : "Overture to contract services in relation to the successfully granted planning application at {$address}.";
            
            // Determine invoice status from lead stage
            $lead_stage = $pi_lead_id > 0 ? get_post_meta($pi_lead_id, PI_LEAD_META_PREFIX . 'stage', true) : 'new_lead';
            $stage_to_invoice_status = [
                'new_lead' => 'draft',
                'proposal_sent' => 'draft',
                'contacted' => 'contacted',
                'negotiation' => 'negotiation',
                'won' => 'won'
            ];
            $invoice_status = $stage_to_invoice_status[$lead_stage] ?? 'draft';
            
            // Create the invoice record with EXACT amounts from frontend
            $invoices[] = [
                'id' => $new_id,
                'lead_id' => $planning_app_id,
                'pi_lead_id' => $pi_lead_id,
                'address' => $address,
                'pdf_url' => '',
                'original_url' => $meta['info_url'][0] ?? '#',
                'status' => $invoice_status, // Synced from lead stage
                'created' => current_time('mysql'),
                // CRITICAL: Use the exact amounts passed from frontend
                'amount' => $grand_total,
                'subtotal' => $subtotal,
                'vat' => $vat,
                'pricing_details' => $pricing_details,
                'tmpl_key' => $tmpl_key,
                'description' => 'We are pleased to submit our proposal for works at the above address.',
                'notes' => $notes ?: $app_desc,
                're_line' => $re_line_value,
                'terms' => $business['default_terms'] ?? '30% deposit, balance on completion.',
                'warranty' => $business['default_warranty'] ?? '5 years',
                'date' => date('d/m/Y'),
                'valid_until' => $due ? date('d/m/Y', strtotime($due)) : date('d/m/Y', strtotime('+30 days')),
            ];
            
            error_log("[PI Invoice Add] Created invoice #$new_id with amount: $grand_total");
            
            update_user_meta($user, PII_INVOICES_META, $invoices);
            if (function_exists('clean_user_cache')) {
                clean_user_cache($user);
            }
            
            // Mark invoice as generated on lead
            if ($pi_lead_id > 0) {
                update_post_meta($pi_lead_id, PI_LEAD_META_PREFIX . 'invoice_generated', true);
                update_post_meta($pi_lead_id, PI_LEAD_META_PREFIX . 'invoice_id', $new_id);
            }
            
            // Generate PDF
            $upload_dir = wp_upload_dir();
            $pdf_dir = $upload_dir['basedir'] . '/planning-proposals/';
            if (!file_exists($pdf_dir)) {
                wp_mkdir_p($pdf_dir);
            }
            $filename = 'proposal-' . $new_id . '-' . $user . '-' . uniqid() . '.pdf';
            $filepath = $pdf_dir . $filename;
            $pdf_url = $upload_dir['baseurl'] . '/planning-proposals/' . $filename;
            // Prepare PDF data with EXACT amount from frontend
            $pdf_data = [
                'company_name' => $business['company_name'] ?? '',
                'company_address' => $business['company_address'] ?? '',
                'phone' => $business['phone'] ?? '',
                'email' => $business['email'] ?? '',
                'website' => $business['website'] ?? '',
                'date' => date('d/m/Y'),
                'valid_until' => $due ? date('d/m/Y', strtotime($due)) : date('d/m/Y', strtotime('+30 days')),
                // CRITICAL: Format the exact grand_total for PDF display
                'amount' => number_format($grand_total, 2),
                'terms' => $business['default_terms'] ?? '30% deposit, balance on completion.',
                'warranty' => $business['default_warranty'] ?? '5 years',
                'description' => 'We are pleased to submit our proposal for works at the above address.',
                'address' => $address,
                're_line' => $re_line_value,
                'logo' => $business['logo'] ?? '',
                'signature' => $business['signature'] ?? '',
                'notes' => $notes ?: $app_desc,
            ];
            
            error_log("[PI Invoice Add] Generating PDF with amount: " . number_format($grand_total, 2));
            if (PI_PDF_Editor::generate_or_update($tmpl_key, $pdf_data, $filepath)) {
                if (!file_exists($filepath)) {
                    error_log('PI_PDF_Editor: PDF generated but file not found at ' . $filepath . ' - permissions issue?');
                    return new WP_Error('pdf_fail', 'PDF generation failed (file not created)', ['status' => 500]);
                }
                // Save PDF URL
                $invoices = get_user_meta($user, PII_INVOICES_META, true) ?: [];
                foreach ($invoices as &$i) {
                    if ($i['id'] === $new_id) {
                        $i['pdf_url'] = $pdf_url;
                        break;
                    }
                }
                update_user_meta($user, PII_INVOICES_META, $invoices);
                return rest_ensure_response([
                    'added' => true,
                    'id' => $new_id,
                    'pdf_url' => $pdf_url,
                    'amount' => $grand_total,
                    'subtotal' => $subtotal,
                    'vat' => $vat
                ]);
            } else {
                error_log('[PI Invoice Add] PDF generation failed completely for ID ' . $new_id);
                return new WP_Error('pdf_fail', 'PDF creation failed', ['status' => 500]);
            }
            
            // QBO Integration: Trigger proposal created hook
            $proposal_data = [
                'id' => $new_id,
                'lead_id' => $planning_app_id,
                'pi_lead_id' => $pi_lead_id,
                'amount' => $grand_total,
                'status' => $invoice_status,
                'pdf_url' => $pdf_url ?? '',
                'user_id' => $user,
            ];
            do_action('pi_proposal_created', $new_id, $proposal_data);
        }
    ]);
    
    // SYNC INVOICE FROM LEAD - Called when lead pricing changes
    // This is the CORE endpoint for dynamic price synchronization
    register_rest_route($namespace, '/workspace/invoices/sync_from_lead', [
        'methods' => 'POST',
        'permission_callback' => fn() => is_user_logged_in(),
        'callback' => function (WP_REST_Request $req) {
            $user_id = get_current_user_id();
            $invoice_id = intval($req['invoice_id'] ?? 0);
            $lead_id = intval($req['lead_id'] ?? $req['pi_lead_id'] ?? 0);
            $planning_app_id = intval($req['planning_app_id'] ?? 0);
            
            // Get new values from lead pricing table
            $amount = floatval($req['amount'] ?? 0);
            $subtotal = floatval($req['subtotal'] ?? 0);
            $vat = floatval($req['vat'] ?? 0);
            $pricing_details = $req['pricing_details'] ?? [];
            $notes = sanitize_textarea_field($req['notes'] ?? '');
            
            error_log("[PI Invoice Sync] Starting sync - invoice_id: $invoice_id, lead_id: $lead_id, amount: $amount");
            
            $invoices = get_user_meta($user_id, PII_INVOICES_META, true) ?: [];
            $inv = null;
            $index = -1;
            
            // Find invoice by ID or by lead_id
            foreach ($invoices as $k => $i) {
                if ($invoice_id > 0 && $i['id'] === $invoice_id) {
                    $inv = $i;
                    $index = $k;
                    break;
                }
                if ($lead_id > 0 && ($i['lead_id'] === $lead_id || ($i['pi_lead_id'] ?? 0) === $lead_id)) {
                    $inv = $i;
                    $index = $k;
                    break;
                }
                if ($planning_app_id > 0 && $i['lead_id'] === $planning_app_id) {
                    $inv = $i;
                    $index = $k;
                    break;
                }
            }
            
            if (!$inv) {
                error_log("[PI Invoice Sync] Invoice not found for lead_id: $lead_id, invoice_id: $invoice_id");
                return new WP_Error('not_found', 'Invoice not found for this lead', ['status' => 404]);
            }
            
            $old_amount = $inv['amount'] ?? 0;
            error_log("[PI Invoice Sync] Found invoice #{$inv['id']} - old amount: $old_amount, new amount: $amount");
            
            // Update invoice with new pricing
            $inv['amount'] = $amount;
            $inv['subtotal'] = $subtotal;
            $inv['vat'] = $vat;
            $inv['pricing_details'] = $pricing_details;
            if (!empty($notes)) {
                $inv['notes'] = $notes;
            }
            $inv['last_synced'] = current_time('mysql');
            
            $invoices[$index] = $inv;
            update_user_meta($user_id, PII_INVOICES_META, $invoices);
            
            // Clear user cache to ensure fresh data
            if (function_exists('clean_user_cache')) {
                clean_user_cache($user_id);
            }
            
            // Regenerate PDF with new amount using FRESH business info
            $business = pi_get_fresh_business_info($user_id);
            // Use user's current template preference, not stored invoice template
            $tmpl_key = $business['default_template'] ?? 'basic';
            $default_re_line = ($tmpl_key === 'window')
                ? "Proposal for Window Installation at " . ($inv['address'] ?? '') . "."
                : "Overture to contract services in relation to the successfully granted planning application at " . ($inv['address'] ?? '') . ".";
            $pdf_data = [
                'company_name' => $business['company_name'] ?? '',
                'company_address' => $business['company_address'] ?? '',
                'phone' => $business['phone'] ?? '',
                'email' => $business['email'] ?? '',
                'website' => $business['website'] ?? '',
                'date' => $inv['date'] ?? date('d/m/Y', strtotime($inv['created'] ?? current_time('mysql'))),
                'valid_until' => $inv['valid_until'] ?? date('d/m/Y', strtotime(($inv['created'] ?? current_time('mysql')) . ' +30 days')),
                'amount' => number_format($amount, 2),
                'terms' => $inv['terms'] ?? $business['default_terms'] ?? '30% deposit, balance on completion.',
                'warranty' => $inv['warranty'] ?? $business['default_warranty'] ?? '5 years',
                'description' => $inv['description'] ?? 'We are pleased to submit our proposal for works at the above address.',
                'address' => $inv['address'] ?? '',
                're_line' => $inv['re_line'] ?? $default_re_line,
                'logo' => $business['logo'] ?? '',
                'signature' => $business['signature'] ?? '',
                'notes' => $inv['notes'] ?? ''
            ];
            
            $result = [
                'synced' => true, 
                'invoice_id' => $inv['id'], 
                'old_amount' => $old_amount,
                'new_amount' => $amount,
                'subtotal' => $subtotal,
                'vat' => $vat
            ];
            
            // CRITICAL: Regenerate PDF with new amount
            // This is where the actual PDF document gets updated
            $pdf_regenerated = false;
            $new_pdf_url = $inv['pdf_url'] ?? '';
            
            if (!empty($inv['pdf_url'])) {
                $pdf_path = pi_get_pdf_path_from_url($inv['pdf_url']);
                error_log("[PI Invoice Sync] Regenerating PDF at: $pdf_path with amount: " . number_format($amount, 2));
                
                try {
                    // Delete old PDF first to ensure fresh generation
                    if (file_exists($pdf_path)) {
                        @unlink($pdf_path);
                    }
                    
                    // Generate new PDF with updated amount
                    $ok = PI_PDF_Editor::generate_or_update($tmpl_key, $pdf_data, $pdf_path, true);
                    
                    if ($ok && file_exists($pdf_path)) {
                        // Embed images if method exists
                        if (method_exists('PI_PDF_Editor', 'embed_images_into_pdf')) {
                            PI_PDF_Editor::embed_images_into_pdf($pdf_path, $pdf_data['logo'] ?? '', $pdf_data['signature'] ?? '');
                        }
                        
                        // Add cache-busting timestamp to URL
                        $new_pdf_url = preg_replace('/\?.*$/', '', $inv['pdf_url']) . '?t=' . time();
                        $pdf_regenerated = true;
                        
                        // Update the invoice record with new PDF URL timestamp
                        $invoices[$index]['pdf_url'] = $inv['pdf_url']; // Keep base URL
                        update_user_meta($user_id, PII_INVOICES_META, $invoices);
                        
                        error_log("[PI Invoice Sync] PDF regenerated successfully with amount: £" . number_format($amount, 2));
                    } else {
                        error_log("[PI Invoice Sync] PDF regeneration failed for invoice #{$inv['id']} at path $pdf_path");
                    }
                } catch (Exception $e) {
                    error_log("[PI Invoice Sync] Exception regenerating PDF: " . $e->getMessage());
                    $result['pdf_error'] = $e->getMessage();
                }
            } else {
                // No existing PDF - generate a new one
                error_log("[PI Invoice Sync] No existing PDF - generating new one for invoice #{$inv['id']}");
                
                $upload_dir = wp_upload_dir();
                $pdf_dir = $upload_dir['basedir'] . '/planning-proposals/';
                if (!file_exists($pdf_dir)) {
                    wp_mkdir_p($pdf_dir);
                }
                
                $filename = 'proposal-' . $inv['id'] . '-' . $user_id . '-' . uniqid() . '.pdf';
                $pdf_path = $pdf_dir . $filename;
                
                try {
                    $ok = PI_PDF_Editor::generate_or_update($tmpl_key, $pdf_data, $pdf_path, false);
                    
                    if ($ok && file_exists($pdf_path)) {
                        $new_pdf_url = $upload_dir['baseurl'] . '/planning-proposals/' . $filename;
                        $pdf_regenerated = true;
                        
                        // Update invoice with new PDF URL
                        $invoices[$index]['pdf_url'] = $new_pdf_url;
                        update_user_meta($user_id, PII_INVOICES_META, $invoices);
                        
                        error_log("[PI Invoice Sync] New PDF generated at: $new_pdf_url");
                    }
                } catch (Exception $e) {
                    error_log("[PI Invoice Sync] Exception generating new PDF: " . $e->getMessage());
                }
            }
            
            $result['pdf_url'] = $new_pdf_url;
            $result['pdf_regenerated'] = $pdf_regenerated;
            
            // Also update lead meta with synced amount
            if ($lead_id > 0 && get_post_type($lead_id) === PI_LEAD_CPT) {
                update_post_meta($lead_id, PI_LEAD_META_PREFIX . 'estimated_value', $amount);
                error_log("[PI Invoice Sync] Updated lead #$lead_id estimated_value to $amount");
            }
            
            error_log("[PI Invoice Sync] Sync complete - result: " . json_encode($result));
            return rest_ensure_response($result);
        }
    ]);
    // UPDATE INVOICE (amount, status, notes) — NOW UPDATES PDF INSTANTLY
    register_rest_route($namespace, '/workspace/invoices/update', [
        'methods' => 'POST',
        'permission_callback' => fn() => is_user_logged_in(),
        'callback' => function (WP_REST_Request $req) {
            $user_id = get_current_user_id();
            $id = intval($req['id']);
            $field = sanitize_key($req['field']);
            $value = $req['value'];
            $invoices = get_user_meta($user_id, PII_INVOICES_META, true) ?: [];
            $inv = null;
            $index = -1;
            foreach ($invoices as $k => $i) {
                if ($i['id'] === $id) {
                    $inv = $i;
                    $index = $k;
                    break;
                }
            }
            if (!$inv) {
                return new WP_Error('not_found', 'Invoice not found', ['status' => 404]);
            }
            // Preserve original description if this is the first save
            if (!isset($inv['original_description']) && !empty($inv['notes'])) {
                $inv['original_description'] = $inv['notes'];
            }
            // Update in-memory invoice fields
            if ($field === 'amount') {
                $inv['amount'] = floatval($value);
            } elseif ($field === 'status') {
                $inv['status'] = sanitize_text_field($value);
            } elseif ($field === 'notes') {
                $inv['notes'] = sanitize_textarea_field($value);
            } else {
                // allow custom_content or other fields
                $inv[$field] = is_string($value) ? sanitize_text_field($value) : $value;
            }
            // persist updated invoices list
            $invoices[$index] = $inv;
            update_user_meta($user_id, PII_INVOICES_META, $invoices);
            // Reload fresh data
            $invoices = get_user_meta($user_id, PII_INVOICES_META, true) ?: [];
            foreach ($invoices as $i) {
                if ($i['id'] === $id) {
                    $inv = $i;
                    break;
                }
            }
            $result = ['updated' => true];
            // If amount or notes changed, (re)generate the PDF and return new pdf_url
            if (in_array($field, ['amount', 'notes'])) {
                // FORCE FRESH business info - NEVER use stale data
                $business = pi_get_fresh_business_info($user_id);
                
                $tmpl_key = pi_get_current_template_from_settings($user_id, $inv['description'] ?? '');
                // Use CURRENT business settings, not stored invoice values for company info
                $default_re_line = ($tmpl_key === 'window')
                    ? "Proposal for Window Installation at " . ($inv['address'] ?? '') . "."
                    : "Overture to contract services in relation to the successfully granted planning application at " . ($inv['address'] ?? '') . ".";
                $pdf_data = [
                    'company_name' => $business['company_name'] ?? '',
                    'company_address' => $business['company_address'] ?? '',
                    'phone' => $business['phone'] ?? '',
                    'email' => $business['email'] ?? '',
                    'website' => $business['website'] ?? '',
                    'date' => $inv['date'] ?? date('d/m/Y', strtotime($inv['created'] ?? current_time('mysql'))),
                    'valid_until' => $inv['valid_until'] ?? date('d/m/Y', strtotime(($inv['created'] ?? current_time('mysql')) . ' +30 days')),
                    'amount' => number_format($inv['amount'], 2),
                    'terms' => $business['default_terms'] ?? '30% deposit, balance on completion.',
                    'warranty' => $business['default_warranty'] ?? '5 years',
                    'description' => $inv['description'] ?? 'We are pleased to submit our proposal for works at the above address.',
                    'address' => $inv['address'] ?? '',
                    're_line' => $inv['re_line'] ?? $default_re_line,
                    'logo' => $business['logo'] ?? '',
                    'signature' => $business['signature'] ?? '',
                    'notes' => $inv['notes'] ?? ''
                ];
                $upload_dir = wp_upload_dir();
                // If there is no existing pdf_url, create one now
                if (empty($inv['pdf_url'])) {
                    $pdf_dir = $upload_dir['basedir'] . '/planning-proposals/';
                    if (!file_exists($pdf_dir)) {
                        wp_mkdir_p($pdf_dir);
                    }
                    $filename = 'proposal-' . $inv['id'] . '-' . $user_id . '-' . uniqid() . '.pdf';
                    $pdf_path = $pdf_dir . $filename;
                    $pdf_url = $upload_dir['baseurl'] . '/planning-proposals/' . $filename;
                    // Save pdf_url back into the invoice meta
                    foreach ($invoices as &$i) {
                        if ($i['id'] === $inv['id']) {
                            $i['pdf_url'] = $pdf_url;
                            $inv['pdf_url'] = $pdf_url;
                            break;
                        }
                    }
                    update_user_meta($user_id, PII_INVOICES_META, $invoices);
                } else {
                    // existing pdf_url -> compute reliable filesystem path
                    $pdf_path = pi_get_pdf_path_from_url($inv['pdf_url']);
                    $pdf_url = $inv['pdf_url'];
                }
                // Try to (re)generate PDF file
                try {
                    $ok = PI_PDF_Editor::generate_or_update($tmpl_key, $pdf_data, $pdf_path, true);
                    if ($ok && file_exists($pdf_path)) {
                        // Optionally embed images
                        if (method_exists('PI_PDF_Editor', 'embed_images_into_pdf')) {
                            PI_PDF_Editor::embed_images_into_pdf($pdf_path, $pdf_data['logo'] ?? '', $pdf_data['signature'] ?? '');
                        }
                        // Append timestamp to bust caches
                        $result['pdf_url'] = $pdf_url . '?t=' . time();
                    } else {
                        error_log("PI_PDF_Editor: Regeneration failed for invoice {$inv['id']} at path {$pdf_path}");
                        $result['pdf_error'] = true;
                    }
                } catch (Exception $e) {
                    error_log("PI_PDF_Editor: Exception regenerating PDF: " . $e->getMessage());
                    $result['pdf_error'] = true;
                }
            }
            return rest_ensure_response($result);
        }
    ]);
    // Bulk actions
    register_rest_route($namespace, '/workspace/invoices/bulk', [
        'methods' => 'POST',
        'permission_callback' => fn() => is_user_logged_in(),
        'callback' => function (WP_REST_Request $req) {
            $user = get_current_user_id();
            $ids = array_map('intval', (array) $req['ids']);
            $action = sanitize_key($req['action']);
            $value = $req['value'] ?? '';
            $invoices = get_user_meta($user, PII_INVOICES_META, true) ?: [];
            if ($action === 'delete') {
                $invoices = array_values(array_filter($invoices, fn($inv) => !in_array($inv['id'], $ids)));
            } elseif ($action === 'set_status') {
                foreach ($invoices as &$inv) {
                    if (in_array($inv['id'], $ids)) {
                        $old_status = $inv['status'] ?? 'draft';
                        $new_status = sanitize_text_field($value);
                        $inv['status'] = $new_status;
                        
                        // QBO Integration: Trigger proposal status changed hook
                        do_action('pi_proposal_status_changed', $inv['id'], $new_status, $old_status, [
                            'pi_lead_id' => $inv['pi_lead_id'] ?? 0,
                            'lead_id' => $inv['lead_id'] ?? 0,
                            'amount' => $inv['amount'] ?? 0,
                            'user_id' => $user,
                        ]);
                    }
                }
            } elseif ($action === 'print') {
                // Placeholder for bulk print
                return ['bulk' => true, 'message' => 'Bulk print queued (implement server-side if needed)'];
            }
            update_user_meta($user, PII_INVOICES_META, $invoices);
            return ['bulk' => true];
        }
    ]);
    // Delete single invoice
    register_rest_route($namespace, '/workspace/invoices/delete', [
        'methods' => 'POST',
        'permission_callback' => fn() => is_user_logged_in(),
        'callback' => function (WP_REST_Request $req) {
            $user_id = get_current_user_id();
            $inv_id = intval($req['inv_id']);
            $invoices = get_user_meta($user_id, PII_INVOICES_META, true) ?: [];
            
            // Find the invoice to get pi_lead_id before deletion
            $pi_lead_id = 0;
            foreach ($invoices as $inv) {
                if ($inv['id'] === $inv_id) {
                    $pi_lead_id = intval($inv['pi_lead_id'] ?? 0);
                    break;
                }
            }
            
            // Remove the invoice
            $invoices = array_values(array_filter($invoices, fn($x) => $x['id'] !== $inv_id));
            update_user_meta($user_id, PII_INVOICES_META, $invoices);
            
            // QBO Integration: Trigger proposal deleted hook
            do_action('pi_proposal_deleted', $inv_id, [
                'pi_lead_id' => $pi_lead_id,
                'user_id' => $user_id,
            ]);
            
            // CRITICAL: Clear the invoice_generated meta on the associated lead
            // This ensures the "Proposal" badge disappears from the workspace card
            if ($pi_lead_id > 0 && get_post_type($pi_lead_id) === PI_LEAD_CPT) {
                delete_post_meta($pi_lead_id, PI_LEAD_META_PREFIX . 'invoice_generated');
                delete_post_meta($pi_lead_id, PI_LEAD_META_PREFIX . 'invoice_id');
                error_log("[PI Invoice Delete] Cleared invoice_generated meta for lead #$pi_lead_id");
            }
            
            return rest_ensure_response(['deleted' => true, 'lead_cleared' => $pi_lead_id]);
        }
    ]);
    // Update invoice for lead (called from workspace on changes)
    register_rest_route($namespace, '/workspace/invoices/update_for_lead', [
        'methods' => 'POST',
        'permission_callback' => fn() => is_user_logged_in(),
        'callback' => function (WP_REST_Request $req) {
            $user_id = get_current_user_id();
            $lead_id = intval($req['lead_id']);
            $est = floatval($req['est'] ?? 0);
            $due = sanitize_text_field($req['due'] ?? '');
            $notes = sanitize_textarea_field($req['notes'] ?? '');
            $invoices = get_user_meta($user_id, PII_INVOICES_META, true) ?: [];
            $inv = null;
            $index = -1;
            foreach ($invoices as $k => $i) {
                if ($i['lead_id'] === $lead_id) {
                    $inv = $i;
                    $index = $k;
                    break;
                }
            }
            if (!$inv) {
                return rest_ensure_response(['updated' => false]);
            }
            $inv['amount'] = $est;
            $inv['notes'] = $notes;
            if ($due) {
                $inv['valid_until'] = date('d/m/Y', strtotime($due));
            }
            $invoices[$index] = $inv;
            update_user_meta($user_id, PII_INVOICES_META, $invoices);
            
            // Regenerate PDF using FRESH business info
            $business = pi_get_fresh_business_info($user_id);
            // Use user's current template preference
            $tmpl_key = $business['default_template'] ?? 'basic';
            $default_re_line = ($tmpl_key === 'window')
                ? "Proposal for Window Installation at " . ($inv['address'] ?? '') . "."
                : "Overture to contract services in relation to the successfully granted planning application at " . ($inv['address'] ?? '') . ".";
            $pdf_data = [
                'company_name' => $business['company_name'] ?? '',
                'company_address' => $business['company_address'] ?? '',
                'phone' => $business['phone'] ?? '',
                'email' => $business['email'] ?? '',
                'website' => $business['website'] ?? '',
                'date' => $inv['date'] ?? date('d/m/Y', strtotime($inv['created'] ?? current_time('mysql'))),
                'valid_until' => $inv['valid_until'] ?? date('d/m/Y', strtotime(($inv['created'] ?? current_time('mysql')) . ' +30 days')),
                'amount' => number_format($inv['amount'], 2),
                'terms' => $inv['terms'] ?? $business['default_terms'] ?? '30% deposit, balance on completion.',
                'warranty' => $inv['warranty'] ?? $business['default_warranty'] ?? '5 years',
                'description' => $inv['description'] ?? 'We are pleased to submit our proposal for works at the above address.',
                'address' => $inv['address'] ?? '',
                're_line' => $inv['re_line'] ?? $default_re_line,
                'logo' => $business['logo'] ?? '',
                'signature' => $business['signature'] ?? '',
                'notes' => $inv['notes'] ?? ''
            ];
            $pdf_path = pi_get_pdf_path_from_url($inv['pdf_url']);
            try {
                $ok = PI_PDF_Editor::generate_or_update($tmpl_key, $pdf_data, $pdf_path, true);
                if ($ok && file_exists($pdf_path)) {
                    if (method_exists('PI_PDF_Editor', 'embed_images_into_pdf')) {
                        PI_PDF_Editor::embed_images_into_pdf($pdf_path, $pdf_data['logo'] ?? '', $pdf_data['signature'] ?? '');
                    }
                } else {
                    error_log("PI_PDF_Editor: Regeneration failed for invoice {$inv['id']} at path {$pdf_path}");
                }
            } catch (Exception $e) {
                error_log("PI_PDF_Editor: Exception regenerating PDF: " . $e->getMessage());
            }
            return rest_ensure_response(['updated' => true]);
        }
    ]);
    // Upload Custom PDF & Extract Fields
    register_rest_route($namespace, '/workspace/invoices/upload-pdf', [
        'methods' => 'POST',
        'permission_callback' => fn() => is_user_logged_in(),
        'callback' => function (WP_REST_Request $req) {
            $id = intval($req['id']);
            $user_id = get_current_user_id();
            $invoices = get_user_meta($user_id, PII_INVOICES_META, true) ?: [];
            $inv = null;
            foreach ($invoices as &$i) {
                if ($i['id'] === $id) {
                    $inv = &$i;
                    break;
                }
            }
            if (!$inv) {
                return new WP_Error('not_found', 'Invoice not found', ['status' => 404]);
            }
            // Handle upload
            if (empty($_FILES['file'])) {
                return new WP_Error('no_file', 'No file uploaded', ['status' => 400]);
            }
            $upload = wp_handle_upload($_FILES['file'], ['test_form' => false]);
            if ($upload['error']) {
                return new WP_Error('upload_fail', $upload['error'], ['status' => 500]);
            }
            // Update pdf_url
            $old_path = !empty($inv['pdf_url']) ? pi_get_pdf_path_from_url($inv['pdf_url']) : '';
            if ($old_path && file_exists($old_path)) {
                @unlink($old_path);
            }
            $inv['pdf_url'] = $upload['url'];
            $inv['custom'] = true; // Mark as custom
            update_user_meta($user_id, PII_INVOICES_META, $invoices);
            // Extract amount, notes, description, re_line
            $extracted_amount = null;
            $extracted_notes = '';
            $extracted_description= '';
            $extracted_re_line = '';
            try {
                $parser = new \Smalot\PdfParser\Parser();
                $pdf_parsed = $parser->parseFile($upload['file']);
                $text = $pdf_parsed->getText();
                if (preg_match('/Proposed Amount: £([\d\.,]+)/', $text, $m)) {
                    $extracted_amount = floatval(str_replace(',', '', $m[1]));
                }
                if (preg_match('/Notes:(.*?)Terms & Conditions/s', $text, $n)) {
                    $extracted_notes = trim($n[1]);
                }
                if (preg_match('/To Whom It May Concern:(.*?)Proposed Amount/s', $text, $d)) {
                    $extracted_description = trim($d[1]);
                }
                if (preg_match('/Re: (.*?)To Whom It May Concern/s', $text, $r)) {
                    $extracted_re_line = trim($r[1]);
                }
                if ($extracted_amount !== null) {
                    $inv['amount'] = $extracted_amount;
                }
                if ($extracted_notes) {
                    $inv['notes'] = $extracted_notes;
                }
                if ($extracted_description) {
                    $inv['original_description'] = $extracted_description;
                }
                update_user_meta($user_id, PII_INVOICES_META, $invoices);
            } catch (Exception $e) {
                error_log('PDF extraction failed: ' . $e->getMessage());
            }
            return rest_ensure_response([
                'pdf_url' => $inv['pdf_url'] . '?t=' . time(),
                'extracted_amount' => $extracted_amount,
                'extracted_notes' => $extracted_notes
            ]);
        }
    ]);
    // Serve PDF for editor (binary response)
    register_rest_route($namespace, '/workspace/invoices/get-pdf', [
        'methods' => 'GET, HEAD, OPTIONS',
        'permission_callback' => function (WP_REST_Request $req) {
            if ($req->get_method() === 'OPTIONS') {
                return true;
            }
            $nonce = $req->get_param('_wpnonce') ?: $req->get_header('X-WP-Nonce');
            if ($nonce && check_ajax_referer('wp_rest', '_wpnonce', false)) {
                return true;
            }
            if (is_user_logged_in()) {
                return true;
            }
            return current_user_can('read');
        },
        'callback' => function (WP_REST_Request $req) {
            /* ------------------------- OPTIONS (preflight) ------------------------- */
            if ($req->get_method() === 'OPTIONS') {
                $response = new WP_REST_Response(null, 204);
                $response->header('Access-Control-Allow-Methods', 'GET, HEAD, OPTIONS');
                $response->header('Access-Control-Allow-Headers', 'X-WP-Nonce, Content-Type, Range');
                $response->header('Access-Control-Expose-Headers', 'Content-Length, Accept-Ranges, Content-Range');
                $response->header('Access-Control-Allow-Origin', '*');
                $response->header('Access-Control-Allow-Credentials', 'true');
                return $response;
            }
            /* ------------------------- Locate invoice ------------------------- */
            $id = intval($req['id']);
            $user_id = get_current_user_id();
            $invoices = get_user_meta($user_id, PII_INVOICES_META, true) ?: [];
            $inv = array_values(array_filter($invoices, fn($i) => $i['id'] === $id))[0] ?? null;
            if (!$inv || empty($inv['pdf_url'])) {
                return new WP_Error('not_found', 'PDF not found', ['status' => 404]);
            }
            $pdf_path = pi_get_pdf_path_from_url($inv['pdf_url']);
            if (!is_readable($pdf_path)) {
                return new WP_Error('file_missing', 'PDF file missing', ['status' => 404]);
            }
            /* ------------------------- HARD STOP REST OUTPUT ------------------------- */
            while (ob_get_level()) {
                ob_end_clean();
            }
            $file_size = filesize($pdf_path);
            /* ------------------------- Headers (RAW) ------------------------- */
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="proposal-' . $inv['id'] . '.pdf"');
            header('Accept-Ranges: bytes');
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: private');
            /* ------------------------- HEAD request ------------------------- */
            if ($req->get_method() === 'HEAD') {
                header('Content-Length: ' . $file_size);
                exit;
            }
            /* ------------------------- Range support (PDF.js) ------------------------- */
            $range = $_SERVER['HTTP_RANGE'] ?? null;
            if ($range && preg_match('/bytes=(\d+)-(\d*)/', $range, $matches)) {
                $start = (int) $matches[1];
                $end = $matches[2] !== '' ? (int) $matches[2] : $file_size - 1;
                $end = min($end, $file_size - 1);
                $length = ($end - $start) + 1;
                header('HTTP/1.1 206 Partial Content');
                header("Content-Range: bytes $start-$end/$file_size");
                header('Content-Length: ' . $length);
                $fp = fopen($pdf_path, 'rb');
                fseek($fp, $start);
                $buffer = 8192;
                while (!feof($fp) && $length > 0) {
                    $read = min($buffer, $length);
                    echo fread($fp, $read);
                    $length -= $read;
                }
                fclose($fp);
                exit;
            }
            /* ------------------------- Full stream ------------------------- */
            header('Content-Length: ' . $file_size);
            readfile($pdf_path);
            exit;
        }
    ]);
    // Preview PDF (generates temp preview without saving)
    register_rest_route($namespace, '/workspace/invoices/preview-pdf', [
        'methods' => 'POST',
        'permission_callback' => fn() => is_user_logged_in(),
        'callback' => function (WP_REST_Request $req) {
            $id = intval($req['id']);
            $fields = $req['fields'];
            $user_id = get_current_user_id();
            if (!$id || empty($fields)) {
                return new WP_Error('invalid', 'Missing data', ['status' => 400]);
            }
            $invoices = get_user_meta($user_id, PII_INVOICES_META, true) ?: [];
            $inv = null;
            foreach ($invoices as $i) {
                if ($i['id'] === $id) {
                    $inv = $i;
                    break;
                }
            }
            if (!$inv) {
                return new WP_Error('not_found', 'Invoice not found', ['status' => 404]);
            }
            $business = pi_get_fresh_business_info($user_id);
            $tmpl_key = pi_get_current_template_from_settings($user_id, $inv['description'] ?? '');
            // Format amount properly
            $amount = isset($fields['amount']) ? floatval($fields['amount']) : floatval($inv['amount'] ?? 0);
            $display_amount = '' . number_format($amount, 2);
            // Format dates
            $date = !empty($fields['date']) ? date('d/m/Y', strtotime($fields['date'])) : date('d/m/Y');
            $valid_until = !empty($fields['valid_until']) ? date('d/m/Y', strtotime($fields['valid_until'])) : date('d/m/Y', strtotime('+30 days'));
            $pdf_data = [
                'company_name' => $business['company_name'] ?? '',
                'company_address' => $business['company_address'] ?? '',
                'phone' => $business['phone'] ?? '',
                'email' => $business['email'] ?? '',
                'website' => $business['website'] ?? '',
                'date' => $date,
                'valid_until' => $valid_until,
                'amount' => $display_amount,
                'terms' => $fields['terms'] ?? $business['default_terms'] ?? '',
                'warranty' => $fields['warranty'] ?? $business['default_warranty'] ?? '',
                'description' => $fields['description'] ?? $inv['description'] ?? '',
                'address' => $fields['address'] ?? $inv['address'] ?? '',
                're_line' => $fields['re_line'] ?? $inv['re_line'] ?? '',
                'logo' => $business['logo'] ?? '',
                'signature' => $business['signature'] ?? '',
                'notes' => $fields['notes'] ?? $inv['notes'] ?? '',
            ];
            // Generate temporary preview PDF
            $upload_dir = wp_upload_dir();
            $preview_dir = $upload_dir['basedir'] . '/planning-proposals/previews/';
            if (!file_exists($preview_dir)) wp_mkdir_p($preview_dir);
            $preview_filename = 'preview-' . $id . '-' . $user_id . '-' . time() . '.pdf';
            $preview_path = $preview_dir . $preview_filename;
            $preview_url = $upload_dir['baseurl'] . '/planning-proposals/previews/' . $preview_filename;
            if (PI_PDF_Editor::generate_or_update($tmpl_key, $pdf_data, $preview_path)) {
                return rest_ensure_response(['preview_url' => $preview_url]);
            }
            return new WP_Error('preview_fail', 'Failed to generate preview', ['status' => 500]);
        }
    ]);
    // Save edited PDF (from form editor)
    register_rest_route('pi/v1', '/workspace/invoices/save-edited-pdf', [
        'methods' => 'POST',
        'permission_callback' => fn() => is_user_logged_in(),
        'callback' => function (WP_REST_Request $req) {
            $id = intval($req['id']);
            $edits = $req['edits'];
            $user_id = get_current_user_id();
            error_log("PI Save PDF: Invoice ID {$id}, Edits: " . print_r($edits, true));
            if (!$id || empty($edits) || !is_array($edits)) {
                return new WP_Error('invalid', 'Missing or invalid data', ['status' => 400]);
            }
            $invoices = get_user_meta($user_id, PII_INVOICES_META, true) ?: [];
            $invIndex = null;
            foreach ($invoices as $k => $i) {
                if ($i['id'] === $id) {
                    $invIndex = $k;
                    break;
                }
            }
            if ($invIndex === null) {
                return new WP_Error('not_found', 'Invoice not found', ['status' => 404]);
            }
            // All allowed editable fields
            $allowed_fields = [
                'amount', 'date', 'valid_until', 'address', 'description', 'notes',
                're_line', 'terms', 'warranty', 'company_name', 'company_address',
                'phone', 'email', 'website'
            ];
            // Process each edit
            foreach ($edits as $edit) {
                if (empty($edit['field']) || !in_array($edit['field'], $allowed_fields)) {
                    continue;
                }
                $field = $edit['field'];
                $value = $edit['text'] ?? '';
                if ($field === 'amount') {
                    // Clean and convert to float
                    $value = preg_replace('/[^\d.]/', '', $value);
                    $value = floatval($value);
                    error_log("PI Save PDF: Setting amount to {$value}");
                } else {
                    $value = sanitize_textarea_field($value);
                }
                $invoices[$invIndex][$field] = $value;
                error_log("PI Save PDF: Set field '{$field}' to: " . substr($value, 0, 100));
            }
            // Save updated invoice data
            update_user_meta($user_id, PII_INVOICES_META, $invoices);
            // Refetch for PDF generation
            $invoices = get_user_meta($user_id, PII_INVOICES_META, true) ?: [];
            $inv = $invoices[$invIndex];
            // Get business info for company details
            $business = get_user_meta($user_id, '_pi_business_info', true) ?: [];
            // Format amount for display
            $amount_value = floatval($inv['amount'] ?? 0);
            $display_amount = '' . number_format($amount_value, 2);
            // Format dates - convert from YYYY-MM-DD to DD/MM/YYYY if needed
            $date = $inv['date'] ?? '';
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $date = date('d/m/Y', strtotime($date));
            } elseif (empty($date)) {
                $date = date('d/m/Y', strtotime($inv['created'] ?? 'now'));
            }
            $valid_until = $inv['valid_until'] ?? '';
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valid_until)) {
                $valid_until = date('d/m/Y', strtotime($valid_until));
            } elseif (empty($valid_until)) {
                $valid_until = date('d/m/Y', strtotime(($inv['created'] ?? 'now') . ' +30 days'));
            }
            // Compute re_line default if not stored
            $tmpl_key = pi_get_current_template_from_settings($user_id, $inv['description'] ?? '');
            $default_re_line = ($tmpl_key === 'window')
                ? "Proposal for Window Installation at " . ($inv['address'] ?? '') . "."
                : "Overture to contract services in relation to the successfully granted planning application at " . ($inv['address'] ?? '') . ".";
            // Build PDF data - use STORED invoice values, only fall back to business defaults for company info
            $pdf_data = [
                // Company info from business settings
                'company_name' => $inv['company_name'] ?? $business['company_name'] ?? '',
                'company_address' => $inv['company_address'] ?? $business['company_address'] ?? '',
                'phone' => $inv['phone'] ?? $business['phone'] ?? '',
                'email' => $inv['email'] ?? $business['email'] ?? '',
                'website' => $inv['website'] ?? $business['website'] ?? '',
                'logo' => $business['logo'] ?? '',
                'signature' => $business['signature'] ?? '',
               
                // These should come from stored invoice values (set at creation or via edits)
                'date' => $date,
                'valid_until' => $valid_until,
                'amount' => $display_amount,
                'address' => $inv['address'] ?? '',
               
                // CRITICAL: Use stored values, with sensible defaults only if truly missing
                'description' => $inv['description'] ?? 'We are pleased to submit our proposal for works at the above address.',
                'notes' => $inv['notes'] ?? '',
                're_line' => $inv['re_line'] ?? $default_re_line,
                'terms' => $inv['terms'] ?? $business['default_terms'] ?? '30% deposit, balance on completion.',
                'warranty' => $inv['warranty'] ?? $business['default_warranty'] ?? '5 years',
            ];
            error_log("PI Save PDF: Final PDF data: " . print_r($pdf_data, true));
            // Ensure PDF URL exists
            if (empty($inv['pdf_url'])) {
                $upload_dir = wp_upload_dir();
                $pdf_dir = $upload_dir['basedir'] . '/planning-proposals/';
                if (!file_exists($pdf_dir)) wp_mkdir_p($pdf_dir);
                $filename = 'proposal-' . $inv['id'] . '-' . $user_id . '-' . uniqid() . '.pdf';
                $pdf_path = $pdf_dir . $filename;
                $pdf_url = $upload_dir['baseurl'] . '/planning-proposals/' . $filename;
                // Save new PDF URL
                $invoices[$invIndex]['pdf_url'] = $pdf_url;
                update_user_meta($user_id, PII_INVOICES_META, $invoices);
            } else {
                $pdf_path = pi_get_pdf_path_from_url($inv['pdf_url']);
                $pdf_url = $inv['pdf_url'];
            }
            error_log("PI Save PDF: Regenerating PDF at {$pdf_path}");
            // Generate the PDF
            $tmpl_key = pi_get_current_template_from_settings($user_id, $inv['description'] ?? '');
            $result = PI_PDF_Editor::generate_or_update($tmpl_key, $pdf_data, $pdf_path, true);
            if (!$result) {
                error_log("PI Save PDF: Failed to regenerate PDF for Invoice ID {$id}");
                return new WP_Error('pdf_fail', 'Failed to regenerate PDF', ['status' => 500]);
            }
            // Embed images if method exists
            if (method_exists('PI_PDF_Editor', 'embed_images_into_pdf')) {
                PI_PDF_Editor::embed_images_into_pdf($pdf_path, $pdf_data['logo'], $pdf_data['signature']);
            }
            error_log("PI Save PDF: PDF regenerated successfully");
            return rest_ensure_response([
                'saved' => true,
                'pdf_url' => $pdf_url . '?t=' . time()
            ]);
        }
    ]);
    // Optional: manual full regeneration (fallback)
    register_rest_route($namespace, '/workspace/invoices/generate-pdf', [
        'methods' => 'POST',
        'permission_callback' => fn() => is_user_logged_in(),
        'callback' => function (WP_REST_Request $req) {
            $id = intval($req['id']);
            $user_id = get_current_user_id();
            $invoices = get_user_meta($user_id, PII_INVOICES_META, true) ?: [];
            $inv = null;
            foreach ($invoices as $i) {
                if ($i['id'] === $id) {
                    $inv = $i;
                    break;
                }
            }
            if (!$inv || empty($inv['pdf_url'])) {
                return new WP_Error('not_found', 'Invoice or PDF not found', ['status' => 404]);
            }
            $business = pi_get_fresh_business_info($user_id) ?: [
                'company_name' => 'Your Company',
                'default_terms' => '30% deposit, balance on completion.',
                'default_warranty' => '5 years'
            ];
            $tmpl_key = pi_get_current_template_from_settings($user_id, $inv['description'] ?? '');
            $pdf_data = [
                'company_name' => $business['company_name'] ?? '',
                'company_address' => $business['company_address'] ?? '',
                'phone' => $business['phone'] ?? '',
                'email' => $business['email'] ?? '',
                'website' => $business['website'] ?? '',
                'proposal_id' => (string) $inv['id'],
                'date' => date('d/m/Y', strtotime($inv['created'])),
                'valid_until' => date('d/m/Y', strtotime($inv['created'] . ' +30 days')),
                'amount' => number_format($inv['amount'], 2),
                'terms' => $business['default_terms'] ?? '30% deposit, balance on completion.',
                'warranty' => $business['default_warranty'] ?? '5 years',
                'description' => $inv['notes'] ?: 'We are pleased to submit our proposal for works at the above address.',
                'address' => $inv['address'],
                're_line' => ($tmpl_key === 'window')
                    ? "Proposal for Window Installation at {$inv['address']}."
                    : "Overture to contract services in relation to the successfully granted planning application at {$inv['address']}.",
                'logo' => $business['logo'] ?? '',
                'signature' => $business['signature'] ?? '',
                'notes' => $inv['notes']
            ];
            $pdf_path = pi_get_pdf_path_from_url($inv['pdf_url']);
            $fill_data = [
                'company_name' => $pdf_data['company_name'],
                'company_address' => $pdf_data['company_address'],
                'phone' => $pdf_data['phone'],
                'email' => $pdf_data['email'],
                'website' => $pdf_data['website'],
                'date' => $pdf_data['date'],
                'valid_until' => $pdf_data['valid_until'],
                'address' => $pdf_data['address'],
                're_line' => $pdf_data['re_line'],
                'description' => $pdf_data['description'],
                'amount' => $pdf_data['amount'],
                'warranty' => $pdf_data['warranty'],
                'terms' => $pdf_data['terms'],
                'notes' => $pdf_data['notes'] ?? '',
            ];
            if (PI_PDF_Editor::generate_or_update($tmpl_key, $fill_data, $pdf_path)) {
                PI_PDF_Editor::embed_images_into_pdf($pdf_path, $pdf_data['logo'], $pdf_data['signature']);
                return rest_ensure_response(['pdf_url' => $inv['pdf_url'] . '?t=' . time()]);
            } else {
                return new WP_Error('pdf_fail', 'PDF regeneration failed', ['status' => 500]);
            }
        }
    ]);
    
    // ══════════════════════════════════════════════════════════════════════════════
    // REGENERATE ALL INVOICES WITH CURRENT SETTINGS
    // This endpoint completely refreshes all PDFs using the latest _pi_business_info
    // Called after Settings page save to ensure all proposals use new data
    // ══════════════════════════════════════════════════════════════════════════════
    register_rest_route($namespace, '/workspace/invoices/regenerate-all', [
        'methods' => 'POST',
        'permission_callback' => fn() => is_user_logged_in(),
        'callback' => function (WP_REST_Request $req) {
            $user_id = get_current_user_id();
            
            // Force fresh read of business info (bypass any caching)
            clean_user_cache($user_id);
            wp_cache_delete($user_id, 'user_meta');
            
            $business = get_user_meta($user_id, '_pi_business_info', true) ?: [
                'company_name' => 'Your Company',
                'default_terms' => '30% deposit, balance on completion.',
                'default_warranty' => '5 years'
            ];
            
            $new_template = $business['default_template'] ?? 'basic';
            
            error_log("[PI Regenerate All] Starting for user #$user_id with template: $new_template, company: " . ($business['company_name'] ?? 'none'));
            
            $invoices = get_user_meta($user_id, PII_INVOICES_META, true) ?: [];
            $regenerated = 0;
            $failed = 0;
            $errors = [];
            
            foreach ($invoices as $k => &$inv) {
                // Update the invoice record with current settings
                $inv['tmpl_key'] = $new_template;
                $inv['terms'] = $business['default_terms'] ?? '30% deposit, balance on completion.';
                $inv['warranty'] = $business['default_warranty'] ?? '5 years';
                
                // Build PDF data from CURRENT business settings, NOT stored invoice data
                $address = $inv['address'] ?? '';
                $default_re_line = ($new_template === 'window')
                    ? "Proposal for Window Installation at {$address}."
                    : "Overture to contract services in relation to the successfully granted planning application at {$address}.";
                
                $pdf_data = [
                    'company_name' => $business['company_name'] ?? '',
                    'company_address' => $business['company_address'] ?? '',
                    'phone' => $business['phone'] ?? '',
                    'email' => $business['email'] ?? '',
                    'website' => $business['website'] ?? '',
                    'date' => $inv['date'] ?? date('d/m/Y', strtotime($inv['created'] ?? 'now')),
                    'valid_until' => $inv['valid_until'] ?? date('d/m/Y', strtotime(($inv['created'] ?? 'now') . ' +30 days')),
                    'amount' => number_format(floatval($inv['amount'] ?? 0), 2),
                    'terms' => $business['default_terms'] ?? '30% deposit, balance on completion.',
                    'warranty' => $business['default_warranty'] ?? '5 years',
                    'description' => $inv['description'] ?? 'We are pleased to submit our proposal for works at the above address.',
                    'address' => $address,
                    're_line' => $inv['re_line'] ?? $default_re_line,
                    'logo' => $business['logo'] ?? '',
                    'signature' => $business['signature'] ?? '',
                    'notes' => $inv['notes'] ?? ''
                ];
                
                // Regenerate PDF if one exists
                if (!empty($inv['pdf_url'])) {
                    $pdf_path = pi_get_pdf_path_from_url($inv['pdf_url']);
                    
                    try {
                        // Delete old PDF first
                        if (file_exists($pdf_path)) {
                            @unlink($pdf_path);
                        }
                        
                        $ok = PI_PDF_Editor::generate_or_update($new_template, $pdf_data, $pdf_path, false);
                        
                        if ($ok && file_exists($pdf_path)) {
                            // Embed images if method exists
                            if (method_exists('PI_PDF_Editor', 'embed_images_into_pdf')) {
                                PI_PDF_Editor::embed_images_into_pdf($pdf_path, $pdf_data['logo'], $pdf_data['signature']);
                            }
                            $regenerated++;
                            error_log("[PI Regenerate All] Successfully regenerated invoice #{$inv['id']}");
                        } else {
                            $failed++;
                            $errors[] = "Invoice #{$inv['id']}: PDF generation failed";
                            error_log("[PI Regenerate All] Failed to regenerate invoice #{$inv['id']}");
                        }
                    } catch (Exception $e) {
                        $failed++;
                        $errors[] = "Invoice #{$inv['id']}: " . $e->getMessage();
                        error_log("[PI Regenerate All] Exception for invoice #{$inv['id']}: " . $e->getMessage());
                    }
                } else {
                    // No PDF exists yet - create one
                    $upload_dir = wp_upload_dir();
                    $pdf_dir = $upload_dir['basedir'] . '/planning-proposals/';
                    if (!file_exists($pdf_dir)) {
                        wp_mkdir_p($pdf_dir);
                    }
                    
                    $filename = 'proposal-' . $inv['id'] . '-' . $user_id . '-' . uniqid() . '.pdf';
                    $pdf_path = $pdf_dir . $filename;
                    
                    try {
                        $ok = PI_PDF_Editor::generate_or_update($new_template, $pdf_data, $pdf_path, false);
                        
                        if ($ok && file_exists($pdf_path)) {
                            $inv['pdf_url'] = $upload_dir['baseurl'] . '/planning-proposals/' . $filename;
                            $regenerated++;
                        } else {
                            $failed++;
                            $errors[] = "Invoice #{$inv['id']}: New PDF generation failed";
                        }
                    } catch (Exception $e) {
                        $failed++;
                        $errors[] = "Invoice #{$inv['id']}: " . $e->getMessage();
                    }
                }
            }
            unset($inv);
            
            // Save updated invoices
            update_user_meta($user_id, PII_INVOICES_META, $invoices);
            
            // Clear caches again
            clean_user_cache($user_id);
            wp_cache_delete($user_id, 'user_meta');
            
            error_log("[PI Regenerate All] Complete - Regenerated: $regenerated, Failed: $failed");
            
            return rest_ensure_response([
                'success' => true,
                'regenerated' => $regenerated,
                'failed' => $failed,
                'errors' => $errors,
                'template_used' => $new_template,
                'company_name' => $business['company_name'] ?? ''
            ]);
        }
    ]);
    
    // ══════════════════════════════════════════════════════════════════════════════
    // GET CURRENT BUSINESS INFO (for debugging/verification)
    // Returns the authoritative _pi_business_info with cache bypass
    // ══════════════════════════════════════════════════════════════════════════════
    register_rest_route($namespace, '/business-info', [
        'methods' => 'GET',
        'permission_callback' => fn() => is_user_logged_in(),
        'callback' => function () {
            $user_id = get_current_user_id();
            
            // Force fresh read
            clean_user_cache($user_id);
            wp_cache_delete($user_id, 'user_meta');
            
            $business = get_user_meta($user_id, '_pi_business_info', true) ?: [];
            
            // Also check if any checkout meta still exists (for debugging)
            $checkout_meta = [
                'pmpc_default_template' => get_user_meta($user_id, 'pmpc_default_template', true),
                'pmpc_business_info' => get_user_meta($user_id, 'pmpc_business_info', true),
                'pmpe_default_template' => get_user_meta($user_id, 'pmpe_default_template', true),
                'pmrb_default_template' => get_user_meta($user_id, 'pmrb_default_template', true),
            ];
            
            return rest_ensure_response([
                'business_info' => $business,
                'settings_updated_at' => $business['settings_updated_at'] ?? null,
                'current_template' => $business['default_template'] ?? 'basic',
                'checkout_meta_exists' => array_filter($checkout_meta), // Shows any remaining checkout meta
            ]);
        }
    ]);
    
    // One-time migration: ensure all invoices have all required fields stored
    add_action('init', function() {
        if (get_option('pi_invoices_migrated_v2')) return;
       
        $users = get_users(['fields' => 'ID']);
        foreach ($users as $user_id) {
            $invoices = get_user_meta($user_id, PII_INVOICES_META, true);
            if (!is_array($invoices) || empty($invoices)) continue;
           
            $business = get_user_meta($user_id, '_pi_business_info', true) ?: [];
            $updated = false;
           
            foreach ($invoices as &$inv) {
                $tmpl_key = pi_get_current_template_from_settings($user_id, $inv['description'] ?? '');
                $address = $inv['address'] ?? '';
               
                // Add missing fields with correct defaults
                if (!isset($inv['description'])) {
                    $inv['description'] = 'We are pleased to submit our proposal for works at the above address.';
                    $updated = true;
                }
                if (!isset($inv['re_line'])) {
                    $inv['re_line'] = ($tmpl_key === 'window')
                        ? "Proposal for Window Installation at {$address}."
                        : "Overture to contract services in relation to the successfully granted planning application at {$address}.";
                    $updated = true;
                }
                if (!isset($inv['terms'])) {
                    $inv['terms'] = $business['default_terms'] ?? '30% deposit, balance on completion.';
                    $updated = true;
                }
                if (!isset($inv['warranty'])) {
                    $inv['warranty'] = $business['default_warranty'] ?? '5 years';
                    $updated = true;
                }
                if (!isset($inv['date'])) {
                    $inv['date'] = date('d/m/Y', strtotime($inv['created'] ?? 'now'));
                    $updated = true;
                }
                if (!isset($inv['valid_until'])) {
                    $inv['valid_until'] = date('d/m/Y', strtotime(($inv['created'] ?? 'now') . ' +30 days'));
                    $updated = true;
                }
            }
           
            if ($updated) {
                update_user_meta($user_id, PII_INVOICES_META, $invoices);
            }
        }
       
        update_option('pi_invoices_migrated_v2', true);
    });
    // ══════════════════════════════════════════════════════════════════════════════
    // DEBUG: Verify which data source is being used
    // ══════════════════════════════════════════════════════════════════════════════
    register_rest_route($namespace, '/debug/business-info-source', [
        'methods' => 'GET',
        'permission_callback' => fn() => is_user_logged_in(),
        'callback' => function () {
            $user_id = get_current_user_id();
            
            // Raw meta values
            $settings_meta = get_user_meta($user_id, '_pi_business_info', true);
            $pmpc_meta = get_user_meta($user_id, 'pmpc_business_info', true);
            $pmpe_meta = get_user_meta($user_id, 'pmpe_business_info', true);
            $pmrb_meta = get_user_meta($user_id, 'pmrb_business_info', true);
            
            // What the system will actually use
            $fresh = pi_get_fresh_business_info($user_id);
            
            return [
                'source_used' => $fresh,
                'raw_settings_meta' => $settings_meta,
                'checkout_meta_exists' => [
                    'pmpc' => !empty($pmpc_meta),
                    'pmpe' => !empty($pmpe_meta),
                    'pmrb' => !empty($pmrb_meta),
                ],
                'timestamp_check' => [
                    'settings_updated_at' => $settings_meta['settings_updated_at'] ?? 'NEVER',
                    'current_time' => current_time('mysql'),
                ],
                'cache_cleared' => true
            ];
        }
    ]);
});
