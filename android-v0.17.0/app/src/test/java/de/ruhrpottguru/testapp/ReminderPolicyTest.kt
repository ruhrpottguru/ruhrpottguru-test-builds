package de.ruhrpottguru.testapp

import org.json.JSONObject
import org.junit.Assert.*
import org.junit.Test
import java.time.Instant

class ReminderPolicyTest {
    private fun at(value:String)=Instant.parse(value).toEpochMilli()
    private fun raw()=JSONObject().put("start","2026-12-12T18:00:00+01:00").put("title","Konzert").put("rpg_event_db",JSONObject().put("eventId",42))
    @Test fun canonicalOccurrenceWinsOverParentIntervals() {
        val event=raw().put("timeIntervals",org.json.JSONArray("[{\"start\":\"2020-01-01T00:00:00Z\"}]"))
        assertEquals(at("2026-12-12T17:00:00Z"),ReminderPolicy.start(event))
        event.remove("start");assertNull(ReminderPolicy.start(event))
        assertNull(ReminderPolicy.start(raw().put("start","2026-12-12 18:00:00")))
    }
    @Test fun offsetAndSummerWinterClockChanges() {
        assertEquals(at("2026-03-29T00:30:00Z"),ReminderPolicy.start(raw().put("start","2026-03-29T01:30:00+01:00")))
        assertEquals(at("2026-03-29T01:30:00Z"),ReminderPolicy.start(raw().put("start","2026-03-29T03:30:00+02:00")))
        assertEquals(at("2026-12-12T15:00:00Z"),ReminderPolicy.due(ReminderPolicy.start(raw())!!,120,false))
    }
    @Test fun allDayHasExplicitPreviousEveningInsteadOfMidnight() {
        assertEquals(at("2026-03-28T17:00:00Z"),ReminderPolicy.due(at("2026-03-29T00:00:00Z"),1440,true))
        assertEquals(at("2026-10-24T16:00:00Z"),ReminderPolicy.due(at("2026-10-25T00:00:00Z"),1440,true))
        assertFalse(ReminderPolicy.allDay(raw().put("rpg_event_details",JSONObject().put("allDay",false)),true))
        assertTrue(ReminderPolicy.allDay(raw(),true))
    }
    @Test fun onlyExplicitCancellationSignalsCount() {
        for(key in listOf("cancelled","isCancelled","is_canceled","abgesagt"))assertTrue(ReminderPolicy.cancelled(raw().put(key,true)))
        for(value in listOf("https://schema.org/EventCancelled","cancelled","abgesagt"))assertTrue(ReminderPolicy.cancelled(raw().put("eventStatus",value)))
        assertTrue(ReminderPolicy.cancelled(raw().put("title","[Abgesagt] Konzert")))
        assertFalse(ReminderPolicy.cancelled(raw().put("title","Keine Veranstaltung abgesagt").put("source_status","missing")))
        assertFalse(ReminderPolicy.cancelled(raw().put("cancelled",false)))
        assertTrue(ReminderPolicy.cancelled(raw().put("cancelled",1.0)))
        assertFalse(ReminderPolicy.cancelled(raw().put("cancelled",0)))
    }
    @Test fun missingAndNetworkFailureAreNotCancellation() {
        val now=at("2026-11-01T00:00:00Z")
        assertEquals("unavailable",ReminderPolicy.state(null,true,now))
        assertEquals("unknown",ReminderPolicy.state(null,false,now))
        assertEquals("postponed",ReminderPolicy.state(raw().put("eventStatus","https://schema.org/EventPostponed"),false,now))
        assertEquals("active",ReminderPolicy.state(raw().put("source_status","missing"),false,now))
        assertEquals("past",ReminderPolicy.state(raw(),false,at("2027-01-01T00:00:00Z")))
    }
    @Test fun oneNoticePerOccurrenceNeverAfterStart() {
        val start=100_000L;val due=80_000L
        assertFalse(ReminderPolicy.canDeliver(79_999,start,due,0))
        assertTrue(ReminderPolicy.canDeliver(80_000,start,due,0))
        assertFalse(ReminderPolicy.canDeliver(85_000,start,due,start))
        assertFalse(ReminderPolicy.canDeliver(start,start,due,0))
        assertTrue(ReminderPolicy.canDeliver(180_000,200_000,180_000,start))
    }
    @Test fun stableIdentityRejectsWrongOccurrenceAndUnsafeTokens() {
        val entry=JSONObject().put("descriptor",JSONObject().put("token","e42"))
        assertEquals("e42",ReminderPolicy.token(entry));assertTrue(ReminderPolicy.validResponse(raw(),"e42"))
        assertFalse(ReminderPolicy.validResponse(raw(),"e43"))
        entry.getJSONObject("descriptor").put("token","e42&category=food");assertNull(ReminderPolicy.token(entry))
    }
    @Test fun changesAndExpiredMonitoring() {
        assertEquals(900_000L,ReminderPolicy.refreshInterval(1_000_000L,0))
        assertEquals(3_600_000L,ReminderPolicy.refreshInterval(12*3_600_000L,0))
        assertEquals(21_600_000L,ReminderPolicy.refreshInterval(48*3_600_000L,0))
        assertNotEquals(ReminderPolicy.signature(raw()),ReminderPolicy.signature(raw().put("start","2026-12-13T18:00:00+01:00")))
        assertNotEquals(ReminderPolicy.signature(raw()),ReminderPolicy.signature(raw().put("venueName","Neue Halle")))
        val entry=JSONObject().put("reminder",JSONObject().put("start",100_000_000L))
        assertTrue(ReminderPolicy.watched(entry,100_000_000L))
        assertFalse(ReminderPolicy.watched(entry,200_000_000L))
    }
}
