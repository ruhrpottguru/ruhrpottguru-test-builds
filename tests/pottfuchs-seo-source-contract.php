<?php
/** Offline source-contract checks; run from root of GitHub SEO development branch.
 * Only Place ID 16936 may receive an SEO title exception; never a site-wide city suffix.
 */
$root=dirname(__DIR__);
$backend=$root.'/wordpress/plugins/ruhrpott-guru-places/ruhrpott-guru-places.php';
$theme=$root.'/wordpress/themes/ruhrpott-guru/functions.php';
$header=$root.'/wordpress/themes/ruhrpott-guru/header.php';
function pf_verify($ok,$desc){echo ($ok?'PASS ':'FAIL ').$desc."\n";if(!$ok)exit(1);}
pf_verify(is_file($backend)&&is_file($theme)&&is_file($header),'Required source files exist');
$b=file_get_contents($backend);$t=file_get_contents($theme);$h=file_get_contents($header);
try{token_get_all($b,TOKEN_PARSE);token_get_all($t,TOKEN_PARSE);token_get_all($h,TOKEN_PARSE);}
catch(ParseError $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
pf_verify(str_contains($b,"'seo_title'=>\$this->seo_place_title_16936_v101170((int)\$row['id'],\$title)"),'Only approved Place 16936 title override');
pf_verify(!str_contains($b,'seo_place_title_with_city_v101170'),'No global city suffix helper');
pf_verify(str_contains($b,'static function () use ($title, $seo_title, $desc, $canonical, $image, $schema_json, $esc)'),'Head closure captures SEO title');
pf_verify(substr_count($b,"esc_html(\$seo_title.' | '.get_bloginfo('name'))")===3,'Three detail title paths');
pf_verify(str_contains($b,"'@type'=>'Place','name'=>\$title,'url'=>\$canonical"),'Entity schema name unchanged');
pf_verify(substr_count($b,'wp_safe_redirect($canonical,301)')>=2,'Canonical redirects retained');
pf_verify(str_contains($t,"remove_action('wp_head', '_wp_render_title_tag', 1)"),'Core duplicate title guard retained');
pf_verify(str_contains($h,'/favicon.svg'),'Existing favicon retained');
pf_verify(str_contains($b,'ruhrpottguru/v1'),'REST namespace retained');
echo "Success: static source checks, not live integration.\n";
