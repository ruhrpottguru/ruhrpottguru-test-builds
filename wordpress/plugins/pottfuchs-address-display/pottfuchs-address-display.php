<?php
/**
 * Plugin Name: PottFuchs – Koordinaten zu Adresse
 * Description: Ergänzt fehlende Ortsadressen aus verlässlichen Koordinaten über eine kontrollierte, zwischengespeicherte Geoapify-Rückwärtsabfrage.
 * Version: 1.1.0
 * Requires PHP: 8.0
 * License: GPL-2.0-or-later
 * Text Domain: pottfuchs-address-display
 */
if (!defined('ABSPATH')) { exit; }
final class PottFuchs_Address_Display {
    private const OPT = 'pottfuchs_address_display_settings';
    private const COUNT = 'pottfuchs_address_display_daily';
    private const CACHE = 'pottfuchs_addr_';
    private const MAX_PER_DAY = 75;
    private const MAX_NEW_PER_RESPONSE = 2;
    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_init', [__CLASS__, 'settings']);
        add_action('rest_api_init', [__CLASS__, 'rest']);
        add_filter('rest_post_dispatch', [__CLASS__, 'enrich_response'], 99, 3);
    }
    private static function config(): array {
        $x = get_option(self::OPT, []);
        $x = is_array($x) ? $x : [];
        // Use the EXISTING PottFuchs Geoapify key; never require a parallel subscription.
        if (empty($x['key'])) $x['key'] = trim((string)get_option('rpg_geoapify_api_key', ''));
        // An installed plugin must explicitly be enabled in settings before sending requests.
        return $x;
    }
    public static function settings(): void {
        register_setting('pottfuchs_address_display', self::OPT, [
            'type'=>'object', 'sanitize_callback'=>[__CLASS__, 'sanitize'], 'default'=>[]
        ]);
    }
    public static function sanitize($x): array {
        $x=is_array($x)?$x:[]; $old=self::config();
        $key=trim((string)($x['key']??''));
        return ['enabled'=>!empty($x['enabled'])?1:0,
            'key'=>$key!==''?sanitize_text_field($key):(string)($old['key']??'')];
    }
    public static function menu(): void {
        add_options_page('PottFuchs Adressauflösung', 'PottFuchs Adressauflösung', 'manage_options', 'pottfuchs-address-display', [__CLASS__, 'page']);
    }
    public static function page(): void {
        if (!current_user_can('manage_options')) return;
        $x=self::config();
        echo '<div class="wrap"><h1>PottFuchs – Koordinaten zu Adresse</h1><p>Optionale Rückwärts-Geokodierung mit dem bestehenden PottFuchs-Geoapify-Schlüssel, sofern vorhanden. Es erfolgen keine externen Aufrufe, solange die Funktion deaktiviert ist. API-Kontingent prüfen, bevor sie aktiviert wird. Für Datenschutz und Quellenhinweis Geoapify-Bedingungen beachten.</p>';
        echo '<form action="options.php" method="post">'; settings_fields('pottfuchs_address_display');
        echo '<table class="form-table"><tr><th>Aktivieren</th><td><input type="checkbox" name="'.esc_attr(self::OPT).'[enabled]" value="1" '.checked(!empty($x['enabled']),true,false).' /></td></tr>';
        echo '<tr><th>Geoapify API-Key (optional; vorhandener Theme-Schlüssel wird verwendet)</th><td><input type="password" autocomplete="new-password" class="regular-text" name="'.esc_attr(self::OPT).'[key]" value="" placeholder="Leer lassen, um bestehenden Schlüssel beizubehalten" /><p class="description">Nur einen bereits freigegebenen Key verwenden. Maximal '.esc_html((string)self::MAX_PER_DAY).' externe Abfragen täglich; Ergebnisse 180 Tage im Cache.</p></td></tr></table>';
        submit_button(); echo '</form><p>Admin-Test-Endpunkt: <code>/wp-json/pottfuchs/v1/reverse-address?lat=51.5&amp;lng=7.4</code>. Liefert nur bei hinreichend genauer Adresse ein Ergebnis, sonst einen neutralen Status. Die Routenlogik bleibt unverändert.</p></div>';
    }

    /** Add only verified full event/place addresses to the existing API payload.
     * Never add coordinates, modify visibility, event lifecycle, source data, or ranking.
     * Work is bounded: at most two uncached external requests per REST response.
     */
    public static function enrich_response($response, $server, $request) {
        if (!($request instanceof WP_REST_Request) || !($response instanceof WP_REST_Response)) return $response;
        $route = (string)$request->get_route();
        if (!preg_match('~^/ruhrpottguru/v1/(events|global-search|places/(home|industrial-route|buedchen|night|wurst))(?:/|$)~', $route)) return $response;
        $cfg = self::config();
        if (empty($cfg['enabled']) || empty($cfg['key'])) return $response;
        $data = $response->get_data();
        if (!is_array($data) || empty($data['items']) || !is_array($data['items'])) return $response;
        $uncached = 0;
        foreach ($data['items'] as &$item) {
            if (!is_array($item) || self::has_full_address($item)) continue;
            // A city-scope event does not represent a confirmed physical point.
            if (!empty($item['cityScope']) || !empty($item['city_scope']) ||
                (!empty($item['rpg_event_geo_scope']) && $item['rpg_event_geo_scope'] !== 'precise')) continue;
            $point = self::item_coords($item);
            if (!$point) continue;
            $key = self::CACHE.md5(implode('|', array_map(static fn($v) => (string)round($v, 5), $point)));
            $cached = get_transient($key);
            if ($cached === false && $uncached >= self::MAX_NEW_PER_RESPONSE) continue;
            if ($cached === false) ++$uncached;
            // Resolve by reusing the same quota/cache implementation as the public route.
            $req = new WP_REST_Request('GET', '/pottfuchs/v1/reverse-address');
            $req->set_param('lat', $point[0]); $req->set_param('lng', $point[1]);
            $result = self::resolve($req);
            $value = $result instanceof WP_REST_Response ? $result->get_data() : (is_array($result) ? $result : null);
            if (!is_array($value) || ($value['status'] ?? '') !== 'ok' || empty($value['address']['formatted'])) continue;
            $address = $value['address'];
            // Only supplement empty or city-only summaries; never overwrite a venue address.
            if (!empty($item['address']) && !self::is_city_only_address((string)$item['address'], (string)($item['city']??''))) continue;
            $item['address'] = $address['formatted'];
            // Native and web eventAddress() rank structured venue addresses above flat strings.
            // Response-only enrichment: do not modify persistent source/DB fields.
            if (empty($item['location']) || is_array($item['location'])) {
                if (empty($item['location']) || empty($item['location']['address'])) {
                    $item['location'] = is_array($item['location'] ?? null) ? $item['location'] : [];
                    $item['location']['address'] = [
                        'street' => $address['street'], 'houseNumber' => $address['house_number'],
                        'postalCode' => $address['postcode'], 'city' => $address['city'],
                        'rel' => 'venue',
                    ];
                }
            }
            $item['pottfuchs_resolved_address'] = [
                'formatted' => $address['formatted'], 'source' => 'Geoapify',
                'approximate' => true, 'street' => $address['street'],
                'house_number' => $address['house_number'], 'postcode' => $address['postcode'], 'city' => $address['city']
            ];
        }
        unset($item);
        $response->set_data($data);
        return $response;
    }
    private static function is_city_only_address(string $value, string $city): bool {
        $value = trim($value);
        if ($value === '') return true;
        if ($city !== '' && strtolower($value) === strtolower(trim($city))) return true;
        // City-only fields are permitted, but not a venue, street or partial address.
        return (bool) preg_match('/^\d{5}\s+[\p{L}\-\s]+$/u', $value);
    }
    private static function has_full_address(array $item): bool {
        $values = [$item['address'] ?? null, $item['location']['address'] ?? null];
        // Protect structured event/venue addresses even when the flat summary is city-only.
        foreach (['addresses', 'eventAddress', 'venueAddress'] as $field) {
            $v = $item[$field] ?? null;
            if (is_array($v)) {
                foreach ($v as $k => $row) {
                    if (is_array($row) && isset($row['rel']) && preg_match('/organizer|contact|provider/i', (string)$row['rel'])) continue;
                    $values[] = $row;
                }
            } elseif ($v !== null) $values[] = $v;
        }
        foreach ($values as $a) {
            if (is_array($a)) {
                $street = trim((string)($a['street'] ?? $a['streetAddress'] ?? ''));
                $house = trim((string)($a['house_number'] ?? $a['housenumber'] ?? $a['houseNumber'] ?? ''));
                $zip = trim((string)($a['postcode'] ?? $a['zip'] ?? $a['postalCode'] ?? ''));
                if ($street !== '' && $house !== '' && preg_match('/^[0-9]{5}$/', $zip)) return true;
            }
            if (is_string($a) && preg_match('/\b[0-9]{5}\b/u', $a) && preg_match('/\b[0-9]+[a-z]?\s*[, ]\s*[0-9]{5}\b/iu', $a)) return true;
        }
        return false;
    }
    private static function item_coords(array $item): ?array {
        foreach ([$item, $item['coords'] ?? null, $item['geo'] ?? null, $item['location'] ?? null] as $a) {
            if (!is_array($a)) continue;
            if (isset($a['main']) && is_array($a['main'])) $a = $a['main'];
            $lat = $a['lat'] ?? $a['latitude'] ?? null;
            $lng = $a['lng'] ?? $a['lon'] ?? $a['longitude'] ?? null;
            if (!is_numeric($lat) || !is_numeric($lng)) continue;
            $lat = (float)$lat; $lng = (float)$lng;
            if (!is_finite($lat) || !is_finite($lng) || abs($lat) > 90 || abs($lng) > 180 || ($lat == 0.0 && $lng == 0.0)) continue;
            return [$lat, $lng];
        }
        return null;
    }

    public static function rest(): void {
        register_rest_route('pottfuchs/v1','/reverse-address', [
            'methods'=>'GET', 'callback'=>[__CLASS__, 'resolve'],
            'permission_callback'=>static fn() => current_user_can('manage_options'),
            'args'=>[
                'lat'=>['required'=>true,'type'=>'number','validate_callback'=>static function($v){return is_numeric($v)&&(float)$v>=-90&&(float)$v<=90;}],
                'lng'=>['required'=>true,'type'=>'number','validate_callback'=>static function($v){return is_numeric($v)&&(float)$v>=-180&&(float)$v<=180;}]
            ]
        ]);
    }
    public static function resolve($req) {
        $x=self::config();
        if (empty($x['enabled']) || empty($x['key'])) return new WP_REST_Response(['status'=>'disabled','address'=>null], 200);
        $lat=(float)$req->get_param('lat');$lng=(float)$req->get_param('lng');
        if (!is_finite($lat)||!is_finite($lng)||abs($lat)<0.00001&&abs($lng)<0.00001) return new WP_Error('pottfuchs_invalid_coords','Ungültiger Zielpunkt',['status'=>400]);
        // A 5-decimal-degree grid limits accidental coordinate disclosure and cache cardinality.
        $lat=round($lat,5);$lng=round($lng,5);
        $cache=self::CACHE.md5($lat.'|'.$lng);
        $cached=get_transient($cache);
        if (is_array($cached)) return rest_ensure_response($cached);
        $today=gmdate('Y-m-d'); $counter=get_option(self::COUNT,[]);
        if (!is_array($counter)||($counter['day']??'')!==$today) $counter=['day'=>$today,'used'=>0];
        if ((int)$counter['used']>=self::MAX_PER_DAY) return rest_ensure_response(['status'=>'quota','address'=>null]);
        // Reserve a quota slot before making the request to avoid unbounded parallel bursts.
        $counter['used']=(int)$counter['used']+1;update_option(self::COUNT,$counter,false);
        $url=add_query_arg(['lat'=>$lat,'lon'=>$lng,'format'=>'json','apiKey'=>(string)$x['key'],'lang'=>'de'], 'https://api-eu.geoapify.com/v1/geocode/reverse');
        $result=wp_remote_get($url,['timeout'=>6,'redirection'=>0,'headers'=>['Accept'=>'application/json']]);
        if (is_wp_error($result)||wp_remote_retrieve_response_code($result)!==200) return rest_ensure_response(['status'=>'unavailable','address'=>null]);
        $json=json_decode(wp_remote_retrieve_body($result),true); $p=$json['results'][0]??null;
        if (!is_array($p)) { $out=['status'=>'not_found','address'=>null];set_transient($cache,$out,DAY_IN_SECONDS);return rest_ensure_response($out); }
        $street=trim((string)($p['street']??''));$house=trim((string)($p['housenumber']??''));
        $zip=trim((string)($p['postcode']??''));$city=trim((string)($p['city']??($p['town']??($p['village']??''))));
        $type=(string)($p['result_type']??'');
        $confidence=(float)($p['rank']['confidence']??0);
        $distance=isset($p['distance']) ? (float)$p['distance'] : null;
        // Never invent a precise postal address from a road, city or postcode centroid.
        if ($street===''||$house===''||$zip===''||$city===''||$confidence<0.7||($distance !== null && $distance > 60)||!in_array($type,['building','amenity'],true)) {
            $out=['status'=>'imprecise','address'=>null];set_transient($cache,$out,DAY_IN_SECONDS);return rest_ensure_response($out);
        }
        $out=['status'=>'ok','address'=>['street'=>$street,'house_number'=>$house,'postcode'=>$zip,'city'=>$city,'formatted'=>trim($street.' '.$house).', '.$zip.' '.$city],'source'=>'Geoapify','approximate'=>true];
        set_transient($cache,$out,180*DAY_IN_SECONDS);
        return rest_ensure_response($out);
    }
}
PottFuchs_Address_Display::init();
