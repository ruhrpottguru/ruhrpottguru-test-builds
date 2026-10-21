package de.ruhrpottguru.testapp

import org.json.JSONObject
import java.net.URI

internal data class SearchSection(val key:String,val label:String,val rows:List<JSONObject>)
internal sealed class SearchDestination {
    data class Detail(val token:String,val category:String):SearchDestination()
    data class Page(val name:String):SearchDestination()
    data class External(val url:String):SearchDestination()
}
/** Adapts the existing public Web search response; never ranks or reclassifies results. */
internal object GlobalSearchPolicy {
    val groups=listOf("events" to "Events","food" to "Food","highlights" to "Highlights","culture" to "Kultur","activities" to "Aktivitäten","buedchen" to "Trinkhallen-Radar","wurst" to "Hier geht’s um die Wurst","specials" to "Specials","articles" to "Beiträge")
    fun request(query:String):JSONObject {
        val q=query.trim();require(q.length>=2){"Bitte mindestens 2 Zeichen eingeben."}
        return JSONObject().put("q",q).put("limit",30)
    }
    fun sections(payload:JSONObject):List<SearchSection> {
        val data=payload.optJSONObject("groups") ?: return emptyList()
        val keys=groups.map {it.first}+data.keys().asSequence().filter {key->groups.none {it.first==key}}.toList()
        return keys.mapNotNull {key->
            val rows=data.optJSONArray(key)?.let {a->(0 until a.length()).mapNotNull {a.optJSONObject(it)}}.orEmpty()
            rows.takeIf {it.isNotEmpty()}?.let {SearchSection(key,groups.find {it.first==key}?.second ?: "Weitere Treffer",it)}
        }
    }
    fun destination(row:JSONObject):SearchDestination? {
        val category=row.optString("category","");val id=row.optString("id","")
        if(row.optString("kind")=="event" && category=="events") {
            val n=Regex("event:([1-9][0-9]*)").matchEntire(id)?.groupValues?.get(1) ?: return null
            return SearchDestination.Detail("e$n",category)
        }
        if(row.optString("kind")=="place" && category in listOf("food","highlights","culture","activities","buedchen","wurst")) {
            val prefix=if(category=="wurst")"(?:wurst|place)" else "place"
            val n=Regex("$prefix:([1-9][0-9]*)").matchEntire(id)?.groupValues?.get(1) ?: return null
            return SearchDestination.Detail("p$n",category)
        }
        val url=row.optString("url","")
        if(!allowedExternalUrl(url)||!url.startsWith("https://",true))return null
        val uri=runCatching {URI(url)}.getOrNull() ?: return null
        if(uri.host.lowercase() in listOf("www.ruhrpottguru.de","ruhrpottguru.de") && uri.query==null) {
            val page=mapOf("/buedchen-radar/" to "Trinkhallen-Radar","/imbissbuden/" to "Hier geht’s um die Wurst","/route-industriekultur/" to "Route der Industriekultur","/pott-quiz/" to "Pott-Quiz","/halloween-im-pott/" to "Halloween im Pott","/weihnachten-im-pott/" to "Weihnachten im Pott")[uri.path.trimEnd('/')+"/"]
            if(page!=null)return SearchDestination.Page(page)
        }
        return SearchDestination.External(url)
    }
}
