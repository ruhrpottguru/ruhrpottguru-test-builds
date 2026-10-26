<?php
/**
 * Isolated P0 test for the ONLY approved Place-ID title exception.
 * Does not claim to test installed WordPress or the 1.10.169 plugin end-to-end.
 */
function pottfuchs_place_title_16936($id, $name) {
    $name = trim((string)$name);
    return (int)$id === 16936 ? 'Zum Schwarzen Essen' : $name;
}
$cases = [
    [16936,'Zum Schwarzen','Zum Schwarzen Essen'],
    ['16936','Zum Schwarzen','Zum Schwarzen Essen'],
    [16936,'Something else','Zum Schwarzen Essen'],
    [10523,'Weseler Wirtshaus','Weseler Wirtshaus'],
    [10523,'Weseler Wirtshaus Wesel','Weseler Wirtshaus Wesel'],
    [16937,'Zum Schwarzen','Zum Schwarzen'],
    [0,'Zum Schwarzen','Zum Schwarzen'],
    [-1,'Test','Test'],
    [17000,'Bistro','Bistro'],
    [18900,'Café Düsseldorf','Café Düsseldorf'],
    [16888,'Bistro Mülheim','Bistro Mülheim'],
    [16936,'  Zum Schwarzen  ','Zum Schwarzen Essen'],
];
foreach ($cases as $n => [$id,$name,$expected]) {
    $actual=pottfuchs_place_title_16936($id,$name);
    if ($actual!==$expected) {fwrite(STDERR,"FAIL ".($n+1)."\n");exit(1);}
}
echo "PASS: 12 isolated title cases, no unintended global city suffix\n";
