<?php
/**
 * V353 – Special-Seiten (Martin, Weihnachten) auch ohne JavaScript im Seitencode.
 *
 * Eine Quelle:
 *  - Texte (Einleitung, FAQ) stehen NUR hier und gehen per Einstellung an das JavaScript
 *    (RPG.settings.specialTexts); die Seite zeigt dieselben Texte.
 *  - Termine kommen aus derselben Eventquelle wie die Anzeige (ruhrpott_guru_event_rest),
 *    die Auswahlregeln sind eine 1:1-Übersetzung der Regeln in src/react-app/views.js
 *    (martinCategories, Weihnachtsmarkt-Regel). Ein Vergleichstest hält beide gleich
 *    (tests/v353-special-ssr-parity.cjs).
 *  - Die Liste wird im Hintergrund (WP-Cron) berechnet und zwischengespeichert; ein
 *    Seitenaufruf wartet nie auf die Eventquelle.
 */
if (!defined('ABSPATH') && !defined('RPG_SPECIAL_SSR_TEST')) exit;

function rpg_special_fold($value) {
    $s = is_array($value) ? implode(' ', array_filter($value, 'is_scalar')) : (string) $value;
    $s = function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
    $s = str_replace(array('ä', 'ö', 'ü', 'ß'), array('ae', 'oe', 'ue', 'ss'), $s);
    if (function_exists('remove_accents')) $s = remove_accents($s);
    else $s = strtr($s, array('é' => 'e', 'è' => 'e', 'ê' => 'e', 'á' => 'a', 'à' => 'a', 'â' => 'a', 'ó' => 'o', 'ô' => 'o', 'í' => 'i', 'ú' => 'u', 'ç' => 'c'));
    $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
    return trim((string) $s);
}

/** Startzeitpunkt als DateTimeImmutable in Berlin-Zeit (wie new Date() im Browser). */
function rpg_special_date($value) {
    if (!is_string($value) || $value === '') return null;
    try {
        $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('Europe/Berlin');
        return (new DateTimeImmutable($value))->setTimezone($tz);
    } catch (Exception $e) {
        return null;
    }
}

function rpg_special_taxonomy($item) {
    $parts = array();
    foreach (array('keywords', 'tags', 'categories', 'category', 'eventType') as $k) {
        if (isset($item[$k]) && is_string($item[$k])) $parts[] = $item[$k];
    }
    return rpg_special_fold(implode(' ', $parts));
}

function rpg_special_description($item) {
    $parts = array();
    foreach (array('description', 'shortDescription') as $k) {
        if (isset($item[$k]) && is_string($item[$k])) $parts[] = $item[$k];
    }
    if (isset($item['rpg_event_details']['shortDescription']) && is_string($item['rpg_event_details']['shortDescription'])) {
        $parts[] = $item['rpg_event_details']['shortDescription'];
    }
    return rpg_special_fold(implode(' ', $parts));
}

function rpg_special_season_year($now, $key) {
    $m = (int) $now->format('n');
    $y = (int) $now->format('Y');
    if ($key === 'martin') return $m === 12 ? $y + 1 : $y;
    return $m === 1 ? $y - 1 : $y; // Weihnachten
}

/** Rückgabe: Liste der Martin-Kategorien (umzuege, maerkte-kirmes, sonstiges). */
function rpg_special_martin_categories($item, $now) {
    $start = rpg_special_date(isset($item['start']) ? $item['start'] : '');
    if (!$start) return array();
    $year = rpg_special_season_year($now, 'martin');
    $tz = $start->getTimezone();
    if ($start < new DateTimeImmutable($year . '-10-01 00:00:00', $tz)) return array();
    if ($start > new DateTimeImmutable($year . '-11-30 23:59:59', $tz)) return array();
    $title = rpg_special_fold(isset($item['title']) ? $item['title'] : '');
    $taxonomy = rpg_special_taxonomy($item);
    $description = rpg_special_description($item);
    $keywords = rpg_special_fold(isset($item['keywords']) && is_string($item['keywords']) ? $item['keywords'] : '');
    $notMartin = '/(konzert|orgel|\bchor|lesung|kabarett|comedy|fuehrung|gottesdienst|ausstellung|\bkino|theater|luther)/';
    $marketTitle = preg_match('/\b(martinikirmes|martinimarkt|martinsmarkt|martinsfest|martinifest|martinisonntag)\b/', $title) === 1;
    $parade = '/\b(martinszug|martinsumzug|martinszuege|martinsumzuege|laternenumzug|laternenzug|laternenlauf|lampionumzug|martinsfeuer|martinssingen|martinsfeier)\b/';
    $saint = '/\b(st martin|st martins|sankt martin|martinus)\b/';
    $notTitle = preg_match($notMartin, $title) === 1;
    $paradeTitle = preg_match($parade, $title) === 1 || (preg_match($saint, $title) === 1 && !$notTitle);
    $paradeText = !$notTitle && preg_match($parade, $keywords . ' ' . $description) === 1;
    $generic = preg_match('/\b(martin(s|i)[a-z]*|laternen(fest|basteln)|martinsgans|martinsgaense)\b/', $title) === 1 && !$notTitle;
    $result = array();
    if ($paradeTitle || $paradeText) $result[] = 'umzuege';
    if ($marketTitle) $result[] = 'maerkte-kirmes';
    if (!$result && ($generic || ($taxonomy !== '' && preg_match($saint, $taxonomy) === 1 && !$notTitle))) $result[] = 'sonstiges';
    return $result;
}

/** true, wenn der Termin ein Weihnachtsmarkt im Sinne der Seitenregel ist. */
function rpg_special_is_christmas_market($item, $now) {
    $start = rpg_special_date(isset($item['start']) ? $item['start'] : '');
    if (!$start) return false;
    $year = rpg_special_season_year($now, 'weihnachten');
    $tz = $start->getTimezone();
    if ($start < new DateTimeImmutable($year . '-11-01 00:00:00', $tz)) return false;
    if ($start > new DateTimeImmutable(($year + 1) . '-01-06 23:59:59', $tz)) return false;
    $title = rpg_special_fold(isset($item['title']) ? $item['title'] : '');
    $taxonomy = rpg_special_taxonomy($item);
    $description = rpg_special_description($item);
    $text = $title . ' ' . $taxonomy . ' ' . $description;
    if (preg_match('/\b(weihnacht[a-z]*|advent[a-z]*|nikolaus[a-z]*|christkind[a-z]*|christmas|xmas|winterleuchten|winterzauber)\b/', $text) !== 1) return false;
    $market = '/\b(weihnachtsmarkt|weihnachtsmaerkte|adventsmarkt|adventsmaerkte|christkindlmarkt|christkindlesmarkt|nikolausmarkt|weihnachtsdorf)\b/';
    $other = '/\b(?:konzert|musik|chor|theater|musical|show|lesung|workshop|basteln|backen|(?:weihnachts|advents)(?:konzert|musik|theater|musical|show|maerchen))\b/';
    $otherTitle = preg_match($other, $title) === 1;
    return (preg_match($market, $title) === 1 && !$otherTitle) || (!$otherTitle && preg_match($market, $taxonomy) === 1);
}

/** Texte je Special. {lead} wird je nach Ort (PHP oder Browser) durch den Datensatz ersetzt. */
function rpg_special_texts($key) {
    $year = function_exists('wp_date') ? (int) wp_date('Y') : (int) date('Y');
    $now = new DateTimeImmutable('now');
    $texts = array(
        'martin' => array(
            'title' => 'Martinszüge im Ruhrgebiet: gut zu wissen',
            'intro' => 'Der Martinstag am 11. November erinnert an den heiligen Martin von Tours. Rund um den Tag ziehen Kinder mit Laternen durch die Straßen, vielerorts hinter einem Reiter als St. Martin und mit Musik. Dazu kommen Martinsfeuer, Martinsmärkte und die Martinikirmes.',
            'faqs' => array(
                array('q' => 'Wann sind die Martinszüge im Ruhrgebiet?', 'a' => 'Die meisten Martinszüge finden rund um den Martinstag am 11. November statt. {lead}'),
                array('q' => 'Was ist ein Martinszug?', 'a' => 'Beim Martinszug, auch Martinsumzug oder Laternenumzug genannt, ziehen Kinder mit Laternen durch die Straßen, oft mit einem Reiter als St. Martin, mit Musik und teils mit einem Martinsfeuer zum Abschluss.'),
                array('q' => 'Warum steht mein Martinszug nicht in der Liste?', 'a' => 'Viele Martinszüge organisieren Kindergärten, Schulen, Kirchengemeinden und Vereine. Sie stehen oft nur in den Terminkalendern der Städte und Gemeinden. Hier erscheinen die Termine aus den Veranstaltungsquellen, die wir nutzen dürfen. Neue Termine kommen automatisch dazu, sobald sie dort stehen. Frag für deinen Stadtteil am besten auch bei der Stadt oder der Gemeinde nach.'),
                array('q' => 'Findet der Martinszug auch bei schlechtem Wetter statt?', 'a' => 'Alle Angaben ohne Gewähr. Uhrzeit, Treffpunkt und Wegstrecke legt der jeweilige Veranstalter fest und kann sie kurzfristig ändern, zum Beispiel wegen des Wetters. Prüfe sie vor dem Besuch beim Veranstalter.'),
            ),
        ),
        'weihnachten' => array(
            'title' => 'Weihnachten im Ruhrgebiet: gut zu wissen',
            'intro' => 'Weihnachtsmärkte, Adventsveranstaltungen, Weihnachtskonzerte, Familienaktionen, Theater, Shows und Lichterwelten im Ruhrgebiet: Diese Seite zeigt die Termine zu Advent und Weihnachten ' . rpg_special_season_year($now, 'weihnachten') . ' in deiner Nähe.',
            'faqs' => array(
                array('q' => 'Wo finde ich Weihnachtsmärkte in meiner Nähe?', 'a' => 'Wähle oben „Weihnachtsmärkte" und stelle Standort und Umkreis ein. {lead}'),
                array('q' => 'Was gehört außer Weihnachtsmärkten dazu?', 'a' => 'Konzerte und Musik, Theater und Shows, Kinder- und Familienangebote, Advent-Workshops zum Mitmachen sowie Lichterwelten und weihnachtliche Ausflüge.'),
                array('q' => 'Sind alle Weihnachtsmärkte täglich geöffnet?', 'a' => 'Nein. Schließtage, unterschiedliche Öffnungszeiten und wetterbedingte Änderungen sind möglich. Maßgeblich sind die Angaben des jeweiligen Veranstalters.'),
                array('q' => 'Wie aktuell sind die Termine?', 'a' => 'Die Termine werden automatisch aus unseren Veranstaltungsquellen übernommen. Prüfe Zeiten und Orte vor dem Besuch beim Veranstalter.'),
            ),
        ),
    );
    return isset($texts[$key]) ? $texts[$key] : null;
}

function rpg_special_lead($key, $rows) {
    $n = count($rows);
    if ($key === 'martin') {
        if (!$n) return 'Aktuell liegen uns noch keine Martinstermine vor. Neue Termine erscheinen automatisch, sobald sie in unseren Veranstaltungsquellen stehen.';
        return 'Aktuell listen wir ' . $n . ' Martinstermine im Ruhrgebiet, vom ' . $rows[0]['date'] . ' bis zum ' . $rows[$n - 1]['date'] . '.';
    }
    if (!$n) return 'Aktuell liegen noch keine bestätigten Weihnachtsmärkte vor. Neue Termine erscheinen automatisch.';
    return 'Aktuell zeigen wir ' . $n . ($n === 1 ? ' Weihnachtsmarkt' : ' Weihnachtsmärkte') . ' im Ruhrgebiet. Der erste beginnt am ' . $rows[0]['date'] . (!empty($rows[0]['city']) ? ' in ' . $rows[0]['city'] : '') . '.';
}

function rpg_special_item_city($item) {
    foreach (array('city') as $k) if (!empty($item[$k]) && is_string($item[$k])) return trim($item[$k]);
    if (!empty($item['address']) && is_array($item['address'])) {
        foreach (array('city', 'addressLocality') as $k) if (!empty($item['address'][$k]) && is_string($item['address'][$k])) return trim($item['address'][$k]);
    }
    return '';
}

function rpg_special_item_url($item, $home) {
    if (!empty($item['rpg_event_details']['canonicalUrl']) && is_string($item['rpg_event_details']['canonicalUrl'])) {
        $u = $item['rpg_event_details']['canonicalUrl'];
        if (preg_match('#^https?://#i', $u)) return $u;
    }
    $id = isset($item['rpg_event_db']['eventId']) ? (int) $item['rpg_event_db']['eventId'] : 0;
    if ($id <= 0 && isset($item['id']) && preg_match('/^event:(\d+)$/i', (string) $item['id'], $m)) $id = (int) $m[1];
    if ($id <= 0) return '';
    $slug = rpg_special_fold(isset($item['title']) ? $item['title'] : 'tipp');
    $slug = trim(substr(str_replace(' ', '-', $slug), 0, 90), '-');
    return rtrim($home, '/') . '/teilen/event/' . $id . '/' . ($slug !== '' ? $slug : 'tipp') . '/';
}

/**
 * Aus rohen Eventdaten die Zeilen für ein Special bauen (rein, ohne WordPress testbar).
 * @return array[] Zeilen: id,title,city,date,time,url,start(ts),cat; nach Datum sortiert, ohne Doppelte.
 */
function rpg_special_rows($key, $items, $now, $home) {
    $seen = array();
    $horizon = $now->modify('-1 hour');
    foreach ($items as $item) {
        if (!is_array($item) || empty($item['title'])) continue;
        $start = rpg_special_date(isset($item['start']) ? $item['start'] : '');
        if (!$start) continue;
        $end = rpg_special_date(isset($item['end']) ? $item['end'] : '');
        if (($end ? $end : $start) < $horizon) continue;
        if ($key === 'martin') {
            $cats = rpg_special_martin_categories($item, $now);
            if (!$cats) continue;
        } else {
            if (!rpg_special_is_christmas_market($item, $now)) continue;
            $cats = array('weihnachtsmaerkte');
        }
        $city = rpg_special_item_city($item);
        $k = rpg_special_fold($item['title']) . '|' . rpg_special_fold($city);
        if (isset($seen[$k]) && $seen[$k]['ts'] <= $start->getTimestamp()) continue;
        // Mehrtägige Termine (z. B. Kirmes): Zeitraum statt einzelner Startzeit anzeigen.
        $endShown = $end;
        if ($endShown && $endShown->format('H:i:s') === '00:00:00') $endShown = $endShown->modify('-1 second');
        $multiDay = $endShown && $endShown->format('Y-m-d') !== $start->format('Y-m-d');
        $allDay = !empty($item['allDay']) && $item['allDay'] !== 'False' && $item['allDay'] !== 'false';
        $seen[$k] = array(
            'id' => isset($item['id']) ? (string) $item['id'] : '',
            'title' => trim((string) $item['title']),
            'city' => $city,
            'date' => $start->format('d.m.Y'),
            'time' => ($allDay || $multiDay) ? '' : $start->format('H:i'),
            'date_to' => $multiDay ? $endShown->format('d.m.Y') : '',
            'url' => rpg_special_item_url($item, $home),
            'ts' => $start->getTimestamp(),
            'cat' => $cats[0],
        );
    }
    $rows = array_values($seen);
    usort($rows, function ($a, $b) { return $a['ts'] <=> $b['ts']; });
    return $rows;
}

function rpg_special_html($key, $rows, $texts = null) {
    $texts = $texts ? $texts : rpg_special_texts($key);
    if (!$texts) return '';
    $esc = function ($s) { return function_exists('esc_html') ? esc_html($s) : htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
    $escUrl = function ($s) { return function_exists('esc_url') ? esc_url($s) : htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
    $lead = rpg_special_lead($key, $rows);
    $h1 = $key === 'martin' ? 'Martinszüge &amp; Martinsfeste im Pott' : 'Weihnachten im Pott';
    // Mit JavaScript ist der Block unsichtbar (die App ersetzt ihn); ohne JavaScript wird er angezeigt.
    $out = '<noscript><style>.rpgx-special-ssr{position:static!important;width:auto!important;height:auto!important;overflow:visible!important;padding:1rem 1.25rem;max-width:60rem;margin:0 auto;line-height:1.6}</style></noscript>'
        . '<section class="rpgx-special-ssr" data-rpg-special-ssr="' . $esc($key) . '"><h1>' . $h1 . '</h1><p>' . $esc($lead) . '</p>';
    if ($rows) {
        $out .= '<ul class="rpgx-special-ssr-list">';
        foreach ($rows as $r) {
            $label = $esc($r['title']);
            $link = $r['url'] ? '<a href="' . $escUrl($r['url']) . '">' . $label . '</a>' : $label;
            $out .= '<li>' . $esc(!empty($r['date_to']) ? $r['date'] . ' bis ' . $r['date_to'] : $r['date'] . ($r['time'] ? ', ' . $r['time'] . ' Uhr' : '')) . ': ' . $link . ($r['city'] ? ' (' . $esc($r['city']) . ')' : '') . '</li>';
        }
        $out .= '</ul>';
    }
    $out .= '<h2>' . $esc($texts['title']) . '</h2><p>' . $esc($texts['intro']) . '</p>';
    foreach ($texts['faqs'] as $f) {
        $out .= '<h3>' . $esc($f['q']) . '</h3><p>' . $esc(str_replace('{lead}', $lead, $f['a'])) . '</p>';
    }
    return $out . '</section>';
}

/* ------------------------- WordPress-Teil ------------------------- */
if (defined('ABSPATH')) {
    function rpg_special_cache_key($key) { return 'rpg_special_rows_v1_' . $key; }

    /** Läuft im Hintergrund: holt Events aus derselben Quelle wie die Anzeige und speichert die Zeilen. */
    function rpg_special_refresh($key) {
        if (!in_array($key, array('martin', 'weihnachten'), true) || !function_exists('ruhrpott_guru_event_rest')) return;
        $nowTz = new DateTimeImmutable('now', wp_timezone());
        $seasonEnd = $key === 'martin'
            ? new DateTimeImmutable(rpg_special_season_year($nowTz, 'martin') . '-11-30 23:59:59', wp_timezone())
            : new DateTimeImmutable((rpg_special_season_year($nowTz, 'weihnachten') + 1) . '-01-06 23:59:59', wp_timezone());
        $days = max(1, (int) ceil(($seasonEnd->getTimestamp() - $nowTz->getTimestamp()) / 86400) + 1);
        $call = function ($city, $km) use ($days) {
            $req = new WP_REST_Request('POST', '/ruhrpottguru/v1/events');
            $req->set_header('content-type', 'application/json');
            $req->set_body(wp_json_encode(array('lat' => 51.4556, 'lng' => 7.0116, 'city' => $city, 'maxRadiusKm' => $km, 'untilDays' => $days)));
            $res = ruhrpott_guru_event_rest($req);
            $data = $res instanceof WP_REST_Response ? $res->get_data() : $res;
            return (is_array($data) && !empty($data['items']) && is_array($data['items'])) ? $data['items'] : array();
        };
        try {
            $items = $call('Essen', 50);
            if ($key === 'martin' && function_exists('ruhrpott_guru_city_scope_cities')) {
                foreach (ruhrpott_guru_city_scope_cities() as $city) {
                    foreach ($call($city, 1) as $it) $items[] = $it;
                }
            }
            $rows = rpg_special_rows($key, $items, new DateTimeImmutable('now', wp_timezone()), home_url('/'));
            set_transient(rpg_special_cache_key($key), array('rows' => $rows, 'at' => time()), 6 * HOUR_IN_SECONDS);
        } catch (Throwable $e) {
            // Beim Fehler bleibt der alte Stand stehen; die Seite funktioniert wie bisher per JavaScript.
        }
    }
    add_action('rpg_special_refresh_event', 'rpg_special_refresh', 10, 1);

    function rpg_special_schedule($key) {
        if (!wp_next_scheduled('rpg_special_refresh_event', array($key))) {
            wp_schedule_single_event(time() + 5, 'rpg_special_refresh_event', array($key));
        }
    }

    /** HTML für den Seitencode; wartet nie auf die Eventquelle. */
    function rpg_special_initial_html($key) {
        $texts = rpg_special_texts($key);
        if (!$texts) return '';
        $cached = get_transient(rpg_special_cache_key($key));
        $fresh = is_array($cached) && !empty($cached['at']) && (time() - (int) $cached['at']) < 30 * MINUTE_IN_SECONDS;
        if (!$fresh) rpg_special_schedule($key);
        $rows = (is_array($cached) && isset($cached['rows']) && is_array($cached['rows'])) ? $cached['rows'] : array();
        return rpg_special_html($key, $rows, $texts);
    }
}
