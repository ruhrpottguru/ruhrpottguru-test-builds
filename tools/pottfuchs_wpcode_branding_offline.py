#!/usr/bin/env python3
"""Strict 5-literal PottFuchs branding patch for *existing* WPCode sources.

Offline only: reads an explicitly supplied JSON export mapping ids to exact
raw snippet bodies; never connects to WordPress, installs, or activates code.
Output stays outside plugin paths. Validates original exact owner and refuses
ambiguity, edited/unexpected sources, or second application.
"""
import argparse
import difflib
import hashlib
import json
from pathlib import Path

RULES = {
 "3343": (
  ("return sprintf('%s auf RuhrpottGuru – Tipps, Orte und Geschichten aus dem Ruhrgebiet.', $term->name);",
   "return sprintf('%s auf PottFuchs – Tipps, Orte und Geschichten aus dem Ruhrgebiet.', $term->name);"),
  ("'@id'=>$home.'#organization','name'=>'RuhrpottGuru','url'=>$home",
   "'@id'=>$home.'#organization','name'=>'PottFuchs','url'=>$home"),
  ("'@id'=>$home.'#website','url'=>$home,'name'=>'RuhrpottGuru'",
   "'@id'=>$home.'#website','url'=>$home,'name'=>'PottFuchs'"),
  ("['@type'=>'ListItem','position'=>1,'name'=>'RuhrpottGuru','item'=>$home]",
   "['@type'=>'ListItem','position'=>1,'name'=>'PottFuchs','item'=>$home]"),
 ),
 "4102": (
  ("$seo_title .= ' | RuhrpottGuru';",
   "$seo_title .= ' | PottFuchs';"),
 ),
}
PRESERVE = {
 "3343": ("rpg_seo_core_is_dynamic_detail", "rpg_seo_core_is_city_path",
          "rpg_seo_core_canonical", "urlTemplate", "query-input", "rpg_seo_city_context"),
 "4102": ("rpg_seo_v3_filter", "rpg_seo_v3_event", "rpg_seo_v3_place",
          "template_redirect", "ob_start('rpg_seo_v3_filter')"),
}

def apply_strict(source_map):
    if set(source_map)!={"3343","4102"}:
        raise ValueError("Expected exact live WPCode IDs 3343 and 4102")
    outputs={}
    changes=[]
    for ident, original in source_map.items():
        if not isinstance(original, str) or not original.strip():
            raise ValueError(f"WPCode {ident} source must be a nonempty exact UTF-8 string")
        if any(marker not in original for marker in PRESERVE[ident]):
            raise ValueError(f"WPCode {ident} source owner/guard signature mismatch")
        changed=original
        for index,(old,new) in enumerate(RULES[ident],start=1):
            count=changed.count(old)
            if count!=1:
                raise ValueError(f"WPCode {ident}, target {index}: expected exactly one original, found {count}")
            if new in changed:
                raise ValueError(f"WPCode {ident}, target {index}: new target already present")
            changed=changed.replace(old,new,1)
            changes.append({"wpcode_id":int(ident),"rule":index,"before":old,"after":new})
        if original == changed:
            raise ValueError(f"WPCode {ident} unexpectedly unchanged")
        for marker in PRESERVE[ident]:
            if changed.count(marker)!=original.count(marker):
                raise ValueError(f"WPCode {ident}: protected marker changed: {marker}")
        reverse=changed
        for old,new in reversed(RULES[ident]):
            if reverse.count(new)!=1:
                raise ValueError(f"WPCode {ident}: replacement not unique/reversible")
            reverse=reverse.replace(new,old,1)
        if reverse!=original:
            raise ValueError(f"WPCode {ident}: non-target bytes changed")
        outputs[ident]=changed
    assert len(changes)==5
    return outputs,changes

def main():
    p=argparse.ArgumentParser()
    p.add_argument("--input",type=Path,required=True,
                   help="Local authorized JSON export of exact WPCode source, keyed by ID")
    p.add_argument("--outdir",type=Path,required=True)
    a=p.parse_args()
    original=json.loads(a.input.read_text(encoding="utf-8"))
    patched,changes=apply_strict(original)
    a.outdir.mkdir(parents=True,exist_ok=True)
    audit={"status":"OFFLINE_PREPARATION_ONLY_NOT_DEPLOYED",
           "source_ids":[3343,4102],"change_count":len(changes),
           "changes":changes,"sources":[]}
    for ident,src in patched.items():
        name=f"wpcode-{ident}-candidate.php.txt"
        (a.outdir/name).write_text(src,encoding="utf-8")
        diff="".join(difflib.unified_diff(
          original[ident].splitlines(keepends=True),src.splitlines(keepends=True),
          fromfile=f"live-{ident}",tofile=f"candidate-{ident}"))
        (a.outdir/f"wpcode-{ident}.diff").write_text(diff,encoding="utf-8")
        audit["sources"].append({
          "id":int(ident),"original_sha256":hashlib.sha256(original[ident].encode()).hexdigest(),
          "candidate_sha256":hashlib.sha256(src.encode()).hexdigest(),
          "diff_file":f"wpcode-{ident}.diff","source_file":name})
    (a.outdir/"manifest.json").write_text(json.dumps(audit,ensure_ascii=False,indent=2)+"\n",encoding="utf-8")
    print("Validated five reversible public-brand-only changes. No WordPress writes.")
if __name__=="__main__":main()
