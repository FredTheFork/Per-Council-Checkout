<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * GET /planningindex/v1/councils
 *
 * Returns the full council list with nation and region grouping,
 * matching the React app's Council interface.
 */
class PIC_Councils_Controller
{
    /**
     * @return WP_REST_Response
     */
    public static function get_councils(WP_REST_Request $request)
    {
        return new WP_REST_Response([
            'councils' => PIC_CouncilData::all(),
            'nations' => PIC_CouncilData::nations(),
            'regions' => PIC_CouncilData::regions(),
        ], 200);
    }
}
