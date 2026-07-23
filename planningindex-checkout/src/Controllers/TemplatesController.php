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
     * Template IDs mirror react/src/data/templates.ts exactly.
     *
     * @return array<int, array{id: string, name: string, description: string, category: string, included: bool, price: int, accent: string}>
     */
    private static function build_templates(): array
    {
        return [
            [
                'id' => 'standard-planning',
                'name' => 'Standard Planning Proposal',
                'description' => 'A clean, professional template for standard planning applications. Includes all essential sections for a complete submission.',
                'category' => 'Planning Application',
                'included' => true,
                'price' => 0,
                'accent' => 'brand',
            ],
            [
                'id' => 'detailed-design',
                'name' => 'Detailed Design & Access',
                'description' => 'Comprehensive design and access statement template with detailed sections covering design principles, access arrangements, and sustainability.',
                'category' => 'Design Statement',
                'included' => true,
                'price' => 0,
                'accent' => 'success',
            ],
            [
                'id' => 'heritage-statement',
                'name' => 'Heritage Impact Statement',
                'description' => 'Specialised template for applications affecting listed buildings and conservation areas. Covers historical context and impact assessment.',
                'category' => 'Heritage',
                'included' => true,
                'price' => 0,
                'accent' => 'accent',
            ],
            [
                'id' => 'planning-appeal',
                'name' => 'Planning Appeal Document',
                'description' => 'Structured template for planning appeals with clear argument sections, supporting evidence framework, and statement of grounds.',
                'category' => 'Appeal',
                'included' => true,
                'price' => 0,
                'accent' => 'warning',
            ],
            [
                'id' => 'community-infra',
                'name' => 'Community Infrastructure Levy',
                'description' => 'Template for CIL-related submissions with calculation worksheets, liability assessment, and relief claim sections.',
                'category' => 'Infrastructure',
                'included' => true,
                'price' => 0,
                'accent' => 'brand',
            ],
            [
                'id' => 'environmental-impact',
                'name' => 'Environmental Impact Assessment',
                'description' => 'Comprehensive EIA template covering screening, scoping, and full environmental statements with all required regulatory sections.',
                'category' => 'Environmental',
                'included' => true,
                'price' => 0,
                'accent' => 'success',
            ],
        ];
    }
}
