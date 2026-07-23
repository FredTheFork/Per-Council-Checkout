<?php

if (!defined('ABSPATH')) {
    exit;
}

class PIRB_Regions_Controller
{
    public static function get_regions(WP_REST_Request $request)
    {
        $regions = [];
        foreach (PIRB_RegionData::all() as $name => $bundle) {
            $regions[] = [
                'id'       => $name,
                'name'     => $name,
                'price'    => floatval($bundle['price'] ?? 0),
                'councils' => $bundle['councils'] ?? [],
            ];
        }

        return new WP_REST_Response([
            'regions' => $regions,
        ], 200);
    }
}
