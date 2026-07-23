<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * GET /planningindex/v1/templates
 *
 * Returns available templates matching the React app's PdfTemplate interface,
 * plus the logged-in user's currently saved template for pre-selection.
 */
class PIC_Templates_Controller
{
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
     *
     * @return array<int, array{id: string, name: string, description: string, category: string, included: bool, price: int, accent: string}>
     */
    private static function build_templates(): array
    {
        $base = pmpc_get_templates();

        $shaped = [];
        foreach ($base as $id => $data) {
            $shaped[] = [
                'id' => $id,
                'name' => $data['name'] ?? ucfirst($id),
                'description' => $data['description'] ?? '',
                'category' => self::category_for($id),
                'included' => true,
                'price' => 0,
                'accent' => self::accent_for($id),
            ];
        }

        return $shaped;
    }

    private static function category_for(string $id): string
    {
        $categories = [
            'professional' => 'Planning Application',
            'modern' => 'Design Statement',
            'classic' => 'Heritage',
            'minimal' => 'Appeal',
        ];

        return $categories[$id] ?? 'General';
    }

    private static function accent_for(string $id): string
    {
        $accents = [
            'professional' => 'brand',
            'modern' => 'success',
            'classic' => 'accent',
            'minimal' => 'warning',
        ];

        return $accents[$id] ?? 'brand';
    }
}
