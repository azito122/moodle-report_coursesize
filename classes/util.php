<?php

namespace report_coursesize;

class util {
    public static function bytes_to_megabytes($bytes) {
        return number_format(ceil($bytes / 1048576));
    }

    public static function array_push_unique(&$array, $value) {
        if (!in_array($value, $array)) {
            array_push($array, $value);
        }
    }

    public static function cache_get($cache, $key, $default) {
        if ($r = $cache->get($key)) {
            return $r;
        } else {
            return $default;
        }
    }
}