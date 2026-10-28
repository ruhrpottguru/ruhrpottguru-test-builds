<?php
define('RPG_SPECIAL_SSR_TEST', true);
date_default_timezone_set('Europe/Berlin');
require __DIR__ . '/../inc/special-ssr.php';
$cases = json_decode(file_get_contents($argv[1]), true);
$now = new DateTimeImmutable($argv[2], new DateTimeZone('Europe/Berlin'));
$out = array();
foreach ($cases as $c) {
    $out[] = array('m' => rpg_special_martin_categories($c, $now), 'x' => rpg_special_is_christmas_market($c, $now));
}
echo json_encode($out) . "\n";
if (isset($argv[3])) {
    $rows = rpg_special_rows('martin', $cases, $now, 'https://www.pottfuchs.de/');
    echo rpg_special_html('martin', $rows) . "\n";
}
