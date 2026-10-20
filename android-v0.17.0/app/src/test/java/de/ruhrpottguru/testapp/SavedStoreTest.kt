package de.ruhrpottguru.testapp

import org.json.JSONObject
import org.junit.Assert.*
import org.junit.Test
import java.util.concurrent.CountDownLatch

class SavedStoreTest {
    private class Disk:SavedPersistence {
        @Volatile var value="[]"
        override fun read()=value
        override fun write(value:String):Boolean {this.value=value;return true}
    }
    @Test fun existing0161DataSurvivesReloadAndReminderExtension() {
        val disk=Disk();disk.value="""[{"id":"node/12","category":"food","title":"Altbestand","raw":{"id":12}}]"""
        val first=SavedStore(disk)
        first.edit {it.add(JSONObject().put("id","event-42").put("category","events").put("reminder",JSONObject().put("generation","g1").put("start",2000)))}
        val afterRestart=SavedStore(disk).read()
        assertEquals(2,afterRestart.size);assertEquals("Altbestand",afterRestart[0].getString("title"))
        assertEquals(12,afterRestart[0].getJSONObject("raw").getInt("id"))
        assertEquals("g1",afterRestart[1].getJSONObject("reminder").getString("generation"))
    }
    @Test fun staleWorkerCannotRestoreRemovedOrReconfiguredFavorite() {
        val disk=Disk();val store=SavedStore(disk)
        store.edit {it.add(JSONObject().put("id","event-42").put("reminder",JSONObject().put("generation","new")))}
        assertFalse(store.update("event-42","old"){it.put("title","wrong")})
        assertFalse(store.read().single().has("title"))
        store.edit {it.clear()}
        assertFalse(store.update("event-42","new"){it.put("title","wrong")})
        assertTrue(store.read().isEmpty())
    }
    @Test fun foregroundAndBackgroundWritesPreserveAllFavorites() {
        val disk=Disk();val a=SavedStore(disk);val b=SavedStore(disk)
        val start=CountDownLatch(1)
        val threads=(0..1).map {worker->Thread {
            start.await();repeat(100){i->(if(worker==0)a else b).edit {it.add(JSONObject().put("id","$worker-$i"))}}
        }.apply {start()}}
        start.countDown();threads.forEach {it.join()}
        assertEquals(200,SavedStore(disk).read().size)
        assertEquals(200,SavedStore(disk).read().map {it.getString("id")}.toSet().size)
    }
    @Test fun duplicateWorkersCannotClaimSameReminderTwice() {
        val disk=Disk();val a=SavedStore(disk);val b=SavedStore(disk)
        a.edit {it.add(JSONObject().put("id","e42").put("reminder",JSONObject().put("generation","g").put("sentStart",0)))}
        val notices=java.util.concurrent.atomic.AtomicInteger()
        val threads=listOf(a,b).map {store->Thread {
            store.update("e42","g") {row->val r=row.getJSONObject("reminder");if(r.getLong("sentStart")==0L){notices.incrementAndGet();r.put("sentStart",123)}}
        }.apply {start()}}
        threads.forEach {it.join()};assertEquals(1,notices.get())
    }
}
