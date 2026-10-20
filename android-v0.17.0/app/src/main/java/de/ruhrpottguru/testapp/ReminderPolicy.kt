package de.ruhrpottguru.testapp

import org.json.JSONObject
import java.time.Instant
import java.time.ZoneId
import java.util.Locale

/** Canonical DB occurrences only: never reinterpret the source's parent series. */
internal object ReminderPolicy {
    val zone:ZoneId=ZoneId.of("Europe/Berlin")
    fun start(raw:JSONObject):Long?=runCatching {Instant.parse(raw.text("start")).toEpochMilli()}.getOrNull()?.takeIf {it>0}
    fun allDay(raw:JSONObject,fallback:Boolean):Boolean {
        val d=raw.optJSONObject("rpg_event_details")
        val v=if(d?.has("allDay")==true)d.opt("allDay") else if(raw.has("allDay"))raw.opt("allDay") else return fallback
        return v==true||v==1||v.toString().lowercase(Locale.ROOT) in listOf("true","1","yes","ja","on")
    }
    fun due(start:Long,leadMinutes:Int,allDay:Boolean):Long =
        if(allDay)Instant.ofEpochMilli(start).atZone(zone).toLocalDate().minusDays(1).atTime(18,0).atZone(zone).toInstant().toEpochMilli()
        else start-leadMinutes*60_000L
    fun cancelled(raw:JSONObject):Boolean {
        val yes=setOf("true","1","yes","ja","cancelled","canceled","abgesagt")
        if(listOf("cancelled","canceled","abgesagt","isCancelled","isCanceled","is_cancelled","is_canceled","isAbgesagt","is_abgesagt").any {raw.text(it).lowercase(Locale.ROOT) in yes || (raw.opt(it) as? Number)?.toDouble()==1.0})return true
        return statuses(raw).any {Regex("(?:^|[/#:_ -])(eventcancelled|eventcanceled|cancelled|canceled|abgesagt|entfaellt|entfällt|faellt-aus|fällt-aus)(?:$|[/#:_ -])").containsMatchIn(it)} ||
            Regex("^(?:\\[?\\s*)?(?:abgesagt|entfällt|entfaellt|fällt aus|faellt aus|cancelled|canceled)\\b",RegexOption.IGNORE_CASE).containsMatchIn(raw.text("title"))
    }
    fun postponed(raw:JSONObject)=statuses(raw).any {it.contains("eventpostponed")||it in setOf("postponed","verschoben")}
    private fun statuses(raw:JSONObject)=listOf("eventStatus","event_status","status","state","sourceStatus","source_status").map {raw.text(it).lowercase(Locale.ROOT)}
    fun token(entry:JSONObject):String?=entry.optJSONObject("descriptor")?.text("token")?.takeIf {Regex("e[1-9][0-9]*").matches(it)}
    fun signature(raw:JSONObject)=listOf("title","start","end","venueName","city","address").joinToString("|"){raw.opt(it)?.toString().orEmpty()}
    fun canDeliver(now:Long,start:Long,due:Long,sentStart:Long)=now>=due&&now<start&&sentStart!=start
    fun validResponse(raw:JSONObject,token:String)=raw.optJSONObject("rpg_event_db")?.optLong("eventId")==token.drop(1).toLongOrNull()
    fun state(raw:JSONObject?,unavailable:Boolean,now:Long):String=when {
        raw!=null&&cancelled(raw)->"cancelled"
        unavailable->"unavailable"
        raw!=null&&postponed(raw)->"postponed"
        raw==null||start(raw)==null->"unknown"
        start(raw)!!<=now->"past"
        else->"active"
    }
    fun watched(entry:JSONObject,now:Long)=entry.optJSONObject("reminder")?.optLong("start")?.let {it>now-86_400_000L}==true
    fun refreshInterval(start:Long,now:Long)=when {start-now<=3*3_600_000L->15*60_000L;start-now<=24*3_600_000L->3_600_000L;else->6*3_600_000L}
}
