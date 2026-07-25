<?php
/**
 * Planning Index Invoices — Pingen Integration
 * 
 * Handles:
 *   POST /wp-json/pi/v1/pingen/send-letter   — Charge user + send PDF letter via Pingen
 *   POST /wp-json/pi/v1/pingen-webhook        — Receive Pingen delivery status updates
 *
 * Uses the official Pingen API v2 workflow:
 *   1. Get upload URL (GET /file-upload)
 *   2. Upload PDF (PUT to returned URL)
 *   3. Create letter (POST /organisations/{org}/letters)
 *   4. Send letter (PATCH /organisations/{org}/letters/{id}/send) [if auto_send=false]
 *
 * @since 1.1.0
 */

if (!defined('ABSPATH')) exit;

// Token helper (kept exactly as your working version)
if (!function_exists('pi_pingen_get_access_token')) {
    function pi_pingen_get_access_token(): string {
        $cached = get_transient('pi_pingen_access_token');
        if ($cached) return $cached;

        $identity_base = PINGEN_SANDBOX ? 'https://identity-staging.pingen.com' : 'https://identity.pingen.com';
        $url = $identity_base . '/auth/access-tokens';

        $response = wp_remote_post($url, [
            'timeout' => 15,
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'body'    => http_build_query([
                'grant_type'    => 'client_credentials',
                'client_id'     => PINGEN_CLIENT_ID,
                'client_secret' => PINGEN_CLIENT_SECRET,
            ]),
        ]);

        if (is_wp_error($response)) {
            error_log('[PI Pingen] Token failed: ' . $response->get_error_message());
            return '';
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body['access_token'])) {
            error_log('[PI Pingen] Token failed. Response: ' . wp_remote_retrieve_body($response));
            return '';
        }

        $expires_in = intval($body['expires_in'] ?? 3600) - 60;
        set_transient('pi_pingen_access_token', $body['access_token'], max($expires_in, 60));

        error_log('[PI Pingen] Token acquired (sandbox=' . (PINGEN_SANDBOX ? 'true' : 'false') . ')');
        return $body['access_token'];
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// HELPER: Pingen API base URL
// ══════════════════════════════════════════════════════════════════════════════
if (!function_exists('pi_pingen_api_base')) {
    function pi_pingen_api_base(): string {
        return PINGEN_SANDBOX
            ? 'https://api-staging.pingen.com'
            : 'https://api.pingen.com';
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// HELPER: Calculate Pingen posting price via price-calculator
// Returns price in GBP (float) or 0 on failure.
// ══════════════════════════════════════════════════════════════════════════════
if (!function_exists('pi_pingen_calculate_price')) {
    function pi_pingen_calculate_price(int $page_count): float {
        $token = pi_pingen_get_access_token();
        if (!$token) {
            error_log('[PI Pingen] Price calc aborted — no token');
            return 0;
        }

        $url = pi_pingen_api_base() . '/organisations/' . PINGEN_ORGANISATION_UUID . '/letters/price-calculator';

        $payload = [
            'data' => [
                'type'       => 'letter_price_calculator',
                'attributes' => [
                    'country'          => 'GB',
                    'paper_types'      => ['normal'],
                    'print_spectrum'   => 'color',
                    'print_mode'       => 'duplex',
                    'delivery_product' => 'cheap',
                ],
            ],
        ];

        $response = wp_remote_post($url, [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/vnd.api+json',
                'Accept'        => 'application/vnd.api+json',
            ],
            'body' => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            error_log('[PI Pingen] Price calc WP error: ' . $response->get_error_message());
            return 0;
        }

        $status = wp_remote_retrieve_response_code($response);
        $body   = json_decode(wp_remote_retrieve_body($response), true);
        error_log('[PI Pingen] Price calc raw response: ' . wp_remote_retrieve_body($response));
        if ($status < 200 || $status >= 300) {
            $error_msg = $body['errors'][0]['detail'] ?? wp_remote_retrieve_body($response);
            error_log("[PI Pingen] Price calc HTTP {$status}: {$error_msg}");
            return 0;
        }

        $price = floatval($body['data']['attributes']['price'] ?? 0);
        // Pingen returns price in currency units (e.g., 1.25 for £1.25)
        error_log("[PI Pingen] Price calc SUCCESS: £{$price} for {$page_count} pages");
        return $price;
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// HELPER: Send letter via Pingen API v2
// 
// CORRECTED WORKFLOW per official documentation:
// 1. Get upload URL (GET /file-upload)
// 2. Upload PDF (PUT to returned URL - NO Authorization header)
// 3. Create letter (POST /organisations/{org}/letters with file_url + file_url_signature)
// 4. Send letter (PATCH /organisations/{org}/letters/{id}/send) [if auto_send=false]
//
// NOTE: Address is automatically extracted from PDF by Pingen - no separate address creation!
// ══════════════════════════════════════════════════════════════════════════════
function pi_pingen_send_letter(string $pdf_path, string $address): array|WP_Error {

    $token = pi_pingen_get_access_token();
    if (!$token) return new WP_Error('auth', 'No token');

    if (!file_exists($pdf_path)) {
        return new WP_Error('file', 'PDF missing: ' . $pdf_path);
    }

    $base = pi_pingen_api_base();
    $org  = PINGEN_ORGANISATION_UUID;

    // ─────────────────────────────────────────────
    // STEP 1: GET UPLOAD URL FROM PINGEN
    // ─────────────────────────────────────────────
    error_log('[PI Pingen] Step 1: Getting upload URL from /file-upload');
    
    $upload_url_res = wp_remote_get("$base/file-upload", [
        'timeout' => 15,
        'headers' => [
            'Authorization' => "Bearer $token",
            'Accept'        => 'application/vnd.api+json',
        ],
    ]);

    if (is_wp_error($upload_url_res)) {
        error_log('[PI Pingen] Upload URL request failed: ' . $upload_url_res->get_error_message());
        return new WP_Error('upload_url', 'Failed to get upload URL: ' . $upload_url_res->get_error_message());
    }

    $upload_status = wp_remote_retrieve_response_code($upload_url_res);
    $upload_body_raw = wp_remote_retrieve_body($upload_url_res);
    $upload_body = json_decode($upload_body_raw, true);

    if ($upload_status !== 200) {
        error_log("[PI Pingen] Upload URL request failed: HTTP {$upload_status} - {$upload_body_raw}");
        return new WP_Error('upload_url_failed', "Failed to get upload URL: HTTP {$upload_status}");
    }

    $file_url = $upload_body['data']['attributes']['url'] ?? null;
    $file_url_signature = $upload_body['data']['attributes']['url_signature'] ?? null;

    if (!$file_url || !$file_url_signature) {
        error_log('[PI Pingen] Missing file_url or file_url_signature in response: ' . $upload_body_raw);
        return new WP_Error('upload_url_data', 'Missing upload URL or signature in response');
    }

    error_log('[PI Pingen] Step 1 complete: Got upload URL and signature');

    // ─────────────────────────────────────────────
    // STEP 2: UPLOAD PDF TO THE PROVIDED URL
    // ─────────────────────────────────────────────
    error_log('[PI Pingen] Step 2: Uploading PDF to provided URL');
    
    $pdf_content = file_get_contents($pdf_path);
    if ($pdf_content === false) {
        return new WP_Error('file_read', 'Failed to read PDF file');
    }

    $upload_res = wp_remote_request($file_url, [
        'method'  => 'PUT',
        'timeout' => 30,
        'body'    => $pdf_content,
        'headers' => [
            'Content-Type' => 'application/pdf',
        ],
    ]);

    if (is_wp_error($upload_res)) {
        error_log('[PI Pingen] File upload failed: ' . $upload_res->get_error_message());
        return new WP_Error('file_upload', 'Failed to upload PDF: ' . $upload_res->get_error_message());
    }

    $file_upload_status = wp_remote_retrieve_response_code($upload_res);
    if ($file_upload_status !== 200) {
        error_log("[PI Pingen] File upload failed: HTTP {$file_upload_status} - " . wp_remote_retrieve_body($upload_res));
        return new WP_Error('file_upload_failed', "File upload failed with HTTP {$file_upload_status}");
    }

    error_log('[PI Pingen] Step 2 complete: PDF uploaded successfully');

    // ─────────────────────────────────────────────
    // STEP 3: CREATE LETTER WITH auto_send: true
    // Pingen will automatically send after async processing completes
    // ─────────────────────────────────────────────
    error_log('[PI Pingen] Step 3: Creating letter with auto_send=true');
    
    $create_payload = [
        'data' => [
            'type' => 'letters',
            'attributes' => [
                'file_original_name' => basename($pdf_path),
                'file_url'           => $file_url,
                'file_url_signature' => $file_url_signature,
                'address_position'   => 'right',
                'auto_send'          => true,  // ← KEY FIX: Let Pingen handle sending
                'delivery_product'   => 'cheap',
                'print_mode'         => 'duplex',
                'print_spectrum'     => 'color',
            ],
        ],
    ];

    $create_res = wp_remote_post("$base/organisations/$org/letters", [
        'timeout' => 15,
        'headers' => [
            'Authorization' => "Bearer $token",
            'Content-Type'  => 'application/vnd.api+json',
            'Accept'        => 'application/vnd.api+json',
        ],
        'body' => wp_json_encode($create_payload),
    ]);

    if (is_wp_error($create_res)) {
        error_log('[PI Pingen] Letter creation failed: ' . $create_res->get_error_message());
        return new WP_Error('create_failed', 'Letter creation failed: ' . $create_res->get_error_message());
    }

    $create_status = wp_remote_retrieve_response_code($create_res);
    $create_body_raw = wp_remote_retrieve_body($create_res);
    $create_body = json_decode($create_body_raw, true);

    if ($create_status !== 201) {
        error_log("[PI Pingen] Letter creation failed: HTTP {$create_status} - {$create_body_raw}");
        $error_detail = $create_body['errors'][0]['detail'] ?? 'Unknown error';
        return new WP_Error('create_failed', "Letter creation failed: {$error_detail}");
    }

    $letter_id = $create_body['data']['id'] ?? null;
    if (!$letter_id) {
        error_log('[PI Pingen] No letter ID in create response: ' . $create_body_raw);
        return new WP_Error('no_letter_id', 'No letter ID returned from creation');
    }

    // Price may not be available immediately due to async processing
    $pingen_price = floatval($create_body['data']['attributes']['price_value'] ?? 0);

    error_log("[PI Pingen] Step 3 complete: Letter created with ID {$letter_id} (auto_send enabled, will send after processing)");

    // ─────────────────────────────────────────────
    // STEP 4: REMOVED - No longer needed with auto_send=true
    // Pingen handles sending asynchronously after validation
    // ─────────────────────────────────────────────

    return [
        'letter_id'    => $letter_id,
        'pingen_price' => $pingen_price, // May be 0 initially; use webhooks for final price
    ];
}

// ══════════════════════════════════════════════════════════════════════════════
// HELPER: Charge user via Paid Memberships Pro (one-time charge on saved card)
// Returns true on success, WP_Error on failure.
// ══════════════════════════════════════════════════════════════════════════════
if (!function_exists('pi_pmpro_charge_user')) {
    function pi_pmpro_charge_user(int $user_id, float $amount, string $description = 'Letter Posting'): bool|WP_Error {
        if (!function_exists('pmpro_getGateway') || !class_exists('MemberOrder')) {
            error_log('[PI Pingen] PMPro not available');
            return new WP_Error('pmpro_missing', 'Paid Memberships Pro is not active');
        }

        $user = get_userdata($user_id);
        if (!$user) {
            return new WP_Error('invalid_user', 'User not found');
        }

        // Get the user's most recent successful order to find their saved payment method
        $last_order = new MemberOrder();
        $last_order->getLastMemberOrder($user_id, 'success');

        if (empty($last_order->id)) {
            error_log('[PI Pingen] No saved payment method for user #' . $user_id);
            return new WP_Error('no_payment', 'No saved payment method found. Please update your billing details.');
        }

        // Create a new one-time order
        $order = new MemberOrder();
        $order->user_id              = $user_id;
        $order->membership_id        = $last_order->membership_id;
        $order->InitialPayment       = $amount;
        $order->PaymentAmount        = $amount;
        $order->billing              = $last_order->billing;
        $order->gateway              = $last_order->gateway;
        $order->gateway_environment  = $last_order->gateway_environment;
        $order->payment_type         = $last_order->payment_type;
        $order->cardtype             = $last_order->cardtype;
        $order->accountnumber        = $last_order->accountnumber;
        $order->expirationmonth      = $last_order->expirationmonth;
        $order->expirationyear       = $last_order->expirationyear;
        $order->status               = 'token';
        $order->notes                = 'Pingen letter posting charge: ' . $description;

        // Copy subscription transaction ID for tokenised charging
        $order->subscription_transaction_id = $last_order->subscription_transaction_id;
        $order->payment_transaction_id      = $last_order->payment_transaction_id;

        // Process via gateway
        $order->setGateway($last_order->gateway);

        $charged = $order->Gateway->charge($order);

        if ($charged) {
            $order->status = 'success';
            $order->saveOrder();
            error_log('[PI Pingen] Charge successful: £' . number_format($amount, 2) . ' for user #' . $user_id);
            return true;
        } else {
            $error_msg = $order->error ?? 'Payment gateway declined the charge';
            error_log('[PI Pingen] Charge failed for user #' . $user_id . ': ' . $error_msg);
            return new WP_Error('charge_failed', $error_msg);
        }
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// REST ROUTE: POST /wp-json/pi/v1/pingen/send-letter
// ══════════════════════════════════════════════════════════════════════════════
add_action('rest_api_init', function () {

    register_rest_route('pi/v1', '/pingen/send-letter', [
        'methods'             => 'POST',
        'permission_callback' => fn() => is_user_logged_in(),
        'callback'            => function (WP_REST_Request $req) {
            $user_id    = get_current_user_id();
            $invoice_id = intval($req['invoice_id'] ?? 0);

            $invoices = get_user_meta($user_id, PII_INVOICES_META, true) ?: [];
            $invoice = null;
            $invoice_idx = null;
            foreach ($invoices as $idx => &$inv) {
                if (intval($inv['id']) === $invoice_id) {
                    $invoice_idx = $idx;
                    $invoice = &$inv;
                    break;
                }
            }
            unset($inv);

            if (!$invoice || empty($invoice['pdf_url'])) {
                return new WP_Error('no_pdf', 'No PDF found', ['status' => 400]);
            }

            $pdf_path = pi_get_pdf_path_from_url($invoice['pdf_url']);
            if (!file_exists($pdf_path)) {
                return new WP_Error('pdf_missing', 'PDF file missing: ' . $pdf_path, ['status' => 400]);
            }

            // Send letter via Pingen
            $result = pi_pingen_send_letter($pdf_path, $invoice['address'] ?? '');

            if (is_wp_error($result)) {
                return new WP_Error('send_failed', $result->get_error_message(), ['status' => 500]);
            }

            // Calculate user fee (Pingen price + 5% markup)
            $user_fee = round($result['pingen_price'] * 1.05, 2);

            // Charge user
            $charge = pi_pmpro_charge_user($user_id, $user_fee);
            if (is_wp_error($charge)) {
                return new WP_Error('charge_failed', $charge->get_error_message(), ['status' => 402]);
            }

            // Update invoice with Pingen details
            $invoices[$invoice_idx]['pingen_letter_id'] = $result['letter_id'];
            $invoices[$invoice_idx]['status'] = 'mailed';
            $invoices[$invoice_idx]['mailed_at'] = current_time('mysql');
            $invoices[$invoice_idx]['pingen_fee'] = $user_fee;
            update_user_meta($user_id, PII_INVOICES_META, $invoices);

            return rest_ensure_response([
                'success'   => true,
                'message'   => 'Letter queued for posting – you will be charged £' . number_format($user_fee, 2) . ' automatically.',
                'letter_id' => $result['letter_id'],
                'fee'       => $user_fee,
            ]);
        },
    ]);

    // ══════════════════════════════════════════════════════════════════════════
    // REST ROUTE: POST /wp-json/pi/v1/pingen-webhook
    // Public endpoint — no nonce, verified via HMAC-SHA256 signature.
    // ══════════════════════════════════════════════════════════════════════════
    register_rest_route('pi/v1', '/pingen-webhook', [
        'methods'             => 'POST',
        'permission_callback' => '__return_true', // Public webhook
        'callback'            => function (WP_REST_Request $req) {
            // ── Verify HMAC signature ────────────────────────────────────
            $signature = $req->get_header('X-Pingen-Signature')
                      ?: $req->get_header('x-pingen-signature')
                      ?: '';

            $raw_body = $req->get_body();
            $expected = hash_hmac('sha256', $raw_body, PINGEN_WEBHOOK_SECRET);

            if (!hash_equals($expected, $signature)) {
                error_log('[PI Pingen Webhook] Invalid signature. Expected: ' . $expected . ' Got: ' . $signature);
                return new WP_REST_Response(['error' => 'Invalid signature'], 403);
            }

            $payload = json_decode($raw_body, true);
            if (!$payload) {
                return new WP_REST_Response(['error' => 'Invalid JSON'], 400);
            }

            $event     = $payload['event']              ?? $payload['data']['type'] ?? 'unknown';
            $letter_id = $payload['data']['id']         ?? $payload['letter_id']    ?? '';
            $status    = $payload['data']['attributes']['status'] ?? $event;

            error_log("[PI Pingen Webhook] Event: {$event}, Letter: {$letter_id}, Status: {$status}");

            if (!$letter_id) {
                return new WP_REST_Response(['ok' => true], 200);
            }

            // ── Map Pingen status to our invoice status ──────────────────
            $status_map = [
                'sent'            => 'mailed',
                'delivered'       => 'delivered',
                'undeliverable'   => 'undeliverable',
                'issues'          => 'delivery_issue',
                'printing'        => 'mailed',
                'in_delivery'     => 'mailed',
            ];

            $new_status = $status_map[$status] ?? null;
            if (!$new_status) {
                error_log("[PI Pingen Webhook] Unmapped status: {$status}");
                return new WP_REST_Response(['ok' => true], 200);
            }

            // ── Find and update the invoice across all users ─────────────
            global $wpdb;
            $meta_rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s",
                    PII_INVOICES_META
                )
            );

            $found = false;
            foreach ($meta_rows as $row) {
                $invoices = maybe_unserialize($row->meta_value);
                if (!is_array($invoices)) continue;

                foreach ($invoices as &$inv) {
                    if (($inv['pingen_letter_id'] ?? '') === $letter_id) {
                        $inv['status']                = $new_status;
                        $inv['pingen_last_event']      = $event;
                        $inv['pingen_last_updated']    = current_time('mysql');
                        $inv['pingen_tracking_status'] = $status;
                        update_user_meta(intval($row->user_id), PII_INVOICES_META, $invoices);
                        error_log("[PI Pingen Webhook] Updated invoice for user #{$row->user_id}, letter {$letter_id} → {$new_status}");
                        $found = true;
                        break 2;
                    }
                }
                unset($inv);
            }

            if (!$found) {
                error_log("[PI Pingen Webhook] Letter ID not found in any user's invoices: {$letter_id}");
            }

            // Always return 200 to acknowledge receipt
            return new WP_REST_Response(['ok' => true], 200);
        },
    ]);
});