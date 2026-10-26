#!/usr/bin/env python3
"""One-time scoped safety correction for archival SEO candidate. NEVER deploys WordPress."""
import pathlib, subprocess, hashlib
path=pathlib.Path('wordpress/plugins/ruhrpott-guru-places/ruhrpott-guru-places.php')
raw=path.read_bytes()
assert b' * Version: 1.10.169' in raw[:1024], 'Only historical archived version allowed'
sha=hashlib.sha1(b'blob '+str(len(raw)).encode()+b'\0'+raw).hexdigest()
assert sha=='11a94086d01e0a43038a57c477f297437b0dc309', f'Unexpected input SHA {sha}'
s=raw.decode('utf-8')
a=s.index('    /** PottFuchs: SEO title gains locality;')
b=s.index('    private function seo_render_detail_html_v101096',a)
assert s[a:b].count('private function seo_place_title_with_city_v101170')==1
new='''    /** Preserve every place title except the explicitly approved URL /ort/16936/. */
    private function seo_place_title_16936_v101170($place_id,$name) {
        $name=trim((string)$name);
        return (int)$place_id===16936 ? 'Zum Schwarzen Essen' : $name;
    }

'''
s=s[:a]+new+s[b:]
o="'seo_title'=>$this->seo_place_title_with_city_v101170($title,$city)"
n="'seo_title'=>$this->seo_place_title_16936_v101170((int)$row['id'],$title)"
assert s.count(o)==1
s=s.replace(o,n)
assert s.count("esc_html($seo_title.' | '.get_bloginfo('name'))")==3
assert s.count("'@type'=>'Place','name'=>$title,'url'=>$canonical")==1
assert "ruhrpottguru/v1" in s
path.write_text(s,encoding='utf-8')
subprocess.run(['php','-l',str(path)],check=True)
print('Archive-only targeted 16936 correction applied, PHP lint passed, no live write')
