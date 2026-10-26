"""Read-only characterization tests for archived PottFuchs backend v1.10.186.

Run:
  POTTFUCHS_BACKEND_ZIP=/path/to/original.zip python -m unittest -v tests/test_location_contract_v186.py
Tests assert OLD implementation; they do not prove compliance with new global rules.
"""
import os
import unittest
import zipfile

ARCHIVE = os.environ.get("POTTFUCHS_BACKEND_ZIP", "/mnt/data/PottFuchs-Places-Backend-v1.10.186-Import-Diagnose-FINAL.zip")
PHP = "ruhrpott-guru-places/ruhrpott-guru-places.php"


class LocationContractV186(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        with zipfile.ZipFile(ARCHIVE) as z:
            assert z.testzip() is None
            cls.source = z.read(PHP).decode("utf-8")
        cls.scope = cls.source[cls.source.index("// 1.10.165: the activated official Wesel source"):]

    def test_wesel_city_gate(self):
        self.assertIn("strcasecmp(trim((string)$city),'Wesel')===0", self.scope)

    def test_wesel_source_gate(self):
        self.assertIn("e.source='wesel_marketing_calendar'", self.scope)

    def test_missing_coordinate_filter(self):
        self.assertIn("(e.lat IS NULL OR e.lng IS NULL)", self.scope)

    def test_only_active_events(self):
        self.assertIn("e.status='active'", self.scope)

    def test_date_window(self):
        self.assertIn("e.end_at>=%s OR (e.end_at IS NULL AND DATE(e.start_at)>=DATE(%s))", self.scope)

    def test_city_scope_marked(self):
        self.assertIn("$cr['_rpg_city_scope']=1", self.scope)

    def test_sort_sentinel(self):
        self.assertIn("$cr['distance_km']=99999.0", self.scope)

    def test_candidate_limit(self):
        self.assertIn("ORDER BY e.start_at ASC,e.id ASC LIMIT 500", self.scope)

    def test_scope_excludes_raw_coordinates(self):
        self.assertIn("unset($raw['latitude'],$raw['longitude'],$raw['lat'],$raw['lng'],$raw['geo'])", self.scope)

    def test_scope_exposes_city_label(self):
        self.assertIn("$raw['rpg_geo_scope']='city'", self.scope)

    def test_city_scope_and_internal_sort_sentinel_are_distinct(self):
        self.assertIn("$raw['rpg_geo_scope']='city'", self.scope)
        self.assertIn("$cr['distance_km']=99999.0", self.scope)

    def test_sort_prefers_start_time(self):
        self.assertIn("return $ta <=> $tb;", self.scope)


if __name__ == "__main__":
    unittest.main()
