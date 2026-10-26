import importlib.util
import unittest
from pathlib import Path

SCRIPT=Path(__file__).resolve().parents[1]/"tools"/"pottfuchs_wpcode_branding_offline.py"
spec=importlib.util.spec_from_file_location("pf_wpcode_patch",SCRIPT)
mod=importlib.util.module_from_spec(spec);spec.loader.exec_module(mod)

def source(ident):
    return "\n".join(mod.PRESERVE[ident])+"\n"+"\n".join(old for old,_ in mod.RULES[ident])+"\n"

class BrandingPatchTests(unittest.TestCase):
    def test_exact_five_public_brand_changes(self):
        old={k:source(k) for k in ("3343","4102")}
        new, changes=mod.apply_strict(old)
        self.assertEqual(len(changes),5)
        for ident in old:
            for before,after in mod.RULES[ident]:
                self.assertNotIn(before,new[ident])
                self.assertIn(after,new[ident])
        self.assertIn("rpg_seo_v3_filter",new["4102"])
        self.assertIn("urlTemplate",new["3343"])
    def test_reject_missing_original(self):
        original={k:source(k) for k in ("3343","4102")}
        original["4102"]=original["4102"].replace("$seo_title .= ' | RuhrpottGuru';","")
        with self.assertRaisesRegex(ValueError,"target 1"):
            mod.apply_strict(original)
    def test_reject_duplicate_original(self):
        original={k:source(k) for k in ("3343","4102")}
        original["4102"] += "$seo_title .= ' | RuhrpottGuru';"
        with self.assertRaisesRegex(ValueError,"target 1"):
            mod.apply_strict(original)
    def test_reject_unsafe_owner(self):
        original={k:source(k) for k in ("3343","4102")}
        original["3343"]=original["3343"].replace("rpg_seo_core_canonical","invalid")
        with self.assertRaisesRegex(ValueError,"signature mismatch"):
            mod.apply_strict(original)
    def test_refuse_second_application(self):
        original={k:source(k) for k in ("3343","4102")}
        altered,_=mod.apply_strict(original)
        with self.assertRaises(ValueError):
            mod.apply_strict(altered)
    def test_refuse_unknown_snippet_id(self):
        with self.assertRaisesRegex(ValueError,"exact live WPCode IDs"):
            mod.apply_strict({"3343":source("3343"),"9999":source("4102")})
    def test_preserve_technical_branding_comment(self):
        original={k:source(k)+"\n/* RuhrpottGuru technical ID preserved */" for k in ("3343","4102")}
        changed,_=mod.apply_strict(original)
        for ident in changed:
            self.assertIn("RuhrpottGuru technical ID preserved",changed[ident])
    def test_reverse_is_byte_identical(self):
        original={k:source(k)+"\nadd_filter('x','preserve');\n" for k in ("3343","4102")}
        changed,_=mod.apply_strict(original)
        for ident in original:
            restored=changed[ident]
            for before,after in reversed(mod.RULES[ident]):
                restored=restored.replace(after,before,1)
            self.assertEqual(original[ident],restored)
if __name__=="__main__": unittest.main()
