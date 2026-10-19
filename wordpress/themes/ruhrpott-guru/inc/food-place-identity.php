<?php
/**
 * Ruhrpott Guru – reversible Food Place Identity & Filter V2.
 *
 * IMPORTANT:
 * - Provider query stays broad. Nothing is deleted upstream.
 * - Filtering is conservative and reversible.
 * - Current names win over old/previous names only when OSM itself provides
 *   strong rename evidence and the records refer to the same physical place.
 */

if (!function_exists('ruhrpott_guru_food_normalize_text')) {
    function ruhrpott_guru_food_normalize_text($value) {
        $value = trim((string) $value);
        if ($value === '') return '';
        if (function_exists('remove_accents')) {
            $value = remove_accents($value);
        }
        if (function_exists('mb_strtolower')) {
            $value = mb_strtolower($value, 'UTF-8');
        } else {
            $value = strtolower($value);
        }
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value);
        return trim(preg_replace('/\s+/', ' ', $value));
    }
}

if (!function_exists('ruhrpott_guru_food_split_names')) {
    function ruhrpott_guru_food_split_names($value) {
        $out = array();
        foreach (preg_split('/\s*[;|]\s*/', (string) $value) as $part) {
            $part = trim($part);
            if ($part !== '') $out[] = $part;
        }
        return array_values(array_unique($out));
    }
}

if (!function_exists('ruhrpott_guru_food_display_name')) {
    function ruhrpott_guru_food_display_name($tags) {
        if (!is_array($tags)) return '';
        foreach (array('name', 'brand') as $key) {
            if (isset($tags[$key]) && trim((string) $tags[$key]) !== '') {
                return trim((string) $tags[$key]);
            }
        }
        return '';
    }
}

if (!function_exists('ruhrpott_guru_food_known_rename_pairs')) {
    function ruhrpott_guru_food_known_rename_pairs() {
        // User-confirmed local rename. This rule is only applied when BOTH old
        // and new records are present at the same physical place, so it never
        // invents a new venue when only the historical record exists.
        return array(
            'mezzomar' => 'oliv',
        );
    }
}

if (!function_exists('ruhrpott_guru_food_aliases')) {
    function ruhrpott_guru_food_aliases($tags) {
        if (!is_array($tags)) return array();
        $aliases = array();
        // old_name / previous_name are strong history signals. alt_name is NOT
        // used for rename dedupe because it can be a concurrent valid name.
        foreach (array('old_name', 'previous_name', 'former_name', 'was:name') as $key) {
            if (!empty($tags[$key])) {
                $aliases = array_merge($aliases, ruhrpott_guru_food_split_names($tags[$key]));
            }
        }
        $seen = array();
        $out = array();
        foreach ($aliases as $alias) {
            $norm = ruhrpott_guru_food_normalize_text($alias);
            if ($norm !== '' && !isset($seen[$norm])) {
                $seen[$norm] = true;
                $out[] = $alias;
            }
        }
        return $out;
    }
}

if (!function_exists('ruhrpott_guru_food_is_explicitly_inactive')) {
    function ruhrpott_guru_food_is_explicitly_inactive($tags) {
        if (!is_array($tags)) return false;
        foreach (array('disused', 'abandoned', 'demolished', 'razed', 'closed') as $key) {
            if (!isset($tags[$key])) continue;
            $v = ruhrpott_guru_food_normalize_text($tags[$key]);
            if (in_array($v, array('yes', 'true', '1'), true)) return true;
        }
        foreach (array(
            'disused:amenity', 'abandoned:amenity', 'demolished:amenity',
            'razed:amenity', 'was:amenity', 'removed:amenity'
        ) as $key) {
            if (isset($tags[$key]) && trim((string) $tags[$key]) !== '') return true;
        }
        return false;
    }
}

if (!function_exists('ruhrpott_guru_food_coords')) {
    function ruhrpott_guru_food_coords($element) {
        if (!is_array($element)) return null;
        if (isset($element['lat'], $element['lon']) && is_numeric($element['lat']) && is_numeric($element['lon'])) {
            return array((float) $element['lat'], (float) $element['lon']);
        }
        if (isset($element['center']['lat'], $element['center']['lon']) && is_numeric($element['center']['lat']) && is_numeric($element['center']['lon'])) {
            return array((float) $element['center']['lat'], (float) $element['center']['lon']);
        }
        return null;
    }
}

if (!function_exists('ruhrpott_guru_food_distance_m')) {
    function ruhrpott_guru_food_distance_m($a, $b) {
        if (!$a || !$b) return INF;
        $earth = 6371000.0;
        $lat1 = deg2rad($a[0]);
        $lat2 = deg2rad($b[0]);
        $dlat = deg2rad($b[0] - $a[0]);
        $dlon = deg2rad($b[1] - $a[1]);
        $h = sin($dlat / 2) * sin($dlat / 2) + cos($lat1) * cos($lat2) * sin($dlon / 2) * sin($dlon / 2);
        return $earth * (2 * atan2(sqrt($h), sqrt(max(0.0, 1.0 - $h))));
    }
}

if (!function_exists('ruhrpott_guru_food_address_key')) {
    function ruhrpott_guru_food_address_key($tags) {
        if (!is_array($tags)) return '';
        $parts = array();
        foreach (array('addr:street', 'addr:housenumber', 'addr:postcode', 'addr:city') as $key) {
            $parts[] = isset($tags[$key]) ? ruhrpott_guru_food_normalize_text($tags[$key]) : '';
        }
        // Require at least street + housenumber to call an address strong.
        if ($parts[0] === '' || $parts[1] === '') return '';
        return implode('|', $parts);
    }
}

if (!function_exists('ruhrpott_guru_food_same_place')) {
    function ruhrpott_guru_food_same_place($a, $b) {
        $ta = isset($a['tags']) && is_array($a['tags']) ? $a['tags'] : array();
        $tb = isset($b['tags']) && is_array($b['tags']) ? $b['tags'] : array();
        $addr_a = ruhrpott_guru_food_address_key($ta);
        $addr_b = ruhrpott_guru_food_address_key($tb);
        $distance = ruhrpott_guru_food_distance_m(ruhrpott_guru_food_coords($a), ruhrpott_guru_food_coords($b));

        if ($addr_a !== '' && $addr_a === $addr_b && $distance <= 80) return true;
        // Without a complete address require almost identical coordinates.
        return $distance <= 20;
    }
}

if (!function_exists('ruhrpott_guru_food_element_score')) {
    function ruhrpott_guru_food_element_score($element) {
        $tags = isset($element['tags']) && is_array($element['tags']) ? $element['tags'] : array();
        $score = count($tags);
        if (ruhrpott_guru_food_address_key($tags) !== '') $score += 20;
        if (!empty($tags['website'])) $score += 4;
        if (!empty($tags['opening_hours'])) $score += 4;
        if (!empty($tags['phone']) || !empty($tags['contact:phone'])) $score += 2;
        if (!empty($tags['old_name']) || !empty($tags['previous_name'])) $score += 3;
        return $score;
    }
}

if (!function_exists('ruhrpott_guru_food_filter_data')) {
    function ruhrpott_guru_food_filter_data($data, $enabled = true) {
        if (!is_array($data) || !isset($data['elements']) || !is_array($data['elements'])) {
            return array('data' => $data, 'meta' => array('enabled' => (bool) $enabled, 'error' => 'invalid-elements'));
        }

        $raw = array_values($data['elements']);
        $meta = array(
            'version' => 'food-filter-v2',
            'enabled' => (bool) $enabled,
            'rawCount' => count($raw),
            'visibleCount' => count($raw),
            'excluded' => array('explicitly-inactive' => 0, 'no-name' => 0, 'duplicate' => 0, 'renamed-old-record' => 0, 'local-confirmed-closed' => 0),
            'renames' => array(),
            'corrections' => array(),
        );

        if (!$enabled) {
            $meta['mode'] = 'raw';
            return array('data' => $data, 'meta' => $meta);
        }

        // Stage 1: conservative hard exclusions plus a tiny, explicit local
        // correction registry. These entries are user-confirmed and fully
        // reversible by setting rg_food_filter=off. Generic provider data is
        // never rewritten from fuzzy name matching alone.
        $candidates = array();
        $wesel_center = array(51.65627, 6.62170);
        $oliv_coords = array(51.656753, 6.617025);
        foreach ($raw as $element) {
            $tags = isset($element['tags']) && is_array($element['tags']) ? $element['tags'] : array();
            if (ruhrpott_guru_food_is_explicitly_inactive($tags)) {
                $meta['excluded']['explicitly-inactive']++;
                continue;
            }
            $display_name = ruhrpott_guru_food_display_name($tags);
            if ($display_name === '') {
                $meta['excluded']['no-name']++;
                continue;
            }

            $norm_name = ruhrpott_guru_food_normalize_text($display_name);
            $coords = ruhrpott_guru_food_coords($element);
            $in_wesel_core = $coords && ruhrpott_guru_food_distance_m($coords, $wesel_center) <= 2500;

            // User-confirmed closed local records. Constrain to Wesel core so
            // similarly named businesses elsewhere are never affected.
            if ($in_wesel_core && in_array($norm_name, array('san marino', 'san marino ii', 'berliner stube', 'berliner stuben'), true)) {
                $meta['excluded']['local-confirmed-closed']++;
                $meta['corrections'][] = array('action' => 'exclude', 'name' => $display_name, 'reason' => 'local-confirmed-closed');
                continue;
            }

            // Confirmed local rename at Kreuzstraße 1a: Mezzomar ->
            // Café-Bistro Oliv. Apply only to the physical venue, never to
            // other Mezzomar locations. This deliberately works even if OSM
            // still contains only the historical name.
            if (in_array($norm_name, array('mezzomar', 'mezzomar wesel'), true) && $coords && ruhrpott_guru_food_distance_m($coords, $oliv_coords) <= 120) {
                if (!isset($element['tags']) || !is_array($element['tags'])) $element['tags'] = array();
                $element['tags']['name'] = 'Café-Bistro Oliv';
                if (empty($element['tags']['old_name'])) $element['tags']['old_name'] = $display_name;
                $element['tags']['rg:aliases'] = $display_name;
                $element['tags']['rg:correction'] = 'mezzomar-to-oliv';
                $meta['renames'][] = array('current' => 'Café-Bistro Oliv', 'previous' => $display_name, 'evidence' => 'local-confirmed-rename');
                $meta['corrections'][] = array('action' => 'rename', 'from' => $display_name, 'to' => 'Café-Bistro Oliv');
            }

            $candidates[] = $element;
        }

        // Stage 2: rename-aware identity resolution.
        // Strong rule only: A is current and B is old when A.old_name / A.previous_name
        // explicitly contains B.name AND both resolve to the same physical place.
        $remove = array();
        $count = count($candidates);
        for ($i = 0; $i < $count; $i++) {
            if (isset($remove[$i])) continue;
            $tags_i = isset($candidates[$i]['tags']) && is_array($candidates[$i]['tags']) ? $candidates[$i]['tags'] : array();
            $name_i = ruhrpott_guru_food_display_name($tags_i);
            $aliases_i = ruhrpott_guru_food_aliases($tags_i);
            $alias_norm_i = array();
            foreach ($aliases_i as $alias) $alias_norm_i[ruhrpott_guru_food_normalize_text($alias)] = true;

            for ($j = 0; $j < $count; $j++) {
                if ($i === $j || isset($remove[$j])) continue;
                $tags_j = isset($candidates[$j]['tags']) && is_array($candidates[$j]['tags']) ? $candidates[$j]['tags'] : array();
                $name_j = ruhrpott_guru_food_display_name($tags_j);
                if ($name_i === '' || $name_j === '' || !ruhrpott_guru_food_same_place($candidates[$i], $candidates[$j])) continue;

                $norm_i = ruhrpott_guru_food_normalize_text($name_i);
                $norm_j = ruhrpott_guru_food_normalize_text($name_j);
                $known_pairs = ruhrpott_guru_food_known_rename_pairs();
                if ($norm_j !== '' && $norm_i !== '' && isset($known_pairs[$norm_j]) && $known_pairs[$norm_j] === $norm_i) {
                    $remove[$j] = 'renamed-old-record';
                    $meta['excluded']['renamed-old-record']++;
                    $meta['renames'][] = array('current' => $name_i, 'previous' => $name_j, 'evidence' => 'known-local-rename');
                    if (!isset($candidates[$i]['tags']) || !is_array($candidates[$i]['tags'])) $candidates[$i]['tags'] = array();
                    $existing_aliases = ruhrpott_guru_food_aliases($candidates[$i]['tags']);
                    $existing_aliases[] = $name_j;
                    $candidates[$i]['tags']['rg:aliases'] = implode(';', array_values(array_unique($existing_aliases)));
                    continue;
                }
                if ($norm_j !== '' && isset($alias_norm_i[$norm_j])) {
                    $remove[$j] = 'renamed-old-record';
                    $meta['excluded']['renamed-old-record']++;
                    $meta['renames'][] = array('current' => $name_i, 'previous' => $name_j);
                    if (!isset($candidates[$i]['tags']) || !is_array($candidates[$i]['tags'])) $candidates[$i]['tags'] = array();
                    $candidates[$i]['tags']['rg:aliases'] = implode(';', $aliases_i);
                }
            }
        }

        $identity = array();
        foreach ($candidates as $idx => $element) {
            if (!isset($remove[$idx])) $identity[] = $element;
        }

        // Stage 3: exact-name duplicate collapse, but NEVER merge two different
        // active names merely because they share an address.
        $kept = array();
        foreach ($identity as $element) {
            $tags = isset($element['tags']) && is_array($element['tags']) ? $element['tags'] : array();
            $name_norm = ruhrpott_guru_food_normalize_text(ruhrpott_guru_food_display_name($tags));
            $merged = false;
            foreach ($kept as $k => $existing) {
                $existing_tags = isset($existing['tags']) && is_array($existing['tags']) ? $existing['tags'] : array();
                $existing_name_norm = ruhrpott_guru_food_normalize_text(ruhrpott_guru_food_display_name($existing_tags));
                if ($name_norm !== '' && $name_norm === $existing_name_norm && ruhrpott_guru_food_same_place($element, $existing)) {
                    if (ruhrpott_guru_food_element_score($element) > ruhrpott_guru_food_element_score($existing)) {
                        $kept[$k] = $element;
                    }
                    $meta['excluded']['duplicate']++;
                    $merged = true;
                    break;
                }
            }
            if (!$merged) $kept[] = $element;
        }

        $data['elements'] = array_values($kept);
        $meta['visibleCount'] = count($data['elements']);
        $meta['mode'] = 'filtered';
        return array('data' => $data, 'meta' => $meta);
    }
}
