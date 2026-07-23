<?php
/**
 * Plugin Name: Planning Index Invoices
 * Description: Premium bespoke letter manager for construction propositions—track, generate, and analyze proposals with stats, bulk actions, and Apple-inspired design.
 * Version: 1.0.0
 * Author: Planning Index
 */

if (!defined('ABSPATH')) exit;

// ══════════════════════════════════════════════════════════════════════════════
// PINGEN INTEGRATION CONSTANTS
// ══════════════════════════════════════════════════════════════════════════════
if (!defined('PINGEN_CLIENT_ID'))           define('PINGEN_CLIENT_ID',           '6S2WDLTOHNMWMICJR7L4');
if (!defined('PINGEN_CLIENT_SECRET'))       define('PINGEN_CLIENT_SECRET',       'ERredhiifD8/UwTLrhVmdgDlvozTDUyn8+HC59jpdTA3j3HF8yVsmbEanegUfDJaUuKNzcqvvmskIgCD');
if (!defined('PINGEN_ORGANISATION_UUID'))   define('PINGEN_ORGANISATION_UUID',   '6c2fa8bf-a16f-4817-8ecd-32828007ef6f');
if (!defined('PINGEN_SANDBOX'))             define('PINGEN_SANDBOX',             true);
if (!defined('PINGEN_WEBHOOK_SECRET'))      define('PINGEN_WEBHOOK_SECRET',      'KusNoelNoelKusKusNoel123@@');

// Ensure uploads directory allows PDF headers
// Add proper CORS headers for all PDF files in uploads
add_action('send_headers', function() {
    if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '.pdf') !== false) {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
        header('Access-Control-Expose-Headers: Content-Length, Content-Range, Accept-Ranges');
        header('Accept-Ranges: bytes');
    }
});
// Load FPDF and FPDI (keep for compatibility, but we'll use TCPDF bridge)
// Load mPDF (assuming composer setup from GitHub download; adjust path if no vendor)
require_once __DIR__ . '/vendor/autoload.php';

// Load Pingen integration (send-letter + webhook endpoints)
require_once __DIR__ . '/includes/pingen.php';

add_filter('mod_rewrite_rules', function($rules) {
    return preg_replace('/Require valid-user/', '', $rules); // Remove auth if mod_security adds
});
// Allow email for Basic Auth if needed
add_filter('authenticate', function($user, $username, $password) {
    if ($user instanceof WP_User) return $user;
    if (is_email($username)) {
        $user = get_user_by('email', $username);
        if ($user && wp_check_password($password, $user->user_pass, $user->ID)) {
            return $user;
        }
    }
    return null;
}, 20, 3);
// Define where blank templates are stored
define('PI_PDF_BLANK_TEMPLATES_DIR', wp_upload_dir()['basedir'] . '/planning-proposals-blank/');
if (!file_exists(PI_PDF_BLANK_TEMPLATES_DIR)) wp_mkdir_p(PI_PDF_BLANK_TEMPLATES_DIR);
register_activation_hook(__FILE__, function() {
    $upload_dir = wp_upload_dir();
    $tmpl_dir = $upload_dir['basedir'] . '/invoice-templates/';
    $prop_dir = $upload_dir['basedir'] . '/planning-proposals/';
    if (!file_exists($tmpl_dir)) wp_mkdir_p($tmpl_dir);
    if (!file_exists($prop_dir)) wp_mkdir_p($prop_dir);
});

define('PII_INVOICES_META', '_pi_invoices');
define('PI_PDF_TEMPLATES', [

    /* ═══════════════════════════════════════════════════════════════════════════
       BASIC PROPOSAL — Clean, Professional, Timeless
       ═══════════════════════════════════════════════════════════════════════════ */
    'basic' => [
        'name' => 'Basic Proposal',
        'html' => '
            <div style="font-family: Arial, sans-serif; font-size: 11px; line-height: 1.6; color: #000;">
                
                <!-- Header Table: Company left, Address right in red box area -->
                <table style="width: 100%; border-collapse: collapse; margin-bottom: 25px;">
                    <tr>
                        <td style="width: 55%; vertical-align: top;">
                            <div>[logo]</div>
                            <strong>[company_name]</strong><br>
                            [company_address]<br>
                            Phone: [phone] | Email: [email]<br>
                            Website: [website]
                            <div style="border-top: 1px solid #858585; margin: 8px 0; width: 100%;"></div>
                            <p style="margin: 0;">
                                <strong>Date:</strong> [date]<br>
                                <strong>Valid Until:</strong> [valid_until]
                            </p>
                        </td>
                        <td style="width: 45%; vertical-align: bottom; text-align: right; padding-top: 120px;">
                            [address]
                        </td>
                    </tr>
                </table>

                <h3 style="margin-top: 25px;">Re: [re_line]</h3>

                <p>Dear Sir/Madam,</p>

                <p style="margin-bottom: 20px;">
                    Thank you for the opportunity to provide a proposal for the works at your property.
                    Please find our quotation below.
                </p>

                <h3>1. Description of Proposed Works</h3>
                <p style="margin-bottom: 20px;">[description]<br>[notes]</p>

                <h3>2. Proposed Investment</h3>
                <p style="margin-bottom: 20px;"><strong>Total Amount: </strong>£[amount]</p>

                <h3>3. Warranty & Quality Assurance</h3>
                <p style="margin-bottom: 15px;">[warranty]</p>

                <h3>4. Terms & Conditions</h3>
                <p style="margin-bottom: 20px;">[terms]</p>

                <h3>Acceptance of Proposal</h3>

                <p style="margin-bottom: 15px;">
                    If you wish to proceed with these works, please sign below and return this form.
                </p>

                <p style="margin-bottom: 20px;">
                    Customer Signature: _______________________________<br>
                    Date: _______________________
                </p>

                <h3>Company Authorised Signature</h3>

                <p style="margin-bottom: 20px;">[signature]</p>

                <p>Kind regards,<br>[company_name]</p>

            </div>
        ',
    ],

    /* ═══════════════════════════════════════════════════════════════════════════
       WESTMINSTER — British Government/Corporate Standard
       Inspired by formal UK construction correspondence
       ═══════════════════════════════════════════════════════════════════════════ */

    'westminster' => [
        'name' => 'Westminster Formal',
        'html' => '
            <div style="font-family: \'Times New Roman\', Times, serif; font-size: 11pt; line-height: 1.5; color: #1a1a1a; padding: 0; max-width: 100%;">

                <!-- Letterhead -->
                <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 8px;">
                    <tr>
                        <td style="width: 35%; vertical-align: top;">
                            <div style="height: 70px;">[logo]</div>
                        </td>
                        <td style="width: 65%; text-align: right; vertical-align: top;">
                            <div style="font-size: 14pt; font-weight: bold; color: #1a1a1a; margin-bottom: 4px;">[company_name]</div>
                            <div style="font-size: 9pt; line-height: 1.4; color: #4a4a4a;">
                                [company_address]<br>
                                [email] | [website]
                            </div>
                        </td>
                    </tr>
                </table>

                <div style="border-top: 1px solid #000; margin-bottom: 4px; margin-top: 0px; width: 50%;"></div>

                <!-- Reference & Recipient Block -->
                <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 65px; margin-top: 2px; font-size: 10pt;">
                    <tr>
                        <td style="width: 50%; vertical-align: middle;">
                            <br><br>
                            <div style="margin-bottom: 2px;"><strong>Our Ref:</strong> PROP/[date]</div>
                            <div style="margin-bottom: 2px;"><strong>Date:</strong> [date]</div>
                            <div><strong>Valid Until:</strong> [valid_until]</div>
                        </td>
                        <td style="width: 50%; vertical-align: bottom; text-align: right; padding-left: 30px;">
                            <div style="font-size: 10pt; line-height: 1.3; padding-top: 40px; margin-top: 30px;">
                                <span style="display: inline-block; margin-top: 70px; line-height: 1.6;">
                                    
                                </span>
                                <br><br><br>
                                [address]
                            </div>
                        </td>
                    </tr>
                </table>

                <!-- Subject Line -->
                <div style="background: #f5f5f5; padding: 10px 14px; border-left: 3px solid #1a1a1a; margin-bottom: 20px;">
                    <strong>RE: [re_line]</strong>
                </div>

                <!-- Salutation -->
                <p style="margin: 0 0 14px 0;">Dear Sir or Madam,</p>

                <!-- The rest remains unchanged -->
                <!-- Opening -->
                <p style="margin: 0 0 14px 0; text-align: justify;">
                    Following your recent planning approval, we write to present our formal proposal for the construction works at the above-referenced property. We trust this quotation meets your requirements and demonstrates our commitment to delivering projects of the highest standard.
                </p>
                <!-- Scope Section -->
                <div style="margin: 20px 0 16px 0;">
                    <div style="font-weight: bold; border-bottom: 1px solid #1a1a1a; padding-bottom: 3px; margin-bottom: 10px;">
                        SCOPE OF WORKS
                    </div>
                    <div style="text-align: justify;">[description]</div>
                </div>
                <!-- Contract Sum -->
                <div style="background: #1a1a1a; color: #ffffff; padding: 18px 20px; margin: 20px 0; text-align: center;">
                    <div style="font-size: 9pt; letter-spacing: 0.5px; margin-bottom: 6px;">CONTRACT SUM (EXCLUSIVE OF VAT)</div>
                    <div style="font-size: 24pt; font-weight: bold;">£[amount]</div>
                </div>
                <!-- Additional Notes -->
                <div style="margin: 16px 0; padding: 12px; background: #fafafa; border: 1px solid #d0d0d0; font-size: 10pt;">
                    <strong>Additional Notes:</strong><br>
                    [notes]
                </div>
                <!-- Warranty -->
                <div style="margin: 16px 0; font-size: 10pt;">
                    <div style="font-weight: bold; margin-bottom: 6px;">WARRANTY PROVISION</div>
                    <div>[warranty]</div>
                </div>
                <!-- Terms -->
                <div style="margin: 16px 0; font-size: 10pt;">
                    <div style="font-weight: bold; margin-bottom: 6px;">TERMS & CONDITIONS</div>
                    <div style="font-size: 9.5pt; line-height: 1.4; padding: 10px; background: #fafafa; border: 1px solid #d0d0d0;">
                        [terms]
                    </div>
                </div>
                <!-- Closing -->
                <p style="margin: 20px 0 14px 0; text-align: justify;">
                    We should be pleased to discuss any aspect of this proposal at your convenience. Should you wish to proceed, kindly sign and return the enclosed acceptance form.
                </p>

                <p style="margin: 0 0 6px 0;">Yours faithfully,</p>

                <!-- Signature Block -->
                <div style="margin: 24px 0 16px 0;">
                    [signature]
                    <div style="margin-top: 6px; font-weight: bold;">[company_name]</div>
                    <div style="font-size: 9pt; color: #666;">Authorised Representative</div>
                </div>

                <!-- Acceptance Section -->
                <div style="border-top: 1px solid #1a1a1a; margin-top: 32px; padding-top: 16px;">
                    <div style="font-weight: bold; margin-bottom: 12px;">ACCEPTANCE OF PROPOSAL</div>
                    <p style="font-size: 10pt; margin: 0 0 16px 0;">I/We hereby accept this proposal and authorise [company_name] to proceed with the works as described above.</p>
                    <table width="100%" cellpadding="0" cellspacing="0" style="font-size: 10pt;">
                        <tr>
                            <td style="width: 50%; padding-right: 20px;">
                                <div style="border-bottom: 1px solid #1a1a1a; height: 36px;"></div>
                                <div style="margin-top: 4px;">Signature</div>
                            </td>
                            <td style="width: 50%;">
                                <div style="border-bottom: 1px solid #1a1a1a; height: 36px;"></div>
                                <div style="margin-top: 4px;">Date</div>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Footer -->
                <div style="margin-top: 32px; padding-top: 10px; border-top: 1px solid #cccccc; font-size: 8pt; color: #666; text-align: center;">
                    [company_name] | [phone] | [email] | [website]
                </div>
            </div>
        ',
    ],

    /* ═══════════════════════════════════════════════════════════════════════════
       BRUNEL — Industrial Heritage / Major Contractor Style
       Named after Isambard Kingdom Brunel - bold, engineering-focused
       ═══════════════════════════════════════════════════════════════════════════ */
    'brunel' => [
        'name' => 'Brunel Industrial',
        'html' => '
            <div style="font-family: Helvetica Neue, Helvetica, Arial, sans-serif; font-size: 10pt; line-height: 1.5; color: #222; padding: 0;">

                <!-- Header Bar - Navy stripe with company info on left and address on right -->
                <table width="100%" cellpadding="0" cellspacing="0" style="margin: -10px -10px 0 -10px;">
                    <tr>
                        <td style="width: 50%; background: #2c3e50; padding: 24px 28px; vertical-align: middle;">
                            <div style="background: #fff; padding: 10px; display: inline-block; vertical-align: middle;">[logo]</div>
                            <div style="display: inline-block; color: #fff; margin-left: 20px; vertical-align: middle;">
                                <div style="font-size: 20pt; font-weight: 700; letter-spacing: 1px;">[company_name]</div>
                                <div style="font-size: 9pt; margin-top: 6px; opacity: 0.9;">
                                    [company_address] | [phone]
                                </div>
                            </div>
                        </td>
                        <!-- Right half now contains the recipient address, aligned with company name -->
                        <td style="width: 50%; vertical-align: middle; padding: 24px 28px; text-align: right; padding-top: 150px;">
                            <div style="font-size: 10pt; line-height: 1.5; color: #222;">
                                [address]
                            </div>
                        </td>
                    </tr>
                </table>

                <!-- Document Title - Orange stripe ONLY on left half -->
                <table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 -10px 0 -10px;">
                    <tr>
                        <td style="width: 50%; background: #e74c3c; padding: 14px 28px; vertical-align: middle;">
                            <div style="font-size: 14pt; font-weight: bold; letter-spacing: 2px; text-transform: uppercase; color: #fff;">
                                Construction Proposal
                            </div>
                        </td>
                        <!-- Right half empty -->
                        <td style="width: 50%;"></td>
                    </tr>
                </table>

                <!-- Reference & Date Block - Address removed from here -->
                <table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 -10px 20px -10px; font-size: 10pt;">
                    <tr>
                        <td style="width: 50%; vertical-align: top; padding: 15px 28px;">
                            <div style="margin-bottom: 2px;">
                                <span style="font-size: 8pt; color: #666; text-transform: uppercase; letter-spacing: 1px;">Date Issued</span><br>
                                <strong>[date]</strong>
                            </div>
                            <div style="margin-bottom: 2px; margin-top: 8px;">
                                <span style="font-size: 8pt; color: #666; text-transform: uppercase; letter-spacing: 1px;">Valid Until</span><br>
                                <strong>[valid_until]</strong>
                            </div>
                            <div style="margin-top: 8px;">
                                <span style="font-size: 8pt; color: #666; text-transform: uppercase; letter-spacing: 1px;">Reference</span><br>
                                <strong>PROP-[date]</strong>
                            </div>
                        </td>
                        <td style="width: 50%;"></td>
                    </tr>
                </table>

                <!-- Project Reference -->
                <div style="margin-bottom: 20px; padding: 14px 20px; background: #2c3e50; color: #fff;">
                    <div style="font-size: 9pt; text-transform: uppercase; letter-spacing: 1px; opacity: 0.8;">Project Reference</div>
                    <div style="font-size: 11pt; margin-top: 4px;">[re_line]</div>
                </div>

                <!-- Scope of Works -->
                <div style="margin-bottom: 24px;">
                    <div style="font-size: 11pt; font-weight: bold; color: #2c3e50; border-bottom: 2px solid #e74c3c; padding-bottom: 6px; margin-bottom: 12px; text-transform: uppercase; letter-spacing: 1px;">
                        Scope of Works
                    </div>
                    <p style="text-align: justify; line-height: 1.65;">[description]</p>
                </div>

                <!-- Price Box -->
                <div style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); color: #fff; padding: 28px; margin: 24px 0; text-align: center; position: relative;">
                    <div style="position: absolute; top: 0; left: 0; right: 0; height: 4px; background: #e74c3c;"></div>
                    <div style="font-size: 10pt; letter-spacing: 2px; text-transform: uppercase; opacity: 0.9;">Total Contract Value</div>
                    <div style="font-size: 32pt; font-weight: 700; margin: 12px 0 4px; letter-spacing: 1px;">£[amount]</div>
                    <div style="font-size: 9pt; opacity: 0.8;">Exclusive of VAT</div>
                </div>

                <!-- Notes -->
                <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 14px 18px; margin: 20px 0;">
                    <div style="font-size: 9pt; font-weight: bold; text-transform: uppercase; color: #856404; margin-bottom: 6px;">Important Notes</div>
                    <div style="font-size: 10pt; color: #856404;">[notes]</div>
                </div>

                <!-- Warranty & Terms Grid -->
                <table width="100%" cellpadding="0" cellspacing="0" style="margin: 24px 0;">
                    <tr>
                        <td style="width: 48%; vertical-align: top; padding-right: 12px;">
                            <div style="font-size: 10pt; font-weight: bold; color: #2c3e50; border-bottom: 1px solid #ddd; padding-bottom: 6px; margin-bottom: 10px;">
                                WARRANTY
                            </div>
                            <div style="font-size: 9.5pt; line-height: 1.55;">[warranty]</div>
                        </td>
                        <td style="width: 4%;"></td>
                        <td style="width: 48%; vertical-align: top; padding-left: 12px;">
                            <div style="font-size: 10pt; font-weight: bold; color: #2c3e50; border-bottom: 1px solid #ddd; padding-bottom: 6px; margin-bottom: 10px;">
                                PAYMENT TERMS
                            </div>
                            <div style="font-size: 9.5pt; line-height: 1.55;">[terms]</div>
                        </td>
                    </tr>
                </table>

                <!-- Signature Section -->
                <div style="margin-top: 32px; border-top: 2px solid #2c3e50; padding-top: 20px;">
                    <table width="100%" cellpadding="0" cellspacing="0">
                        <tr>
                            <td style="width: 48%; vertical-align: bottom;">
                                <div style="margin-bottom: 20px;">
                                    [signature]
                                </div>
                                <div style="border-top: 1px solid #222; padding-top: 6px; font-size: 9pt;">
                                    <strong>[company_name]</strong><br>
                                    Authorised Signatory
                                </div>
                            </td>
                            <td style="width: 4%;"></td>
                            <td style="width: 48%; vertical-align: bottom;">
                                <div style="height: 60px; border-bottom: 1px solid #222;"></div>
                                <div style="padding-top: 6px; font-size: 9pt;">
                                    Client Signature & Date
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Footer -->
                <div style="margin-top: 30px; padding: 16px 0; border-top: 3px solid #e74c3c; text-align: center; font-size: 8.5pt; color: #666;">
                    <strong>[company_name]</strong> | [phone] | [email] | [website]
                </div>
            </div>
        ',
    ],

    /* ═══════════════════════════════════════════════════════════════════════════
       MAYFAIR — Luxury / High-End Residential
       For premium residential projects - understated elegance
       ═══════════════════════════════════════════════════════════════════════════ */
    'mayfair' => [
        'name' => 'Mayfair Premium',
        'html' => '
            <div style="font-family: \'Garamond\', \'Georgia\', serif; font-size: 11pt; line-height: 1.7; color: #2d2d2d; padding: 0;">
                
                <!-- Header: Split layout for window envelope -->
                <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 16px;">
                    <tr>
                        <!-- Left: Company branding (50% width) -->
                        <td width="50%" valign="top" style="padding: 24px 24px 24px 0; border-right: 1px solid #e5e5e5;">
                            <div style="margin-bottom: 12px;">[logo]</div>
                            <br>
                            <div style="font-size: 20pt; font-weight: normal; letter-spacing: 3px; color: #1a1a1a; text-transform: uppercase; margin-bottom: 8px; margin-top: 40px">

                                [company_name]
                            </div>
                            <div style="width: 40px; height: 1px; background: #b8860b; margin: 12px 0;"></div>
                            <div style="font-size: 9pt; letter-spacing: 1px; color: #666; line-height: 1.5;">
                                [company_address]
                            </div>
                            <div style="font-size: 9pt; color: #666; margin-top: 4px;">
                                [phone] | [email]
                            </div>
                        </td>
                        
                        <!-- Right: Address positioned for window (50% width) -->
                        <td width="50%" valign="top" style="padding: 150px 0 0 30px; text-align: right;">
                            <div style="font-size: 11pt; line-height: 1.6; color: #2d2d2d;">
                                [address]
                            </div>
                        </td>
                    </tr>
                </table>
                
                <!-- Gold divider -->
                <div style="height: 1px; background: linear-gradient(to right, transparent, #b8860b, transparent); margin: 12px 0 24px 0;"></div>
                
                <!-- Reference & Date Block (below the window area) -->
                <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 40px; font-size: 10pt; padding: 0 24px;">
                    <tr>
                        <td style="width: 50%; vertical-align: top;">
                            <div style="font-size: 10pt; letter-spacing: 3px; color: #b8860b; text-transform: uppercase; margin-bottom: 12px;">Proposal for Works</div>
                            <div style="margin-bottom: 4px;"><strong>Date:</strong> [date]</div>
                            <div><strong>Valid Until:</strong> [valid_until]</div>
                        </td>
                        <td style="width: 50%;"></td>
                    </tr>
                </table>
                
                <!-- Reference Line -->
                <div style="text-align: center; margin: 24px 0; font-style: italic; font-size: 11pt; color: #555;">
                    Regarding: [re_line]
                </div>
                
                <!-- Letter Body -->
                <div style="margin: 30px 24px;">
                    <p style="margin-bottom: 18px;">Dear Property Owner,</p>
                    
                    <p style="text-align: justify; margin-bottom: 18px;">
                        It is our pleasure to present this proposal for the works at your distinguished property. We are committed to delivering craftsmanship of the highest calibre, ensuring every detail meets your exacting standards.
                    </p>
                    
                    <div style="margin: 28px 0; padding: 20px 24px; border-left: 2px solid #b8860b; background: #fffef5;">
                        <div style="font-size: 10pt; font-weight: bold; color: #1a1a1a; margin-bottom: 10px;">Description of Works</div>
                        <p style="margin: 0; font-size: 10.5pt; line-height: 1.65;">[description]</p>
                    </div>
                </div>
                
                <!-- Price Presentation -->
                <div style="text-align: center; margin: 36px 24px; padding: 32px; background: #1a1a1a; color: #fff;">
                    <div style="font-size: 9pt; letter-spacing: 3px; text-transform: uppercase; color: #b8860b;">Investment</div>
                    <div style="font-size: 36pt; font-weight: 300; margin: 16px 0; letter-spacing: 2px;">£[amount]</div>
                    <div style="font-size: 9pt; color: #999; letter-spacing: 1px;">Exclusive of VAT</div>
                </div>
                
                <!-- Notes -->
                <div style="margin: 28px 24px; padding: 18px 22px; background: #f9f9f9; border: 1px solid #e8e8e8;">
                    <div style="font-size: 9pt; color: #b8860b; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px;">Additional Notes</div>
                    <div style="font-size: 10pt;">[notes]</div>
                </div>
                
                <!-- Warranty -->
                <div style="margin: 24px 24px;">
                    <div style="font-size: 10pt; font-weight: bold; margin-bottom: 8px; color: #1a1a1a;">Our Guarantee</div>
                    <p style="font-size: 10pt; color: #555;">[warranty]</p>
                </div>
                
                <!-- Terms -->
                <div style="margin: 24px 24px; padding-top: 20px; border-top: 1px solid #e5e5e5;">
                    <div style="font-size: 10pt; font-weight: bold; margin-bottom: 10px; color: #1a1a1a;">Terms of Engagement</div>
                    <div style="font-size: 9.5pt; line-height: 1.6; color: #555;">[terms]</div>
                </div>
                
                <!-- Validity -->
                <div style="margin: 24px 24px; text-align: center; font-size: 9.5pt; font-style: italic; color: #888;">
                    This proposal remains valid until [valid_until]
                </div>
                
                <!-- Signature -->
                <div style="margin: 40px 24px 0;">
                    <p style="margin-bottom: 8px;">With warmest regards,</p>
                    <div style="margin: 24px 0;">
                        [signature]
                    </div>
                    <div style="font-weight: bold;">[company_name]</div>
                </div>
                
                <!-- Acceptance -->
                <div style="margin: 50px 24px 0; padding-top: 24px; border-top: 1px solid #b8860b;">
                    <div style="font-size: 10pt; text-align: center; margin-bottom: 20px; color: #1a1a1a;">
                        <strong>Acceptance</strong>
                    </div>
                    <p style="font-size: 9.5pt; text-align: center; margin-bottom: 24px; color: #666;">
                        I accept this proposal and authorise the commencement of works as detailed above.
                    </p>
                    <table width="100%" style="font-size: 9.5pt;">
                        <tr>
                            <td style="width: 45%; border-bottom: 1px solid #ccc; height: 40px;"></td>
                            <td style="width: 10%;"></td>
                            <td style="width: 45%; border-bottom: 1px solid #ccc; height: 40px;"></td>
                        </tr>
                        <tr>
                            <td style="padding-top: 6px; color: #888;">Signature</td>
                            <td></td>
                            <td style="padding-top: 6px; color: #888;">Date</td>
                        </tr>
                    </table>
                </div>
                
                <!-- Footer -->
                <div style="margin-top: 36px; text-align: center;">
                    <div style="width: 40px; height: 1px; background: #b8860b; margin: 0 auto 12px;"></div>
                    <div style="font-size: 8pt; color: #999; letter-spacing: 1px;">
                        [company_name] | [website]
                    </div>
                </div>
            </div>
        ',
    ],

    /* ═══════════════════════════════════════════════════════════════════════════
       THAMES — Commercial / Large-Scale Infrastructure
       For major commercial and civil engineering projects
       ═══════════════════════════════════════════════════════════════════════════ */
    'thames' => [
        'name' => 'Thames Commercial',
        'html' => '
            <div style="font-family: \'Helvetica Neue\', Arial, sans-serif; font-size: 10pt; line-height: 1.5; color: #1e1e1e; padding: 0;">
                
                <!-- Header: Split 50/50 for window envelope -->
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <!-- Left 50%: Logo and Company Name (dark blue) -->
                        <td width="50%" valign="top" style="padding: 18px 24px 12px;">
                            <div style="background: #fff; padding: 8px; display: inline-block; border: 1px solid #e0e0e0; margin-bottom: 8px;">[logo]</div>
                            <div style="font-size: 18pt; font-weight: 600; color: #003366; letter-spacing: 0.5px;">[company_name]</div>
                        </td>
                        <!-- Right 50%: Empty to keep window clear -->
                        <td width="50%"></td>
                    </tr>
                </table>
                
                <!-- Navy and Grey stripes - ONLY 50% width, left aligned -->
                <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 20px;">
                    <tr>
                        <td width="50%" valign="top" style="padding: 18px 24px 12px;">
                            <!-- Navy stripe -->
                            <div style="background: #003366; color: #fff; padding: 12px 24px; font-size: 9pt; letter-spacing: 1px; text-transform: uppercase;">
                                Formal Proposal
                            </div>
                            <!-- Grey contact strip - left aligned -->
                            <div style="background: #f0f4f8; font-size: 8.5pt; color: #555; border-bottom: 1px solid #ddd; line-height: 1.6;">
                                <div>[company_address]</div>
                                <div style="margin-top: 4px;">[phone] | [email] | [website]</div>
                            </div>
                        </td>
                        <!-- Right side: Address positioned ~3 lines higher -->
                        <td width="50%" valign="top" style="padding: 0 24px 0 30px; text-align: right;">
                            <div style="font-size: 10pt; line-height: 1.5; text-align: right; margin-top: -36px;">
                                [address]
                            </div>
                        </td>
                    </tr>
                </table>
                
                <!-- Reference info below -->
                <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 10px; padding: 0 24px;">
                    <tr>
                        <td width="50%" style="vertical-align: top; padding-bottom: 20px;">
                            <div style="margin-top: 8px;">
                                <div style="font-size: 9pt; color: #666;">Issue Date</div>
                                <div style="font-size: 13pt; font-weight: bold; color: #003366;">[date]</div>
                            </div>
                            <div style="margin-top: 8px;">
                                <div style="font-size: 9pt; color: #666;">Expiry Date</div>
                                <div style="font-size: 11pt; color: #1e1e1e;">[valid_until]</div>
                            </div>
                        </td>
                        <td width="50%"></td>
                    </tr>
                </table>
                <div style="border-top: 3px solid #003366; margin-bottom: 4px; margin-top: 0px; width: 100%;"></div>
                <!-- Project Reference -->
                <div style="margin: 20px 24px; padding: 14px 20px; background: #003366; color: #fff;">
                    <div style="font-size: 8.5pt; text-transform: uppercase; letter-spacing: 1px; opacity: 0.8;">Project Description</div>
                    <div style="font-size: 11pt; margin-top: 4px;">[re_line]</div>
                </div>
                
                <!-- Main Content -->
                <div style="margin: 0 24px;">
                    <!-- Executive Summary -->
                    <div style="margin: 24px 0;">
                        <div style="font-size: 11pt; font-weight: bold; color: #003366; padding-bottom: 8px; border-bottom: 2px solid #003366; margin-bottom: 14px;">
                            1. EXECUTIVE SUMMARY
                        </div>
                        <p style="text-align: justify; line-height: 1.65;">
                            This proposal outlines our comprehensive solution for the construction works at the specified location. Our team brings extensive experience in delivering projects to the highest industry standards, ensuring complete client satisfaction.
                        </p>
                    </div>
                    
                    <!-- Scope of Works -->
                    <div style="margin: 24px 0;">
                        <div style="font-size: 11pt; font-weight: bold; color: #003366; padding-bottom: 8px; border-bottom: 2px solid #003366; margin-bottom: 14px;">
                            2. SCOPE OF WORKS
                        </div>
                        <div style="padding: 16px; background: #fafafa; border: 1px solid #e5e5e5; line-height: 1.65;">
                            [description]
                        </div>
                    </div>
                    
                    <!-- Commercial Terms -->
                    <div style="margin: 24px 0;">
                        <div style="font-size: 11pt; font-weight: bold; color: #003366; padding-bottom: 8px; border-bottom: 2px solid #003366; margin-bottom: 14px;">
                            3. COMMERCIAL TERMS
                        </div>
                        
                        <!-- Price Box -->
                        <div style="background: linear-gradient(180deg, #003366 0%, #004488 100%); color: #fff; padding: 24px; text-align: center; margin: 16px 0;">
                            <div style="font-size: 9pt; letter-spacing: 2px; text-transform: uppercase; opacity: 0.85;">Contract Sum</div>
                            <div style="font-size: 30pt; font-weight: 700; margin: 10px 0;">£[amount]</div>
                            <div style="font-size: 9pt; opacity: 0.75;">Exclusive of Value Added Tax</div>
                        </div>
                        
                        <div style="margin: 16px 0; padding: 14px 18px; background: #fffbf0; border-left: 4px solid #f5a623;">
                            <strong style="color: #946c00;">Notes:</strong> [notes]
                        </div>
                    </div>
                    
                    <!-- Warranty -->
                    <div style="margin: 24px 0;">
                        <div style="font-size: 11pt; font-weight: bold; color: #003366; padding-bottom: 8px; border-bottom: 2px solid #003366; margin-bottom: 14px;">
                            4. WARRANTY PROVISIONS
                        </div>
                        <p style="line-height: 1.6;">[warranty]</p>
                    </div>
                    
                    <!-- Terms & Conditions -->
                    <div style="margin: 24px 0;">
                        <div style="font-size: 11pt; font-weight: bold; color: #003366; padding-bottom: 8px; border-bottom: 2px solid #003366; margin-bottom: 14px;">
                            5. TERMS & CONDITIONS
                        </div>
                        <div style="font-size: 9.5pt; line-height: 1.6; padding: 14px 16px; background: #f8f9fa; border: 1px solid #e0e0e0;">
                            [terms]
                        </div>
                    </div>
                    
                    <!-- Signature Section -->
                    <div style="margin-top: 36px; padding: 20px; background: #f8f9fa; border: 1px solid #ddd;">
                        <div style="font-size: 10pt; font-weight: bold; color: #003366; margin-bottom: 16px;">AUTHORISATION</div>
                        <table width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="width: 48%; vertical-align: top; padding-right: 16px;">
                                    <div style="font-size: 9pt; color: #666; margin-bottom: 8px;">For and on behalf of [company_name]:</div>
                                    <div style="margin: 16px 0;">[signature]</div>
                                    <div style="border-top: 1px solid #003366; padding-top: 6px; font-size: 9pt;">
                                        <strong>Authorised Signatory</strong><br>Date: [date]
                                    </div>
                                </td>
                                <td style="width: 4%;"></td>
                                <td style="width: 48%; vertical-align: top; padding-left: 16px;">
                                    <div style="font-size: 9pt; color: #666; margin-bottom: 8px;">Accepted by Client:</div>
                                    <div style="height: 50px;"></div>
                                    <div style="border-top: 1px solid #003366; padding-top: 6px; font-size: 9pt;">
                                        <strong>Signature</strong><br>Date: _______________
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Footer -->
                    <div style="margin-top: 24px; padding-top: 12px; border-top: 3px solid #003366; font-size: 8pt; color: #666; text-align: center;">
                        [company_name] | Registered Office: [company_address] | [phone] | [email]
                    </div>
                </div>
            </div>
        ',
    ],

    /* ═══════════════════════════════════════════════════════════════════════════
       COTSWOLD — Traditional Craft / Heritage Building
       For heritage, restoration, and traditional building work
       ═══════════════════════════════════════════════════════════════════════════ */
    'cotswold' => [
        'name' => 'Cotswold Heritage',
        'html' => '
            <div style="font-family: \'Palatino Linotype\', \'Book Antiqua\', Palatino, serif; font-size: 11pt; line-height: 1.7; color: #3d3d3d; padding: 0; background: #ffffff;">
                
                <!-- Decorative Top Border -->
                <div style="height: 6px; background: linear-gradient(90deg, #8b7355 0%, #a08060 50%, #8b7355 100%); margin-bottom: 24px;"></div>
                
                <!-- Letterhead -->
                <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 24px; margin-left: 4px;">
                    <tr>
                        <td style="width: 75%; text-align: left; vertical-align: middle;">
                            <div style="font-size: 20pt; font-weight: normal; color: #5c4a32; letter-spacing: 1px; font-variant: small-caps;">
                                [company_name]
                            </div>
                            <div style="font-size: 9pt; color: #7a6b5a; margin-top: 8px; line-height: 1.6;">
                                [company_address]<br>
                                Telephone: [phone] | [email]
                            </div>
                        </td>
                        <td style="width: 25%; vertical-align: middle;"></td>
                    </tr>
                </table>
                
                <div style="border-bottom: 1px solid #c4b59d; margin-bottom: 24px; width: 50%;"></div>
                
                <!-- Reference & Recipient Block -->
                <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 65px; margin-top: 2px; font-size: 10pt;">
                    <tr>
                        <td style="width: 50%; vertical-align: top;">
                            <div style="font-size: 16pt; color: #5c4a32; font-variant: small-caps; letter-spacing: 2px; margin-bottom: 10px;">
                                Proposal for Building Works
                            </div>
                            <div style="font-size: 10pt; color: #8a7a68; font-style: italic; margin-bottom: 12px;">
                                Prepared with care on [date]
                            </div>
                            <div><strong>Valid until:</strong> [valid_until]</div>
                        </td>
                        <td style="width: 50%; vertical-align: top; text-align: right; padding-left: 30px; padding-right: 5px;">
                            <div style="font-size: 10pt; line-height: 1.3; padding-top: 0; margin-top: -6px;">
                                <span style="display: inline-block; margin-top: 18px; line-height: 1.6;">
                                </span><br>
                                [address]<br>
                            </div>
                        </td>
                    </tr>
                </table>
                
                <!-- Subject -->
                <div style="text-align: center; margin: 20px 0; padding: 12px; font-style: italic; color: #5c4a32; font-size: 11pt;">
                    <strong>Re:</strong> [re_line]
                </div>
                
                <!-- Introduction -->
                <p style="margin: 20px 0; text-align: justify;">
                    Dear Sir or Madam,
                </p>
                
                <p style="margin: 16px 0; text-align: justify;">
                    Thank you for inviting us to tender for the works at your property. We take great pride in our traditional craftsmanship and would be honoured to assist with your project.
                </p>
                
                <!-- Description Box -->
                <div style="margin: 24px 0; padding: 20px 24px; background: #fff; border: 2px solid #c4b59d; border-radius: 4px;">
                    <div style="font-size: 12pt; color: #5c4a32; font-variant: small-caps; letter-spacing: 1px; margin-bottom: 12px; border-bottom: 1px solid #d4c9b8; padding-bottom: 8px;">
                        Description of Works
                    </div>
                    <p style="margin: 0; line-height: 1.75;">[description]</p>
                </div>
                
                <!-- Price Display -->
                <div style="margin: 28px 0; text-align: center;">
                    <div style="display: inline-block; padding: 24px 48px; background: #5c4a32; color: #fff;">
                        <div style="font-size: 9pt; letter-spacing: 2px; text-transform: uppercase; opacity: 0.9;">Proposed Sum</div>
                        <div style="font-size: 28pt; font-weight: normal; margin: 10px 0; letter-spacing: 1px;">£[amount]</div>
                        <div style="font-size: 8.5pt; opacity: 0.8;">Exclusive of VAT</div>
                    </div>
                </div>
                
                <!-- Notes -->
                <div style="margin: 24px 0; padding: 16px 20px; background: #faf6ee; border-left: 4px solid #a08060;">
                    <div style="font-size: 10pt; font-weight: bold; color: #5c4a32; margin-bottom: 6px;">Notes & Inclusions</div>
                    <div style="font-size: 10pt; color: #5a5a5a;">[notes]</div>
                </div>
                
                <!-- Warranty -->
                <div style="margin: 20px 0;">
                    <div style="font-size: 11pt; color: #5c4a32; font-variant: small-caps; letter-spacing: 1px; margin-bottom: 8px;">Our Guarantee</div>
                    <p style="font-size: 10.5pt; color: #555;">[warranty]</p>
                </div>
                
                <!-- Terms -->
                <div style="margin: 20px 0;">
                    <div style="font-size: 11pt; color: #5c4a32; font-variant: small-caps; letter-spacing: 1px; margin-bottom: 8px;">Terms of Agreement</div>
                    <div style="font-size: 10pt; line-height: 1.6; color: #555; padding: 12px 16px; background: #f8f5ef; border: 1px solid #e0d8c8;">
                        [terms]
                    </div>
                </div>
                
                <!-- Closing -->
                <p style="margin: 28px 0 16px;">
                    We trust this proposal meets with your approval and look forward to hearing from you.
                </p>
                
                <p style="margin-bottom: 8px;">Yours faithfully,</p>
                
                <!-- Signature -->
                <div style="margin: 28px 0 20px;">
                    [signature]
                    <div style="margin-top: 8px; color: #5c4a32; font-weight: bold;">[company_name]</div>
                    <div style="font-size: 9.5pt; color: #8a7a68; font-style: italic;">Master Craftsmen</div>
                </div>
                
                <!-- Acceptance -->
                <div style="margin-top: 44px; padding-top: 20px; border-top: 2px solid #c4b59d;">
                    <div style="font-size: 11pt; text-align: center; color: #5c4a32; font-variant: small-caps; letter-spacing: 1px; margin-bottom: 16px;">
                        Acceptance
                    </div>
                    <p style="text-align: center; font-size: 10pt; color: #666; margin-bottom: 20px;">
                        I hereby accept this proposal and authorise the works to proceed.
                    </p>
                    <table width="100%" cellpadding="0" cellspacing="0" style="font-size: 9.5pt;">
                        <tr>
                            <td style="width: 45%; border-bottom: 1px solid #8b7355; height: 36px;"></td>
                            <td style="width: 10%;"></td>
                            <td style="width: 45%; border-bottom: 1px solid #8b7355; height: 36px;"></td>
                        </tr>
                        <tr>
                            <td style="padding-top: 6px; color: #8a7a68;">Signature</td>
                            <td></td>
                            <td style="padding-top: 6px; color: #8a7a68;">Date</td>
                        </tr>
                    </table>
                </div>
                
                <!-- Decorative Footer -->
                <div style="margin-top: 32px; text-align: center;">
                    <div style="font-size: 8.5pt; color: #a08060; letter-spacing: 1px;">
                        [company_name] • [phone] • [website]
                    </div>
                </div>
                
                <div style="height: 6px; background: linear-gradient(90deg, #8b7355 0%, #a08060 50%, #8b7355 100%); margin-top: 20px;"></div>
            </div>
        ',
    ],

    /* ═══════════════════════════════════════════════════════════════════════════
       CANARY — Modern Corporate / Developer Standard
       Clean, contemporary design for development companies
       ═══════════════════════════════════════════════════════════════════════════ */
    'canary' => [
        'name' => 'Canary Modern',
        'html' => '
            <div style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; font-size: 10pt; line-height: 1.55; color: #1a1a1a; padding: 0;">
                
                <!-- Clean Header -->
                <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 32px; padding-bottom: 30px;">
                    <tr>
                        <td style="width: 40%; vertical-align: middle;">
                            <div style="height: 56px;">[logo]</div>
                        </td>
                        <td style="width: 60%; text-align: right; vertical-align: top;">
                            <div style="font-size: 9pt; color: #666; line-height: 1.7;">
                                [company_name]<br>
                                [company_address]<br>
                                [phone] • [email]
                            </div>
                        </td>
                    </tr>
                </table>
                <div style="border-top: 1px solid #e5e5e5; margin-bottom: 4px; margin-top: 0px; width: 50%;"></div>
                <!-- Reference & Recipient Block -->
                <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 65px; margin-top: 2px; font-size: 10pt;">
                    <tr>
                        <td style="width: 50%; vertical-align: top;">
                            <span style="display: inline-block; padding: 6px 16px; background: #000; color: #fff; font-size: 9pt; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; margin-bottom: 16px;">
                                Proposal
                            </span>
                            <div style="margin-top: 12px;">
                                <div style="font-size: 9pt; color: #999; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Date</div>
                                <div style="font-size: 11pt;">[date]</div>
                            </div>
                            <div style="margin-top: 10px; font-size: 9pt; color: #999;">Valid Until: [valid_until]</div>
                        </td>
                        <td style="width: 50%; vertical-align: top; text-align: right; padding-left: 30px;">
                            <div style="font-size: 10pt; line-height: 1.3; padding-top: 0; margin-top: -6px;">
                                <span style="display: inline-block; margin-top: 18px; line-height: 1.6;">
                                </span><br>
                                [address]<br>
                            </div>
                        </td>
                    </tr>
                </table>
                
                <!-- Project Reference -->
                <div style="margin-bottom: 28px; padding: 16px 20px; background: #f7f7f7; border-radius: 4px;">
                    <div style="font-size: 9pt; color: #999; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Project</div>
                    <div style="font-size: 12pt; font-weight: 500;">[re_line]</div>
                </div>
                
                <!-- Introduction -->
                <p style="margin-bottom: 20px; color: #444;">
                    Thank you for considering us for your project. Please find below our proposal outlining the scope of works and associated costs.
                </p>
                
                <!-- Scope Section -->
                <div style="margin: 24px 0;">
                    <div style="font-size: 10pt; font-weight: 600; color: #000; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 2px solid #000;">
                        Scope of Works
                    </div>
                    <p style="line-height: 1.7; color: #333;">[description]</p>
                </div>
                
                <!-- Cost Section -->
                <div style="margin: 32px 0; padding: 28px; background: #000; color: #fff; text-align: center;">
                    <div style="font-size: 10pt; text-transform: uppercase; letter-spacing: 2px; opacity: 0.7; margin-bottom: 12px;">Total Investment</div>
                    <div style="font-size: 36pt; font-weight: 300; letter-spacing: 1px;">£[amount]</div>
                    <div style="font-size: 9pt; opacity: 0.6; margin-top: 8px;">Exclusive of VAT</div>
                </div>
                
                <!-- Notes -->
                <div style="margin: 24px 0; padding: 16px 20px; background: #fffbf0; border-left: 3px solid #f5c518;">
                    <div style="font-size: 9pt; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: #9a7b00; margin-bottom: 6px;">Notes</div>
                    <div style="font-size: 10pt; color: #6b5900;">[notes]</div>
                </div>
                
                <!-- Details Grid -->
                <table width="100%" cellpadding="0" cellspacing="0" style="margin: 28px 0;">
                    <tr>
                        <td style="width: 48%; vertical-align: top; padding-right: 16px;">
                            <div style="font-size: 10pt; font-weight: 600; margin-bottom: 10px; padding-bottom: 6px; border-bottom: 1px solid #ddd;">Warranty</div>
                            <div style="font-size: 9.5pt; color: #555; line-height: 1.6;">[warranty]</div>
                        </td>
                        <td style="width: 4%;"></td>
                        <td style="width: 48%; vertical-align: top; padding-left: 16px;">
                            <div style="font-size: 10pt; font-weight: 600; margin-bottom: 10px; padding-bottom: 6px; border-bottom: 1px solid #ddd;">Terms</div>
                            <div style="font-size: 9.5pt; color: #555; line-height: 1.6;">[terms]</div>
                        </td>
                    </tr>
                </table>
                
                <!-- Signature Section -->
                <div style="margin-top: 40px; padding-top: 24px; border-top: 1px solid #e5e5e5;">
                    <table width="100%" cellpadding="0" cellspacing="0">
                        <tr>
                            <td style="width: 48%; vertical-align: bottom;">
                                <div style="margin-bottom: 16px;">[signature]</div>
                                <div style="font-size: 10pt; font-weight: 600;">[company_name]</div>
                                <div style="font-size: 9pt; color: #666;">Authorised Representative</div>
                            </td>
                            <td style="width: 4%;"></td>
                            <td style="width: 48%; vertical-align: bottom;">
                                <div style="font-size: 9pt; color: #999; margin-bottom: 8px;">Accepted by Client:</div>
                                <div style="border-bottom: 1px solid #ccc; height: 50px;"></div>
                                <div style="margin-top: 6px; font-size: 9pt; color: #666;">Signature & Date</div>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Footer -->
                <div style="margin-top: 36px; padding-top: 16px; border-top: 1px solid #e5e5e5; text-align: center; font-size: 8.5pt; color: #999;">
                    [company_name] | [phone] | [email] | [website]
                </div>
            </div>
        ',
    ],

    /* ═══════════════════════════════════════════════════════════════════════════
       KENSINGTON — Architectural / Design-Forward
       For architects, designers, and bespoke residential projects
       ═══════════════════════════════════════════════════════════════════════════ */
    'kensington' => [
        'name' => 'Kensington Architectural',
        'html' => '
            <div style="font-family: \'Futura\', \'Century Gothic\', Arial, sans-serif; font-size: 10pt; line-height: 1.6; color: #2a2a2a; padding: 0;">
                
                <!-- Minimalist Header -->
                <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 40px;">
                    <tr>
                        <td style="width: 50%; vertical-align: bottom;">
                            <div style="height: 50px;">[logo]</div>
                        </td>
                        <td style="width: 50%; text-align: right; vertical-align: top;">
                            <div style="font-size: 8pt; letter-spacing: 2px; text-transform: uppercase; color: #888;">
                                Proposal Document
                            </div>
                        </td>
                    </tr>
                </table>
                
                <div style="height: 2px; background: #2a2a2a; margin-bottom: 36px; width: 50%;"></div>
                
                <!-- Reference & Recipient Block -->
                <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 65px; margin-top: 2px; font-size: 10pt;">
                    <tr>
                        <td style="width: 50%; vertical-align: top;">
                            <strong style="font-size: 11pt; color: #2a2a2a; letter-spacing: 1px;">[company_name]</strong><br>
                            <span style="font-size: 9pt; color: #666; line-height: 1.8;">
                                [company_address]<br>
                                [phone]<br>
                                [email]<br>
                                [website]
                            </span>
                            <div style="margin-top: 16px;">
                                <div style="font-size: 8pt; letter-spacing: 2px; text-transform: uppercase; color: #999; margin-bottom: 4px;">Date</div>
                                <div style="font-size: 12pt;">[date]</div>
                            </div>
                        </td>
                        <td style="width: 50%; vertical-align: top; text-align: right; padding-left: 30px;">
                            <div style="font-size: 10pt; line-height: 1.3; padding-top: 0; margin-top: -6px;">
                                <span style="display: inline-block; margin-top: 0px; line-height: 1.6;">
                                </span>
                                [address]<br>
                            </div>
                        </td>
                    </tr>
                </table>
                
                <!-- Project Reference -->
                <div style="margin-bottom: 36px; padding: 20px 0; border-top: 1px solid #e0e0e0; border-bottom: 1px solid #e0e0e0;">
                    <div style="font-size: 8pt; letter-spacing: 2px; text-transform: uppercase; color: #999; margin-bottom: 8px;">Project</div>
                    <div style="font-size: 14pt; font-weight: 500; letter-spacing: 0.5px;">[re_line]</div>
                </div>
                
                <!-- Validity -->
                <div style="margin-bottom: 28px; font-size: 9pt; color: #888;">
                    This proposal is valid until <strong style="color: #2a2a2a;">[valid_until]</strong>
                </div>
                
                <!-- Description -->
                <div style="margin: 32px 0;">
                    <div style="font-size: 8pt; letter-spacing: 2px; text-transform: uppercase; color: #999; margin-bottom: 12px;">Scope</div>
                    <p style="font-size: 10.5pt; line-height: 1.75; text-align: justify;">[description]</p>
                </div>
                
                <!-- Fee Proposal -->
                <div style="margin: 40px 0; text-align: center;">
                    <div style="display: inline-block; padding: 32px 56px; border: 2px solid #2a2a2a;">
                        <div style="font-size: 8pt; letter-spacing: 3px; text-transform: uppercase; color: #888; margin-bottom: 12px;">Fee Proposal</div>
                        <div style="font-size: 32pt; font-weight: 300; color: #2a2a2a; letter-spacing: 1px;">£[amount]</div>
                        <div style="font-size: 8pt; color: #999; margin-top: 8px; letter-spacing: 1px;">EXCLUSIVE OF VAT</div>
                    </div>
                </div>
                
                <!-- Notes -->
                <div style="margin: 28px 0; padding: 18px 24px; background: #f9f9f9;">
                    <div style="font-size: 8pt; letter-spacing: 2px; text-transform: uppercase; color: #999; margin-bottom: 8px;">Notes</div>
                    <div style="font-size: 10pt; line-height: 1.65;">[notes]</div>
                </div>
                
                <!-- Warranty & Terms -->
                <div style="margin: 28px 0;">
                    <div style="font-size: 8pt; letter-spacing: 2px; text-transform: uppercase; color: #999; margin-bottom: 12px;">Warranty</div>
                    <p style="font-size: 10pt; line-height: 1.65; margin-bottom: 20px;">[warranty]</p>
                    
                    <div style="font-size: 8pt; letter-spacing: 2px; text-transform: uppercase; color: #999; margin-bottom: 12px;">Terms</div>
                    <p style="font-size: 9.5pt; line-height: 1.6; color: #555;">[terms]</p>
                </div>
                
                <!-- Signature -->
                <div style="margin-top: 48px;">
                    <div style="margin-bottom: 24px;">[signature]</div>
                    <div style="font-size: 10pt; font-weight: 500;">[company_name]</div>
                </div>
                
                <div style="height: 2px; background: #2a2a2a; margin: 36px 0;"></div>
                
                <!-- Acceptance -->
                <div style="margin-top: 32px;">
                    <div style="font-size: 8pt; letter-spacing: 2px; text-transform: uppercase; color: #999; margin-bottom: 20px;">Acceptance</div>
                    <p style="font-size: 9.5pt; color: #666; margin-bottom: 24px;">
                        I confirm acceptance of this proposal and authorise commencement of works.
                    </p>
                    <table width="100%" cellpadding="0" cellspacing="0">
                        <tr>
                            <td style="width: 45%;">
                                <div style="border-bottom: 1px solid #2a2a2a; height: 36px;"></div>
                                <div style="font-size: 8pt; color: #999; margin-top: 6px; letter-spacing: 1px;">SIGNATURE</div>
                            </td>
                            <td style="width: 10%;"></td>
                            <td style="width: 45%;">
                                <div style="border-bottom: 1px solid #2a2a2a; height: 36px;"></div>
                                <div style="font-size: 8pt; color: #999; margin-top: 6px; letter-spacing: 1px;">DATE</div>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        ',
    ],

]);
require_once __DIR__ . '/includes/rest.php';
require_once __DIR__ . '/includes/shortcode.php';
require_once __DIR__ . '/includes/pdf-editor.php';

add_action('wp_enqueue_scripts', function () {
    if (!is_user_logged_in()) return;

    wp_enqueue_style('select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', [], '4.1.0');
    wp_enqueue_script('select2-js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], '4.1.0', true);

    wp_enqueue_style('pi-invoices-css', plugin_dir_url(__FILE__) . 'assets/invoices.css', [], '1.0');

    // FIXED: Use legacy build for global pdfjsLib (avoids ESM errors)
    wp_enqueue_script('pdf-js-worker', 'https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.worker.min.js', [], '3.11.174', true);
    wp_enqueue_script('pdf-js', 'https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.min.js', [], '3.11.1749', true);
    wp_enqueue_script('pdf-lib', 'https://cdn.jsdelivr.net/npm/pdf-lib@1.17.1/dist/pdf-lib.min.js', [], '1.17.1', true);

    // Your main script depends on PDF.js
    wp_enqueue_script('pi-invoices-js', plugin_dir_url(__FILE__) . 'assets/invoices.js', 
        ['jquery', 'select2-js', 'pdf-js', 'pdf-js-worker', 'pdf-lib'], '1.0', true);

    wp_localize_script('pi-invoices-js', 'PI_Invoices', [
        'rest_base' => rest_url('pi/v1/workspace/invoices'),
        'nonce'     => wp_create_nonce('wp_rest'),
        'statuses'  => ['draft', 'mailed', 'won', 'lost'],
    ]);
});

add_action('admin_menu', function() {
    add_submenu_page('planning-index', 'Edit Proposal PDFs', 'Edit PDFs', 'manage_options', 'pi-pdf-editor', 'pi_pdf_editor_page');
});

function pi_pdf_editor_page() {
    $invoices = get_user_meta(get_current_user_id(), PII_INVOICES_META, true) ?: [];
    echo '<h1>Edit Proposal PDFs</h1>';
    foreach ($invoices as $inv) {
        echo '<div><a href="?inv_id=' . $inv['id'] . '">Edit #' . $inv['id'] . '</a></div>';
    }

    if (isset($_GET['inv_id'])) {
        $id = intval($_GET['inv_id']);
        // Fetch inv, show form for fields, preview iframe with pdf_url
        // On submit, call PI_PDF_Editor::update_fields
    }
}
add_action('pi_business_info_updated', function($user_id, $business_info) {
    $invoices = get_user_meta($user_id, PII_INVOICES_META, true) ?: [];
    $upload_dir = wp_upload_dir();

    // CRITICAL: Use the NEW template from business_info, not the old invoice template
    $tmpl_key = $business_info['default_template'] ?? 'basic';
    error_log("[PI Settings Update] Regenerating invoices for user #$user_id with template: $tmpl_key");

    foreach ($invoices as $inv) {
        if (empty($inv['pdf_url'])) continue;

        $pdf_data = [
            'company_name'     => $business_info['company_name'] ?? '',
            'company_address'  => $business_info['company_address'] ?? '',
            'phone'            => $business_info['phone'] ?? '',
            'email'            => $business_info['email'] ?? '',
            'website'          => $business_info['website'] ?? '',
            'date'             => date('d/m/Y', strtotime($inv['created'])),
            'valid_until'      => date('d/m/Y', strtotime($inv['created'] . ' +30 days')),
            'amount'           => number_format($inv['amount'], 2),
            'terms'            => $business_info['default_terms'] ?? '30% deposit, balance on completion.',
            'warranty'         => $business_info['default_warranty'] ?? '5 years',
            'description'      => $inv['notes'] ?: 'We are pleased to submit our proposal for works at the above address.',
            'address'          => $inv['address'],
            're_line'          => ($tmpl_key === 'window') ? "Proposal for Window Installation at {$inv['address']}." : "Overture to contract services in relation to the successfully granted planning application at {$inv['address']}.",
            'logo'             => $business_info['logo'] ?? '',
            'signature'        => $business_info['signature'] ?? '',
            'notes'            => $inv['notes']
        ];

        if (!empty($inv['pdf_url'])) {
            $pdf_path = pi_get_pdf_path_from_url($inv['pdf_url']);
            PI_PDF_Editor::generate_or_update($tmpl_key, $pdf_data, $pdf_path, true);
        }

    }
}, 10, 2);
