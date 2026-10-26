from pathlib import Path
import hashlib,zipfile,json
root=Path('wordpress/plugins/ruhrpott-guru-places');out=Path('release-output');out.mkdir(exist_ok=True)
main=root/'ruhrpott-guru-places.php';src=main.read_text();start=src.index('    private function event_supplemental_short_refresh_pending_v101182(');end=src.index('    private function event_supplemental_yield_v101182(',start)
original=(src[:start]+Path('tests/fixtures/manual-priority-original.php.txt').read_text()+src[end:]).replace('Version: 1.10.188','Version: 1.10.185',1).replace("const VERSION = '1.10.188'","const VERSION = '1.10.185'",1).encode()
assert hashlib.sha256(original).hexdigest()=='8c4fb40bdcfe0ecb263d65e0ebd08d25e2713622dda56bacd6432a5c27ad3d55'
manifest={'version':'1.10.188','baseline':'installed header 1.10.185, read-only export 2026-10-26','original_main_sha256':hashlib.sha256(original).hexdigest(),'ci':'33 manual-priority assertions plus existing search regression and PHP lint','installation':'Plugin ZIP under Plugins > Add plugin > Upload; replace existing plugin after backup. Then resume already authorized supplemental feed.','rollback':'Install included original-code 1.10.185 rollback ZIP. No schema/option migration. Imported event changes are not undone by code rollback.','live_status':'not installed; Duisburg coverage and Android device acceptance remain open','files':{}}
for name,rollback in [('PottFuchs-Places-v1.10.188-Importsteuerung.zip',False),('PottFuchs-Places-v1.10.185-Rollback.zip',True)]:
 path=out/name
 with zipfile.ZipFile(path,'w',zipfile.ZIP_DEFLATED) as z:
  for p in sorted(root.rglob('*')):
   if p.is_file():
    info=zipfile.ZipInfo(str(Path('ruhrpott-guru-places')/p.relative_to(root)),(2026,10,26,0,0,0));info.compress_type=zipfile.ZIP_DEFLATED;info.external_attr=0o100644<<16
    z.writestr(info,original if rollback and p==main else p.read_bytes())
 with zipfile.ZipFile(path) as z:
  assert z.testzip() is None
  assert z.read('ruhrpott-guru-places/ruhrpott-guru-places.php')==(original if rollback else main.read_bytes())
  for dep in ['assets/bootstrap.js','includes/intelligence/class-rpg-iga-intelligence.php','includes/intelligence/class-rpg-outdoor-intelligence.php','includes/intelligence/class-rpg-outdoor-coverage.php']:assert len(z.read('ruhrpott-guru-places/'+dep))>0
 manifest['files'][name]={'sha256':hashlib.sha256(path.read_bytes()).hexdigest(),'bytes':path.stat().st_size}
(out/'release-manifest.json').write_text(json.dumps(manifest,ensure_ascii=False,indent=2)+'\n')
(out/'SHA256SUMS.txt').write_text(''.join(v['sha256']+'  '+k+'\n' for k,v in manifest['files'].items()))
print(json.dumps(manifest['files'],indent=2))
