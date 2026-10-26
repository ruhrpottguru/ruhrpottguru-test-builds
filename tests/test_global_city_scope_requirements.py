"""RED acceptance checks for agreed global city-scope event handling.

Run: POTTFUCHS_BACKEND_ZIP=/path/to/backend.zip python -m unittest -v tests/test_global_city_scope_requirements.py
Tests fail on archived v1.10.186 by design; they do not verify live behavior.
"""
import os
import unittest
import zipfile

ARCHIVE = os.environ.get("POTTFUCHS_BACKEND_ZIP", "/mnt/data/PottFuchs-Places-Backend-v1.10.186-Import-Diagnose-FINAL.zip")
MAIN = "ruhrpott-guru-places/ruhrpott-guru-places.php"


class GlobalCityScopeRequirements(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        with zipfile.ZipFile(ARCHIVE) as z:
            cls.source = z.read(MAIN).decode("utf-8")
        start = cls.source.index("// 1.10.165: the activated official Wesel source")
        end = cls.source.index("usort($cluster_input", start)
        cls.admission = cls.source[start:end]

    def test_admission_not_restricted_to_wesel(self):
        self.assertNotRegex(self.admission, r"strcasecmp\s*\(\s*trim\(\(string\)\$city\),\s*'Wesel'\s*\)")

    def test_admission_not_restricted_to_wesel_source(self):
        self.assertNotIn("e.source='wesel_marketing_calendar'", self.admission)

    def test_no_unpaged_500_candidate_cap(self):
        self.assertNotRegex(self.admission, r"LIMIT\s+500\b")

    def test_no_wesel_default_city_label(self):
        self.assertNotIn("($row['city']?:'Wesel')", self.source)


if __name__ == "__main__":
    unittest.main()
