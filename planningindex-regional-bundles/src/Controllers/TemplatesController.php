<?php

if (!defined('ABSPATH')) {
    exit;
}

class PIRB_Templates_Controller
{
    public static function get_templates(WP_REST_Request $request)
    {
        $templates = [
            [
                'id'          => 'standard-planning',
                'name'        => 'Standard Planning Proposal',
                'description'  => 'A clean, professional template for standard planning applications. Includes all essential sections for a complete submission.',
                'category'    => 'Planning Application',
                'included'    => true,
                'price'       => 0,
                'accent'      => 'brand',
            ],
            [
                'id'          => 'detailed-design',
                'name'        => 'Detailed Design & Access',
                'description'  => 'Comprehensive design and access statement template with detailed sections covering design principles, access arrangements, and sustainability.',
                'category'    => 'Design Statement',
                'included'    => true,
                'price'       => 0,
                'accent'      => 'success',
            ],
            [
                'id'          => 'heritage-statement',
                'name'        => 'Heritage Impact Statement',
                'description'  => 'Specialised template for applications affecting listed buildings and conservation areas. Covers historical context and impact assessment.',
                'category'    => 'Heritage',
                'included'    => true,
                'price'       => 0,
                'accent'      => 'accent',
            ],
            [
                'id'          => 'planning-appeal',
                'name'        => 'Planning Appeal Document',
                'description' => 'Structured template for planning appeals with clear argument sections, supporting evidence framework, and statement of grounds.',
                'category'    => 'Appeal',
                'included'    => true,
                'price'       => 0,
                'accent'      => 'warning',
            ],
            [
                'id'          => 'community-infra',
                'name'        => 'Community Infrastructure Levy',
                'description' => 'Template for CIL-related submissions with calculation worksheets, liability assessment, and relief claim sections.',
                'category'    => 'Infrastructure',
                'included'    => true,
                'price'       => 0,
                'accent'      => 'brand',
            ],
            [
                'id'          => 'environmental-impact',
                'name'        => 'Environmental Impact Assessment',
                'description' => 'Comprehensive EIA template covering screening, scoping, and full environmental statements with all required regulatory sections.',
                'category'    => 'Environmental',
                'included'    => true,
                'price'       => 0,
                'accent'      => 'success',
            ],
        ];

        $user_current_template = null;
        $user_id = get_current_user_id();
        if ($user_id > 0) {
            $bi = get_user_meta($user_id, '_pi_business_info', true);
            if (is_array($bi) && isset($bi['template']) && !empty($bi['template'])) {
                $user_current_template = $bi['template'];
            } else {
                $t = get_user_meta($user_id, PIRB_META_TEMPLATE, true);
                if (!empty($t)) {
                    $user_current_template = $t;
                }
            }
        }

        return new WP_REST_Response([
            'templates'           => $templates,
            'userCurrentTemplate' => $user_current_template,
        ], 200);
    }
}
