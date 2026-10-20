package de.ruhrpottguru.testapp

import android.content.Context
import org.json.JSONArray
import org.json.JSONObject

/** Same saved-v1 records as 0.16.1; one lock protects foreground and workers. */
internal interface SavedPersistence {fun read():String;fun write(value:String):Boolean}
internal class SavedStore(private val storage:SavedPersistence) {
    constructor(context:Context):this(object:SavedPersistence {
        private val prefs=context.getSharedPreferences("native-location-v2",0)
        override fun read()=prefs.getString("saved-v1","[]") ?: "[]"
        override fun write(value:String)=prefs.edit().putString("saved-v1",value).commit()
    })
    fun read():List<JSONObject> = synchronized(lock){runCatching {JSONArray(storage.read()).objects()}.getOrDefault(emptyList())}
    fun edit(block:(MutableList<JSONObject>)->Unit):List<JSONObject> = synchronized(lock) {
        val rows=read().toMutableList();block(rows)
        check(storage.write(JSONArray(rows).toString())){ "Merkliste konnte nicht gespeichert werden." }
        rows
    }
    fun update(id:String,generation:String,block:(JSONObject)->Unit):Boolean = synchronized(lock) {
        var found=false
        edit {rows->rows.firstOrNull {it.text("id")==id&&it.optJSONObject("reminder")?.text("generation")==generation}?.let {found=true;block(it)}}
        found
    }
    companion object {private val lock=Any()}
}
