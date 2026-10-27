<?php
// Ohne WordPress lauffaehig: php tests/event_prices_visitor_unit.php
// Prueft event_prices_visitor_v101193 (Places 1.10.193).
$src = file_get_contents(__DIR__ . '/../wordpress/plugins/ruhrpott-guru-places/ruhrpott-guru-places.php');
if (!preg_match('/    private function event_prices_visitor_v101193\(.*?\n    }\n/s', $src, $m)) { fwrite(STDERR, "Funktion nicht gefunden\n"); exit(1); }
eval('class T { ' . $m[0] . ' public function run($p){ return $this->event_prices_visitor_v101193($p); } }');
$t = new T();
$fail = 0;
$check = function ($name, $got, $want) use (&$fail) {
    $ok = ($got === $want);
    echo ($ok ? 'OK    ' : 'FEHLER') . ' ' . $name . ($ok ? '' : "\n  erhalten: " . json_encode($got, JSON_UNESCAPED_UNICODE) . "\n  erwartet: " . json_encode($want, JSON_UNESCAPED_UNICODE)) . "\n";
    if (!$ok) $fail++;
};
// 1) echter Fall BOSY 19097
$p = json_decode('{"text":"Normalpreis 22 Euro, es gibt verschiedene Ermäßigungsarten.","items":[{"label":"PRICE_ADULT","value":"22","kind":"number"}],"numbers":[{"type":"PRICE_ADULT","value":"22"}],"attributes":[]}', true);
$r = $t->run($p);
$check('BOSY: Label', $r['items'][0]['label'], 'Erwachsene');
$check('BOSY: Wert mit Euro-Zeichen', $r['items'][0]['value'], '22 €');
$check('BOSY: Text unveraendert', $r['text'], $p['text']);
$check('BOSY: numbers unveraendert', $r['numbers'], $p['numbers']);
// 2) Text ohne Waehrung -> keine erfundene Waehrung
$r = $t->run(array('text' => 'Eintritt 22', 'items' => array(array('label' => 'PRICE_ADULT', 'value' => '22'))));
$check('ohne Waehrung im Text: Wert bleibt', $r['items'][0]['value'], '22');
$check('ohne Waehrung im Text: Label uebersetzt', $r['items'][0]['label'], 'Erwachsene');
// 3) mehrere Zeilen, Komma-Cent, bereits fertiger Wert
$r = $t->run(array('text' => 'Preise in EUR', 'items' => array(
    array('label' => 'PRICE_REDUCED', 'value' => '15,50'),
    array('label' => 'PRICE_CHILD', 'value' => '8 EUR'),
    array('label' => 'PRICE_VIP', 'value' => '99'),
    array('label' => 'Abendkasse', 'value' => '25'),
)));
$check('ermaessigt mit Cent', array($r['items'][0]['label'], $r['items'][0]['value']), array('Ermäßigt', '15,50 €'));
$check('Wert mit Einheit bleibt', array($r['items'][1]['label'], $r['items'][1]['value']), array('Kinder', '8 EUR'));
$check('unbekannter Code unveraendert', array($r['items'][2]['label'], $r['items'][2]['value']), array('PRICE_VIP', '99'));
$check('normales Label unveraendert', array($r['items'][3]['label'], $r['items'][3]['value']), array('Abendkasse', '25'));
// 4) Randfaelle
$check('leer', $t->run(array()), array());
$check('keine items', $t->run(array('text' => 'x')), array('text' => 'x'));
$check('kein Array', $t->run(null), null);
echo $fail ? "$fail Fehler\n" : "alle Pruefungen bestanden\n";
exit($fail ? 1 : 0);
