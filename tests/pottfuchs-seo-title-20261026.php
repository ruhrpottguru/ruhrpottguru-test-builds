<?php
/**
 * Focused offline regression for the place SEO-title helper.
 * php tests/pottfuchs-seo-title-20261026.php
 * Runs isolated helper logic, not the whole WordPress plugin.
 */
function pf_place_title_with_city($name, $city) {
    $name=trim((string)$name);$city=trim((string)$city);
    if($city==='' || preg_match('/(?<![\\p{L}\\p{N}])'.preg_quote($city,'/').'(?![\\p{L}\\p{N}])/iu',$name))return $name;
    return $name.' '.$city;
}
$cases = [
  ['Zum Schwarzen','Essen','Zum Schwarzen Essen'],
  ['Zum Schwarzen Essen','Essen','Zum Schwarzen Essen'],
  ['Zum Schwarzen in Essen','Essen','Zum Schwarzen in Essen'],
  ['Zum Schwarzen','','Zum Schwarzen'],
  ['Bistro','Mülheim an der Ruhr','Bistro Mülheim an der Ruhr'],
  ['Bistro Mülheim an der Ruhr','Mülheim an der Ruhr','Bistro Mülheim an der Ruhr'],
  ['Oberhausen Pizza','Oberhausen','Oberhausen Pizza'],
  ['Essenstisch','Essen','Essenstisch Essen'],
  ['  Zum Schwarzen  ','  Essen  ','Zum Schwarzen Essen'],
  ['Kölner Hof','Köln','Kölner Hof Köln'],
  ['Café Düsseldorf','Düsseldorf','Café Düsseldorf'],
  ['Am Rhein','Düsseldorf','Am Rhein Düsseldorf'],
];
foreach ($cases as $number => [$name,$city,$expected]) {
    $actual = pf_place_title_with_city($name,$city);
    if ($actual!==$expected) {fwrite(STDERR,"FAIL case ".($number+1)."\n");exit(1);}
}
echo "PASS: 12 isolated SEO title cases (not a live WordPress test)\n";
