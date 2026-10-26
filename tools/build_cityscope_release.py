from pathlib import Path
import hashlib,json,zipfile
root=Path('wordpress/plugins/ruhrpott-guru-places')
p=root/'ruhrpott-guru-places.php';src=p.read_text()
a=src.index('        // 1.10.190: reuse the established city-scope contract')
b=src.index('        usort($cluster_input',a)
baseline=(src[:a]+Path('tests/fixtures/city-scope-original-189.php.txt').read_text()+src[b:]).replace("$raw['rpg_geo_label']=(string)($row['city']??'');","$raw['rpg_geo_label']=(string)($row['city']?:'Wesel');",1).replace('Version: 1.10.190','Version: 1.10.189',1).replace("const VERSION = '1.10.190'","const VERSION = '1.10.189'",1).encode()
assert hashlib.sha256(baseline).hexdigest()=='c5351681b2a5a0006219db99df995f1201b764075a6b9531ecfbb205a47bd9b1'
out=Path('cityscope-release');out.mkdir(exist_ok=True)
manifest={'version':'1.10.190','installed':False,'baseline_main_sha256':hashlib.sha256(baseline).hexdigest(),'baseline':'authenticated full live v189 export; SEO and import fixes preserved','rollback':'Restore supplied v189 ZIP. No schema, data, options, source registrations or worker migrations added.','files':{}}
for name,rollback in [('PottFuchs-Places-v1.10.190-Stadtsichtbarkeit.zip',False),('PottFuchs-Places-v1.10.189-Rollback.zip',True)]:
    f=out/name
    with zipfile.ZipFile(f,'w',zipfile.ZIP_DEFLATED) as z:
        for file in sorted(root.rglob('*')):
            if file.is_file():
                info=zipfile.ZipInfo(str(Path('ruhrpott-guru-places')/file.relative_to(root)),(2026,10,26,0,0,0));info.compress_type=zipfile.ZIP_DEFLATED;info.external_attr=0o100644<<16
                z.writestr(info,baseline if rollback and file==p else file.read_bytes())
    with zipfile.ZipFile(f) as z:
        assert z.testzip() is None
        assert z.read('ruhrpott-guru-places/ruhrpott-guru-places.php')==(baseline if rollback else p.read_bytes())
        assert all(n.startswith('ruhrpott-guru-places/') and '..' not in n for n in z.namelist())
    manifest['files'][name]={'sha256':hashlib.sha256(f.read_bytes()).hexdigest(),'bytes':f.stat().st_size}
(out/'release-manifest.json').write_text(json.dumps(manifest,ensure_ascii=False,indent=2)+'\n')
(out/'SHA256SUMS.txt').write_text(''.join(v['sha256']+'  '+k+'\n' for k,v in manifest['files'].items()))
print(json.dumps(manifest))
