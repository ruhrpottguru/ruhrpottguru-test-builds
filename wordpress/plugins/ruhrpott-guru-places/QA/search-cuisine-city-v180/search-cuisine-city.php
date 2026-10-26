<?php
// Execute the real production methods. No WordPress boot, DB writes or network.
function remove_accents($s) { return strtr($s, ['ä'=>'a','ö'=>'o','ü'=>'u','Ä'=>'A','Ö'=>'O','Ü'=>'U','é'=>'e','ß'=>'ss']); }
function sanitize_title($s) {return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(remove_accents($s))), '-');}
function wp_json_encode($value) {return json_encode($value);}
class TestWpdb {function esc_like($s) { return addcslashes($s, '_%\\'); }}
$wpdb=new TestWpdb();
$file=$argv[1];$code=file_get_contents($file);$start=strpos($code,'final class RuhrpottGuru_Places_Backend {');$end=strpos($code,"require_once __DIR__ . '/includes/intelligence/class-rpg-iga-intelligence.php';",$start);if($start===false||$end===false)throw new Exception('Class boundaries missing');
eval(substr($code,$start,$end-$start));
$class=new ReflectionClass('RuhrpottGuru_Places_Backend');$backend=$class->newInstanceWithoutConstructor();$method=$class->getMethod('global_search_place_terms_sql');
$db=new Pdo\Sqlite('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->createFunction('regexp',function($pattern,$text) { $r=preg_match('~'.str_replace('~','\\~',$pattern).'~u',(string)$text);if($r===false)throw new Exception('Bad REGEXP: '.$pattern);return $r;},2);
$db->exec('CREATE TABLE places(id TEXT,name TEXT,city TEXT,district TEXT,street TEXT,primary_category TEXT,primary_subcategory TEXT,raw_tags TEXT,municipality_slug TEXT,active INTEGER,lifecycle_status TEXT)');
$fixture=json_decode(file_get_contents(__DIR__.'/live-fixtures.json'),true,512,JSON_THROW_ON_ERROR);$rows=[];
foreach($fixture['queries'] as $query)foreach($query['rows'] as $r)$rows[$r['id']]=$r;
$add=function($id,$name,$city,$tags,$category='food',$slug=null,$active=1,$status='active')use(&$rows){$rows[$id]=['id'=>$id,'name'=>$name,'city'=>$city,'raw_tags'=>json_encode($tags),'primary_category'=>$category,'primary_subcategory'=>'Restaurant','street'=>'','municipality_slug'=>$slug??sanitize_title($city),'active'=>$active,'lifecycle_status'=>$status];};
$add('test:greek','Athena','Wesel',['cuisine'=>'greek;regional']);
$add('test:japanese','Sakura','Essen',['cuisine'=>'sushi; japanese']);
$add('test:turkish','Anatolia','Dortmund',['cuisine'=>'turkish']);
$add('test:mulheim','Mio','Mülheim an der Ruhr',['cuisine'=>'italian']);
$add('test:wetter','Lago','Wetter (Ruhr)',['cuisine'=>'italian']);
$add('test:escaped','Other','Wesel',['website'=>'https://italian.example','cuisine'=>'italian_fusion']);
$add('test:wrongcat','Museum','Wesel',['cuisine'=>'italian'],'culture');
$add('test:inactive','Gone','Wesel',['cuisine'=>'italian'],'food',null,0);
$add('test:closed','Closed','Wesel',['cuisine'=>'italian'],'food',null,1,'closed');
$add('test:germantag','Lokanta','Dortmund',['cuisine'=>'türkisch']);
$add('test:sql','Literal %_ quote','Wesel',['cuisine'=>'greek']);
$add('test:seq','Cafe Bistro','Wesel',['cuisine'=>'italian; coffee; french']);
$add('test:compact','Cafe Compact','Wesel',['cuisine'=>'italian']);$rows['test:compact']['raw_tags']='{ "cuisine" : "italian" }';
$insert=$db->prepare('INSERT INTO places VALUES (?,?,?,?,?,?,?,?,?,?,?)');
foreach($rows as $r)$insert->execute([$r['id'],$r['name'],$r['city'],'',$r['street']??'',$r['primary_category'],$r['primary_subcategory'],$r['raw_tags'],$r['municipality_slug']??sanitize_title($r['city']),$r['active'],$r['lifecycle_status']]);
$find=function($q)use($db,$method,$backend){$spec=$method->invoke($backend,$q);if(substr_count($spec['sql'],'%s')!==count($spec['args']))throw new Exception('Placeholder count: '.$q);$sql=str_replace('LIKE %s',"LIKE %s ESCAPE '\\'",$spec['sql']);$sql=str_replace('%s','?',$sql);$st=$db->prepare("SELECT p.id,p.city FROM places p WHERE p.active=1 AND p.lifecycle_status='active' AND $sql ORDER BY p.id");$st->execute($spec['args']);return $st->fetchAll(PDO::FETCH_KEY_PAIR);};
$n=0;function check($ok,$label){global $n;if(!$ok)throw new Exception('FAIL '.$label);$n++;}
$italian=$find('Italienisch Wesel');check(count($italian)>=4,'real Wesel restaurants present');foreach(['place:10587','test:seq','test:compact'] as $id)check(isset($italian[$id]),'Italian matching '.$id);
check(count(array_filter($italian,fn($city)=>$city!=='Wesel'))===0,'explicit city excludes Weseler street in Haltern');
foreach(['test:escaped','test:wrongcat','test:inactive','test:closed'] as $id)check(!isset($italian[$id]),'admission/cuisine '.$id);
foreach(['italian Wesel','ITALIENISCH WESEL','Wesel italienisch','Italienisches Restaurant in Wesel'] as $q){$rs=$find($q);check(isset($rs['place:10587']),'variant '.$q);check(!in_array('Haltern am See',$rs,true),'no city leakage '.$q);}
check(isset($find('Griechisch Wesel')['test:greek']),'Greek source alias');check(isset($find('Türkisch Dortmund')['test:turkish']),'Turkish umlaut alias');check(isset($find('Türkisch Dortmund')['test:germantag']),'German cuisine token');check(isset($find('Japanisch Essen')['test:japanese']),'Japanese semicolon and city Essen');check(isset($find('Italienisch Mülheim an der Ruhr')['test:mulheim']),'multi-word city');check(isset($find('Italienisch Mülheim')['test:mulheim']),'short Mülheim');check(isset($find('italian Wetter (Ruhr)')['test:wetter']),'parenthesized city');check(count($find('italian Wesel Essen'))===0,'contradictory cities never relaxed');
check(isset($find('Top Golf Oberhausen')['place:8577']),'existing Top Golf term conjunction');check(isset($find('Topgolf Oberhausen')['place:8577']),'existing Topgolf canonical destination');check(count($find('zzqkeintreffer987'))===0,'zero results');check(count($find("x' OR 1=1 --"))===0,'prepared SQL input');check(count($find('%_'))===1,'literal SQL wildcard escaping');
// Existing non-cuisine requests must generate byte-identical clauses/arguments.
foreach(['Wesel','Topgolf Oberhausen','Top Golf Oberhausen','Zollverein','Skatepark','Pizzeria Wesel','Café','%_'] as $q){$spec=$method->invoke($backend,$q);$terms=preg_split('/\s+/u',trim($q),-1,PREG_SPLIT_NO_EMPTY);$cs=[];$args=[];foreach(array_unique($terms)as$t){$cs[]='(p.name LIKE %s OR p.city LIKE %s OR p.district LIKE %s OR p.street LIKE %s OR p.primary_subcategory LIKE %s OR p.raw_tags LIKE %s)';foreach(range(1,6)as$_)$args[]='%'.$wpdb->esc_like($t).'%';}check($spec===['sql'=>'('.implode(' AND ',$cs).')','args'=>$args],'unchanged generic finder '.$q);}
// All 53 existing municipal names, including multi-word and hyphen names.
$seed=$class->getMethod('municipalities_seed')->invoke($backend);
foreach($seed as $index=>$city){$id='city:'.$index;$insert->execute([$id,'Neutral Venue',$city[0],'','','food','Restaurant','{"cuisine":"italian"}',sanitize_title($city[0]),1,'active']);$rs=$find('Italienisch '.$city[0]);check(isset($rs[$id]),'municipality '.$city[0]);check(count(array_filter($rs,fn($value)=>$value!==$city[0]))===0,'city boundaries '.$city[0]);}
foreach([['Italienisch','italian'],['Deutsch','german'],['Griechisch','greek'],['Türkisch','turkish'],['Chinesisch','chinese'],['Asiatisch','asian'],['Japanisch','japanese'],['Indisch','indian'],['Mexikanisch','mexican'],['Vietnamesisch','vietnamese'],['Koreanisch','korean'],['Französisch','french'],['Spanisch','spanish'],['Mediterran','mediterranean'],['Thai','thai']] as $index=>$pair){$id='cuisine:'.$index;$insert->execute([$id,'Neutral Venue','Wesel','','','food','Restaurant',json_encode(['cuisine'=>$pair[1]]),'wesel',1,'active']);check(isset($find($pair[0].' Wesel')[$id]),'existing Food label '.$pair[0]);check(isset($find($pair[1].' Wesel')[$id]),'canonical Food token '.$pair[1]);}
echo json_encode(['status'=>'passed','assertions'=>$n,'engine'=>PHP_VERSION,'fixtureQueries'=>count($fixture['queries']),'fixtureRows'=>count($rows),'italianWeselIds'=>array_keys($italian),'scope'=>'production PHP SQL-builder and SQLite fixtures; no claim of live MySQL deployment'],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";
