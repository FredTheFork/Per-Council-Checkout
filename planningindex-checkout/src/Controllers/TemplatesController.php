<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * GET /planningindex/v1/templates
 *
 * Returns available templates matching the React app's PdfTemplate interface,
 * plus the logged-in user's currently saved template for pre-selection.
 * Pulls from PI_PDF_TEMPLATES if the proposals plugin is active.
 */
class PIC_Templates_Controller
{
    private static $accent_map = [
        'basic'       => 'brand',
        'westminster' => 'brand',
        'brunel'      => 'warning',
        'mayfair'     => 'accent',
        'thames'      => 'brand',
        'cotswold'    => 'accent',
        'canary'      => 'success',
        'kensington'  => 'brand',
    ];

    private static $category_map = [
        'basic'       => 'Standard',
        'westminster' => 'Formal',
        'brunel'      => 'Industrial',
        'mayfair'     => 'Luxury',
        'thames'      => 'Commercial',
        'cotswold'    => 'Heritage',
        'canary'      => 'Modern',
        'kensington'  => 'Architectural',
    ];

    private static $description_map = [
        'basic'       => 'Clean, professional, and timeless. A straightforward layout suitable for any planning proposal.',
        'westminster' => 'British government/corporate standard. Formal layout inspired by UK construction correspondence.',
        'brunel'      => 'Industrial heritage style for major contractors. Bold navy and orange design with engineering-focused layout.',
        'mayfair'     => 'Luxury and high-end residential. Understated elegance with gold accents for premium projects.',
        'thames'      => 'Commercial and large-scale infrastructure. Navy and grey design for major commercial projects.',
        'cotswold'    => 'Traditional craft and heritage building. Warm earth tones for restoration and traditional building work.',
        'canary'      => 'Modern corporate and developer standard. Clean, contemporary design for development companies.',
        'kensington'  => 'Architectural and design-forward. Minimalist layout for architects and bespoke residential projects.',
    ];

    /**
     * @return WP_REST_Response
     */
    public static function get_templates(WP_REST_Request $request)
    {
        $templates = self::build_templates();

        $user_current_template = null;
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $business_info = get_user_meta($user_id, '_pi_business_info', true);
            if (is_array($business_info) && !empty($business_info['default_template'])) {
                $user_current_template = $business_info['default_template'];
            } else {
                $saved = get_user_meta($user_id, PIC_META_TEMPLATE, true);
                if (!empty($saved)) {
                    $user_current_template = $saved;
                }
            }
        }

        return new WP_REST_Response([
            'templates' => $templates,
            'userCurrentTemplate' => $user_current_template,
        ], 200);
    }

    /**
     * Builds the template list matching the React PdfTemplate interface.
     * Pulls from PI_PDF_TEMPLATES if the proposals plugin is active.
     *
     * @return array<int, array>
     */
    private static function build_templates(): array
    {
        if (defined('PI_PDF_TEMPLATES') && is_array(PI_PDF_TEMPLATES)) {
            $templates = [];
            foreach (PI_PDF_TEMPLATES as $key => $tmpl) {
                $templates[] = [
                    'id'          => $key,
                    'name'        => $tmpl['name'] ?? ucfirst($key),
                    'description' => self::$description_map[$key] ?? ($tmpl['description'] ?? ''),
                    'category'    => self::$category_map[$key] ?? 'Standard',
                    'included'    => true,
                    'price'       => 0,
                    'accent'      => self::$accent_map[$key] ?? 'brand',
                    'previewUrl'  => '',
                    'thumbnailUrl' => '',
                    'html'        => $tmpl['html'] ?? '',
                ];
            }
            return $templates;
        }

        return self::fallback_templates();
    }

    private static function fallback_templates(): array
    {
        $keys = ['basic', 'westminster', 'brunel', 'mayfair', 'thames', 'cotswold', 'canary', 'kensington'];
        $templates = [];
        foreach ($keys as $key) {
            $templates[] = [
                'id'          => $key,
                'name'        => self::fallback_name($key),
                'description' => self::$description_map[$key] ?? '',
                'category'    => self::$category_map[$key] ?? 'Standard',
                'included'    => true,
                'price'       => 0,
                'accent'      => self::$accent_map[$key] ?? 'brand',
                'previewUrl'  => '',
                'thumbnailUrl' => '',
                'html'        => '',
            ];
        }
        return $templates;
    }

    private static function fallback_name(string $key): string
    {
        $names = [
            'basic'       => 'Basic Proposal',
            'westminster' => 'Westminster Formal',
            'brunel'      => 'Brunel Industrial',
            'mayfair'     => 'Mayfair Premium',
            'thames'      => 'Thames Commercial',
            'cotswold'    => 'Cotswold Heritage',
            'canary'      => 'Canary Modern',
            'kensington'  => 'Kensington Architectural',
        ];
        return $names[$key] ?? ucfirst($key);
    }
}
