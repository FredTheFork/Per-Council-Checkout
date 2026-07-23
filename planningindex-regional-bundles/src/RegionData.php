<?php

if (!defined('ABSPATH')) {
    exit;
}

class PIRB_RegionData
{
    private static $bundles = null;

    public static function all(): array
    {
        if (self::$bundles !== null) {
            return self::$bundles;
        }

        if (function_exists('pis_get_region_bundles')) {
            self::$bundles = pis_get_region_bundles();
        } else {
            self::$bundles = [];
        }

        return self::$bundles;
    }

    public static function names(): array
    {
        return array_keys(self::all());
    }

    public static function price_for(string $region): float
    {
        $bundles = self::all();
        if (isset($bundles[$region]['price'])) {
            return floatval($bundles[$region]['price']);
        }
        return 0.0;
    }

    public static function councils_for(string $region): array
    {
        $bundles = self::all();
        if (isset($bundles[$region]['councils']) && is_array($bundles[$region]['councils'])) {
            return $bundles[$region]['councils'];
        }
        return [];
    }
}
