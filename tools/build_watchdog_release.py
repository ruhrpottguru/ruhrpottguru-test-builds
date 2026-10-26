from pathlib import Path
import zipfile,hashlib,json
root=Path('wordpress/plugins/ruhrpott-guru-places');p=root/'ruhrpott-guru-places.php';src=p.read_text();a=src.index('    public function ensure_event_supplemental_worker()');b=src.index('    private function event_region_forensic_snapshot()',a)
original=(src[:a]+Path('tests/fixtures/watchdog-original-188.php.txt').read_text()+src[b:]).replace('Version: 1.10.189','Version: 1.10.188',1).replace("const VERSION = '1.10.189'","const VERSION = '1.10.188'",1).encode()
assert hashlib.sha256(original).hexdigest()=='e0fa17964c622011553d9e2dc40ab4ae6610080b0703764205dfe0f4415012a2'
out=Path('watchdog-release');out.mkdir(exist_ok=True);manifest={'version':'1.10.189','baseline':'installed 1.10.188 including current live SEO migration; 2026-10-26 authenticated source export','baseline_main_sha256':hashlib.sha256(original).hexdigest(),'installed':False,'rollback':'Restore supplied 1.10.188 package; SEO fixes preserved. No schema migration. Import data changes are not reversed.','files':{}}
for name,rollback in [('PottFuchs-Places-v1.10.189-Watchdog.zip',False),('PottFuchs-Places-v1.10.188-Rollback-mit-SEO.zip',True)]:
 f=out/name
 with zipfile.ZipFile(f,'w',zipfile.ZIP_DEFLATED) as z:
  for file in sorted(root.rglob('*')):
   if file.is_file():
    info=zipfile.ZipInfo(str(Path('ruhrpott-guru-places')/file.relative_to(root)),(2026,10,26,0,0,0));info.compress_type=zipfile.ZIP_DEFLATED;info.external_attr=0o100644<<16;z.writestr(info,original if rollback and file==p else file.read_bytes())
 with zipfile.ZipFile(f) as z:
  assert z.testzip() is None
  assert z.read('ruhrpott-guru-places/ruhrpott-guru-places.php')==(original if rollback else p.read_bytes())
 manifest['files'][name]={'sha256':hashlib.sha256(f.read_bytes()).hexdigest(),'bytes':f.stat().st_size}
(out/'release-manifest.json').write_text(json.dumps(manifest,ensure_ascii=False,indent=2)+'\n');(out/'SHA256SUMS.txt').write_text(''.join(v['sha256']+'  '+k+'\n' for k,v in manifest['files'].items()));print(json.dumps(manifest))
