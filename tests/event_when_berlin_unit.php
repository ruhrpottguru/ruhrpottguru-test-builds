<?php
// Ohne WordPress lauffaehig: php tests/event_when_berlin_unit.php
// Prueft event_when_berlin_v101192 (Places 1.10.192): UTC in der Datenbank -> deutsche Zeit.
date_default_timezone_set('UTC');
$monate = array(1=>'Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember');
function wp_date($format, $ts, $tz = null) {
    global $monate;
    $d = (new DateTimeImmutable('@' . (int)$ts))->setTimezone($tz ?: new DateTimeZone('UTC'));
    return $d->format('j') . '. ' . $monate[(int)$d->format('n')] . ' ' . $d->format('Y') . ' · ' . $d->format('H:i');
}
$src = file_get_contents(__DIR__ . '/../wordpress/plugins/ruhrpott-guru-places/ruhrpott-guru-places.php');
if (!preg_match('/    private function event_when_berlin_v101192\(.*?\n    }\n/s', $src, $m)) { fwrite(STDERR, "Funktion nicht gefunden\n"); exit(1); }
eval('class T { ' . $m[0] . ' public function run($v,$r=array()){ return $this->event_when_berlin_v101192($v,$r); } }');
$t = new T();
$cases = array(
    array('BOSY 19097 (Winterzeit)', '2026-11-06 19:30:00', array(), '6. November 2026 · 20:30'),
    array('Kummerschalen 28.10. (Winterzeit)', '2026-10-28 10:00:00', array(), '28. Oktober 2026 · 11:00'),
    array('Kummerschalen 20.10. (Sommerzeit)', '2026-10-20 09:00:00', array(), '20. Oktober 2026 · 11:00'),
    array('Sommer', '2026-07-01 12:00:00', array(), '1. Juli 2026 · 14:00'),
    array('Umstellung 25.10. nach 01:00 UTC', '2026-10-25 10:00:00', array(), '25. Oktober 2026 · 11:00'),
    array('Ganztag unveraendert', '2026-11-06 00:00:00', array('all_day' => 1), '6. November 2026 · 00:00'),
    array('eigene Zeitzone der Zeile', '2026-11-06 20:30:00', array('_rpg_visitor_timezone' => 'Europe/Berlin'), '6. November 2026 · 20:30'),
    array('leer', '', array(), ''),
    array('ungueltig', 'kein-datum', array(), ''),
    array('ungueltige Zeitzone', '2026-11-06 19:30:00', array('_rpg_visitor_timezone' => 'Nirgendwo/Xyz'), ''),
);
$fail = 0;
foreach ($cases as $c) {
    $got = $t->run($c[1], $c[2]);
    $ok = ($got === $c[3]);
    echo ($ok ? 'OK    ' : 'FEHLER') . ' ' . $c[0] . ' -> "' . $got . '"' . ($ok ? '' : ' (erwartet "' . $c[3] . '")') . "\n";
    if (!$ok) $fail++;
}
echo $fail ? "$fail Fehler\n" : count($cases) . " Pruefungen bestanden\n";
exit($fail ? 1 : 0);
