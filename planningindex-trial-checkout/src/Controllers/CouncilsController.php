<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * GET /planningindex/v1/councils
 *
 * Returns the full council list with nation and region grouping.
 */
class PIT_Councils_Controller
{
    public static function get_councils(WP_REST_Request $request)
    {
        return new WP_REST_Response([
            'councils' => PIT_CouncilData::all(),
            'nations' => PIT_CouncilData::nations(),
            'regions' => PIT_CouncilData::regions(),
        ], 200);
    }
}
