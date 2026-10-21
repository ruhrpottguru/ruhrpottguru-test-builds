package de.ruhrpottguru.testapp

import org.json.JSONArray
import org.json.JSONObject
import org.junit.Assert.*
import org.junit.Test

class GlobalSearchPolicyTest {
    private fun fixture(name:String)=JSONObject(javaClass.getResourceAsStream("/search/$name.json")!!.bufferedReader().readText())
    @Test fun sameWebRequestNoLocationOrParallelFilters() {
        val body=GlobalSearchPolicy.request("  Topgolf Oberhausen  ")
        assertEquals("Topgolf Oberhausen",body.getString("q"));assertEquals(30,body.getInt("limit"))
        assertEquals(setOf("q","limit"),body.keys().asSequence().toSet())
        for(q in listOf(""," "," a "))try {GlobalSearchPolicy.request(q);fail()}catch(_:IllegalArgumentException){}
        assertEquals("Café & Bühne",GlobalSearchPolicy.request("Café & Bühne").getString("q"))
    }
    @Test fun liveResultMembershipOrderAndZeroResultsStayUnchanged() {
        for(name in listOf("Zollverein","Wesel","Topgolf-Oberhausen","zzqkeintreffer987")) {
            val data=fixture(name);val sections=GlobalSearchPolicy.sections(data)
            assertEquals(data.getInt("total"),sections.sumOf {it.rows.size})
            sections.forEach {s->val original=data.getJSONObject("groups").getJSONArray(s.key);assertEquals((0 until original.length()).map {original.getJSONObject(it).toString()},s.rows.map {it.toString()})}
        }
        val topgolf=GlobalSearchPolicy.sections(fixture("Topgolf-Oberhausen"))
        assertEquals(1,topgolf.size);assertEquals("Topgolf",topgolf.single().rows.single().getString("title"))
        assertTrue(GlobalSearchPolicy.sections(JSONObject().put("groups",JSONArray())).isEmpty())
    }
    @Test fun everyLivePlaceAndEventUsesExistingDetailIdentity() {
        var places=0;var events=0
        for(name in listOf("Wesel","Zollverein","Topgolf-Oberhausen"))for(section in GlobalSearchPolicy.sections(fixture(name)))for(row in section.rows) {
            if(row.optString("kind") !in listOf("event","place"))continue
            val target=GlobalSearchPolicy.destination(row) as SearchDestination.Detail
            assertEquals(row.getString("category"),target.category)
            val id=row.getString("id").substringAfter(':')
            assertEquals((if(row.getString("kind")=="event"){events++;"e"}else{places++;"p"})+id,target.token)
        }
        assertTrue(places>80);assertTrue(events>=60)
        for((token,cat) in listOf("e19383" to "events","p8577" to "activities","p10377" to "food","p22451" to "buedchen","p10387" to "wurst")) {
            val response=fixture("share-$token");assertTrue(response.getBoolean("ok"));assertEquals(cat,response.getString("category"))
            assertTrue(response.has(if(cat=="events")"item" else "element"))
        }
    }
    @Test fun existingSpecialsOpenNativelyAndArticleLinksAreExplicit() {
        for((path,name) in listOf("buedchen-radar" to "Trinkhallen-Radar","imbissbuden" to "Hier geht’s um die Wurst","route-industriekultur" to "Route der Industriekultur","pott-quiz" to "Pott-Quiz")) {
            val destination=GlobalSearchPolicy.destination(JSONObject().put("url","https://www.ruhrpottguru.de/$path/"))
            assertEquals(SearchDestination.Page(name),destination);assertTrue(AppNavigation.enabled(name))
        }
        val u="https://www.ruhrpottguru.de/stadt/wesel/kultur/"
        assertEquals(SearchDestination.External(u),GlobalSearchPolicy.destination(JSONObject().put("url",u)))
        val external="https://example.org/pott-quiz/";assertEquals(SearchDestination.External(external),GlobalSearchPolicy.destination(JSONObject().put("url",external)))
    }
    @Test fun invalidIdentityAndActiveLinksCannotBecomeDetailRequests() {
        for(id in listOf("place:0","place:-1","place:1?other=2","place:../x","wurst:1"))assertNull(GlobalSearchPolicy.destination(JSONObject().put("kind","place").put("category","culture").put("id",id)))
        for(url in listOf("javascript:alert(1)","file:///etc/passwd","intent://bad","https://name@example.org/","https:///none","mailto:test@example.org"))assertNull(GlobalSearchPolicy.destination(JSONObject().put("url",url)))
        assertNull(GlobalSearchPolicy.destination(JSONObject().put("kind","place").put("category","unknown").put("id","place:12")))
    }
    @Test fun globalEntryIsEnabledAndUnreleasedMenuEntriesRemainDisabled() {
        assertTrue(AppNavigation.enabled("Suche"));assertTrue(AppNavigation.enabled("Food"));assertFalse(AppNavigation.enabled("Ruhrpott-Lexikon"))
    }
}
