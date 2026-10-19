<?php
// SQL regression tests against the helper extracted from the actual backend.
$source = file_get_contents(__DIR__.'/../wordpress/plugins/ruhrpott-guru-places/ruhrpott-guru-places.php');
$tokens = token_get_all($source); $method = ''; $found = false; $depth = 0; $started = false;
foreach ($tokens as $i => $token) {
    if (!$found && is_array($token) && $token[0] === T_FUNCTION) {
        $j = $i + 1;
        while (isset($tokens[$j]) && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) $j++;
        if (is_array($tokens[$j] ?? null) && $tokens[$j][1] === 'global_search_place_terms_sql') $found = true;
    }
    if (!$found) continue;
    $text = is_array($token) ? $token[1] : $token;
    $method .= $text;
    if ($token === '{') { $depth++; $started = true; }
    if ($token === '}' && $started && --$depth === 0) break;
}
if (!$method || !$started || $depth !== 0) throw new RuntimeException('Actual helper not found');
eval('class SearchUnderTest { public '.$method.' }');
$wpdb = new class { public function esc_like($s) { return addcslashes($s, '_%\\'); } };
$search = new SearchUnderTest();
$db = new PDO('sqlite::memory:'); $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE places (id INTEGER, name TEXT, city TEXT, district TEXT, street TEXT, primary_subcategory TEXT, raw_tags TEXT, active INTEGER, lifecycle_status TEXT, quality_score REAL)');
$insert = $db->prepare('INSERT INTO places VALUES (?,?,?,?,?,?,?,?,?,?)');
foreach ([
 [1,'Topgolf','Oberhausen','','Brammenring','Golf','{}',1,'active',.8],
 [2,'Topgolf','Essen','','','Golf','{}',1,'active',.7],
 [3,'Golfhaus','Oberhausen','','','Golf','{}',1,'active',.6],
 [4,'Topgolf','Oberhausen','','','Golf','{}',0,'active',.9],
 [5,'Topgolf','Oberhausen','','','Golf','{}',1,'closed',.9],
 [6,'100% Spaß','Essen','','','Freizeit','{}',1,'active',.5],
 [7,'1000 Spaß','Essen','','','Freizeit','{}',1,'active',.5],
 [8,'A_B','Essen','','','Freizeit','{}',1,'active',.5],
 [9,'AXB','Essen','','','Freizeit','{}',1,'active',.5],
 [10,"O'Brien",'Essen','','','Freizeit','{}',1,'active',.5],
 [11,'Sporttreff','Dortmund','Hörde','Teststraße','Klettern','{"brand":"Testmarke"}',1,'active',.5],
] as $row) $insert->execute($row);
$tests = [
 ['Topgolf',[1,2]],['Topgolf Oberhausen',[1]],['Top Golf Oberhausen',[1]],
 ['Oberhausen Topgolf',[1]],['  Topgolf   Oberhausen  ',[1]],["Topgolf\tOberhausen",[1]],
 ["Topgolf\nOberhausen",[1]],["Topgolf\u{00A0}Oberhausen",[1]],['topgolf oberhausen',[1]],
 ['Topgolf Essen',[2]],['Topgolf Dortmund',[]],['Topgolf Oberhausen Tennis',[]],
 ['Topgolf Topgolf Oberhausen',[1]],['Golf Oberhausen',[1,3]],['100%',[6]],['A_B',[8]],
 ["O'Brien Essen",[10]],["' OR 1=1 --",[]],['Sporttreff Hörde',[11]],
 ['Sporttreff Teststraße',[11]],['Sporttreff Klettern',[11]],['Sporttreff Testmarke',[11]],
 ['Brammenring Oberhausen',[1]],['Nonexistent',[]],['Top Golf Essen',[2]],
];
foreach ($tests as [$query,$expected]) {
    $spec = $search->global_search_place_terms_sql($query);
    if (substr_count($spec['sql'],'%s') !== count($spec['args'])) throw new RuntimeException('Placeholder mismatch');
    $predicate = str_replace('%s', "? ESCAPE '\\'", $spec['sql']);
    $stmt = $db->prepare("SELECT p.id FROM places p WHERE p.active=1 AND p.lifecycle_status='active' AND $predicate ORDER BY p.quality_score DESC,p.name ASC LIMIT 40");
    $stmt->execute($spec['args']); $actual = array_map('intval',$stmt->fetchAll(PDO::FETCH_COLUMN));
    if ($actual !== $expected) throw new RuntimeException($query.': '.json_encode($actual).' expected '.json_encode($expected));
}
echo count($tests)." SQL regression cases passed (actual PHP helper + SQLite; not WordPress/MySQL E2E).\n";
