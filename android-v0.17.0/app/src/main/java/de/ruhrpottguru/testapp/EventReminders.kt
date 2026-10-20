package de.ruhrpottguru.testapp

import android.Manifest
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.net.Uri
import android.os.Build
import androidx.work.*
import org.json.JSONObject
import java.net.HttpURLConnection
import java.net.URL
import java.util.UUID
import java.util.concurrent.TimeUnit

internal object EventReminders {
    private const val CHANNEL="event-reminders-v1"
    fun allowed(ctx:Context):Boolean {
        val manager=ctx.getSystemService(NotificationManager::class.java)
        return (Build.VERSION.SDK_INT<33||ctx.checkSelfPermission(Manifest.permission.POST_NOTIFICATIONS)==PackageManager.PERMISSION_GRANTED)&&manager.areNotificationsEnabled()&&manager.getNotificationChannel(CHANNEL)?.importance!=NotificationManager.IMPORTANCE_NONE
    }
    private fun workName(id:String)="rpg-event-"+java.security.MessageDigest.getInstance("SHA-256").digest(id.toByteArray()).joinToString(""){"%02x".format(it)}
    fun schedule(ctx:Context,row:JSONObject) {
        val r=row.optJSONObject("reminder") ?: return
        if(r.text("state")!="active"||r.optLong("start")<=System.currentTimeMillis()||r.optLong("sentStart")==r.optLong("start"))return
        val request=OneTimeWorkRequest.Builder(EventReminderWorker::class.java)
            .setInputData(Data.Builder().putString("id",row.text("id")).putString("generation",r.text("generation")).build())
            .setInitialDelay((r.optLong("due")-System.currentTimeMillis()).coerceAtLeast(0),TimeUnit.MILLISECONDS).build()
        WorkManager.getInstance(ctx).enqueueUniqueWork(workName(row.text("id")),ExistingWorkPolicy.REPLACE,request)
    }
    fun sync(ctx:Context) {
        val rows=SavedStore(ctx).read()
        val manager=WorkManager.getInstance(ctx)
        if(rows.none {ReminderPolicy.watched(it,System.currentTimeMillis())}){manager.cancelUniqueWork("rpg-reminder-sync");return}
        val request=PeriodicWorkRequest.Builder(EventReminderWorker::class.java,15,TimeUnit.MINUTES)
            .setConstraints(Constraints.Builder().setRequiredNetworkType(NetworkType.CONNECTED).build()).build()
        manager.enqueueUniquePeriodicWork("rpg-reminder-sync",ExistingPeriodicWorkPolicy.KEEP,request)
    }
    fun refresh(ctx:Context) {
        if(SavedStore(ctx).read().none {it.has("reminder")})return
        WorkManager.getInstance(ctx).enqueueUniqueWork("rpg-reminder-refresh",ExistingWorkPolicy.KEEP,
            OneTimeWorkRequest.Builder(EventReminderWorker::class.java).setInputData(Data.Builder().putBoolean("force",true).build()).setConstraints(Constraints.Builder().setRequiredNetworkType(NetworkType.CONNECTED).build()).build())
    }
    fun configure(ctx:Context,entry:JSONObject,lead:Int):JSONObject {
        require(allowed(ctx)){"Bitte Android-Benachrichtigungen erlauben."}
        require(lead==120||lead==1440)
        require(ReminderPolicy.token(entry)!=null){"Für diesen Termin fehlt ein verlässlich aktualisierbarer Event-Link."}
        val raw=entry.optJSONObject("raw") ?: error("Termindaten fehlen.")
        val start=ReminderPolicy.start(raw) ?: error("Keine eindeutige Startzeit vorhanden.")
        require(start>System.currentTimeMillis()){ "Dieser Termin hat bereits begonnen." }
        require(!ReminderPolicy.cancelled(raw)&&!ReminderPolicy.postponed(raw)){ "Für diesen Termin ist derzeit keine Erinnerung möglich." }
        val allDay=entry.optJSONObject("calendar")?.optBoolean("allDay") ?: ReminderPolicy.allDay(raw,false)
        val due=ReminderPolicy.due(start,lead,allDay)
        require(due>System.currentTimeMillis()){ "Dieser Erinnerungszeitpunkt ist bereits vorbei. Bitte einen kürzeren Vorlauf wählen." }
        return JSONObject().put("generation",UUID.randomUUID().toString()).put("lead",lead).put("start",start).put("due",due)
            .put("allDay",allDay).put("state","active").put("signature",ReminderPolicy.signature(raw)).put("sentStart",0)
    }
    fun cancel(ctx:Context,id:String) {
        WorkManager.getInstance(ctx).cancelUniqueWork(workName(id))
        val manager=ctx.getSystemService(NotificationManager::class.java)
        manager.cancel(id,1);manager.cancel(id,2)
    }
    fun notice(ctx:Context,row:JSONObject,text:String,change:Boolean=false):Boolean {
        if(!allowed(ctx))return false
        val manager=ctx.getSystemService(NotificationManager::class.java)
        manager.createNotificationChannel(NotificationChannel(CHANNEL,"Event-Erinnerungen",NotificationManager.IMPORTANCE_DEFAULT))
        val intent=Intent(ctx,MainActivity::class.java).apply {
            data=Uri.Builder().scheme("ruhrpottguru").authority("saved").appendPath(row.text("id")).build()
            putExtra("saved-event-id",row.text("id"));flags=Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
        }
        val pending=PendingIntent.getActivity(ctx,0,intent,PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE)
        val notification=android.app.Notification.Builder(ctx,CHANNEL).setSmallIcon(R.drawable.ic_event_reminder)
            .setContentTitle(row.text("title")).setContentText(text).setStyle(android.app.Notification.BigTextStyle().bigText(text))
            .setContentIntent(pending).setAutoCancel(true).build()
        return runCatching {manager.notify(row.text("id"),if(change)2 else 1,notification);true}.getOrDefault(false)
    }
}

/** Persistent, restart-safe scheduling; Android may defer background execution. */
class EventReminderWorker(context:Context,parameters:WorkerParameters):Worker(context,parameters) {
    override fun doWork():Result {
        val store=SavedStore(applicationContext)
        val id=inputData.getString("id");val expected=inputData.getString("generation")
        val rows=store.read().filter {ReminderPolicy.watched(it,System.currentTimeMillis())&&(id==null||it.text("id")==id&&it.optJSONObject("reminder")?.text("generation")==expected)}
        if(rows.isEmpty()){EventReminders.sync(applicationContext);return Result.success()}
        for(row in rows) {
            if(isStopped)return Result.success()
            val r=row.getJSONObject("reminder");val now=System.currentTimeMillis()
            if(id==null&&!inputData.getBoolean("force",false)&&now-r.optLong("lastChecked")<ReminderPolicy.refreshInterval(r.optLong("start"),now))continue
            process(store,row,id!=null)
        }
        return Result.success()
    }
    private fun process(store:SavedStore,row:JSONObject,dueWork:Boolean) {
        val config=row.getJSONObject("reminder");val id=row.text("id");val generation=config.text("generation")
        val token=ReminderPolicy.token(row) ?: return
        val response=runCatching {fetch(token)}
        val fresh=response.getOrNull()
        val unavailable=response.exceptionOrNull() is MissingEvent
        var schedule:JSONObject?=null
        store.update(id,generation) {entry->
            val r=entry.getJSONObject("reminder");val previous=r.text("state")
            if(fresh!=null||unavailable) {
                val oldStart=r.optLong("start")
                val start=fresh?.let(ReminderPolicy::start)
                val state=ReminderPolicy.state(fresh,unavailable,System.currentTimeMillis())
                r.put("state",state).put("lastChecked",System.currentTimeMillis())
                if(fresh!=null) {
                    entry.put("raw",fresh).put("title",fresh.text("title").ifBlank {entry.text("title")})
                    if(start!=null) {
                        val allDay=ReminderPolicy.allDay(fresh,r.optBoolean("allDay"))
                        r.put("start",start).put("allDay",allDay).put("due",ReminderPolicy.due(start,r.optInt("lead"),allDay))
                    }
                }
                val signature=fresh?.let(ReminderPolicy::signature).orEmpty()
                if(state!=previous || fresh!=null&&signature!=r.text("signature")) {
                    EventReminders.cancel(applicationContext,id)
                    if(state!="past")EventReminders.notice(applicationContext,entry,when(state){
                        "cancelled"->"Die Quelle meldet diesen Termin als abgesagt. Die Erinnerung wurde gestoppt."
                        "postponed"->"Die Quelle meldet eine Verschiebung. Die Erinnerung ist bis zur Klärung pausiert."
                        "unavailable"->"Der Termin ist nicht mehr abrufbar. Das ist keine bestätigte Absage; die Erinnerung ist pausiert."
                        "unknown"->"Der Termin hat keine eindeutige Startzeit mehr. Die Erinnerung ist pausiert."
                        else->"Termindaten wurden geändert. Bitte prüfe Datum, Uhrzeit und Veranstaltungsort."
                    },true)
                }
                if(fresh!=null)r.put("signature",signature)
                if(oldStart!=r.optLong("start"))r.put("sentStart",0)
            }
            val start=r.optLong("start");val now=System.currentTimeMillis()
            if(r.text("state")=="active"&&ReminderPolicy.canDeliver(now,start,r.optLong("due"),r.optLong("sentStart"))) {
                val whenText=java.time.format.DateTimeFormatter.ofPattern(if(r.optBoolean("allDay"))"dd.MM.yyyy" else "dd.MM.yyyy · HH:mm").withZone(ReminderPolicy.zone).format(java.time.Instant.ofEpochMilli(start))
                val note=if(fresh==null)" · Aktualität gerade nicht überprüfbar" else ""
                if(EventReminders.notice(applicationContext,entry,"Gespeicherter Termin: $whenText$note"))r.put("sentStart",start)
            }
            // The running due worker must not replace itself; periodic refreshes may re-plan a changed date.
            if(r.text("state")=="active"&&r.optLong("start")>now&&r.optLong("sentStart")!=r.optLong("start")&&(!dueWork||r.optLong("due")>now))schedule=JSONObject(entry.toString())
        }
        schedule?.let {EventReminders.schedule(applicationContext,it)}
    }
    private class MissingEvent:Exception()
    private fun fetch(token:String):JSONObject {
        val connection=URL(API_ROOT+"share-item?token=$token&category=events").openConnection() as HttpURLConnection
        try {
            connection.connectTimeout=10_000;connection.readTimeout=15_000;connection.instanceFollowRedirects=false
            connection.useCaches=false;connection.setRequestProperty("Cache-Control","no-cache");connection.setRequestProperty("Accept","application/json")
            if(connection.responseCode in listOf(404,410))throw MissingEvent()
            check(connection.responseCode==200){"Termindaten nicht erreichbar"}
            val data=JSONObject(connection.inputStream.bufferedReader().use {it.readText()})
            check(data.optBoolean("ok")&&data.text("kind")=="event")
            val raw=data.getJSONObject("item");check(ReminderPolicy.validResponse(raw,token)){"Anderer Termin geliefert"}
            // Direct share payloads can retain inactive DB rows. Confirm public feed
            // membership too; disappearance pauses a reminder but is never called a cancellation.
            if(!ReminderPolicy.cancelled(raw)&&!ReminderPolicy.postponed(raw))verifyPublicOccurrence(raw,token)
            return raw
        } finally {connection.disconnect()}
    }
    private fun verifyPublicOccurrence(raw:JSONObject,token:String) {
        val lat=raw.optDouble("latitude");val lng=raw.optDouble("longitude")
        if(!lat.isFinite()||!lng.isFinite())return
        val connection=URL(API_ROOT+"events").openConnection() as HttpURLConnection
        try {
            connection.connectTimeout=10_000;connection.readTimeout=15_000;connection.instanceFollowRedirects=false
            connection.useCaches=false;connection.requestMethod="POST";connection.doOutput=true
            connection.setRequestProperty("Content-Type","application/json; charset=utf-8")
            val city=raw.optJSONObject("address")?.text("city").orEmpty()
            val body=JSONObject().put("lat",lat).put("lng",lng).put("city",city).put("maxRadiusKm",1)
            connection.outputStream.use {it.write(body.toString().toByteArray())}
            check(connection.responseCode==200)
            val data=JSONObject(connection.inputStream.bufferedReader().use {it.readText()})
            check(!data.has("ok")||data.optBoolean("ok"))
            val items=data.optJSONArray("items") ?: error("Unvollständige Eventantwort")
            if(items.objects().none {ReminderPolicy.validResponse(it,token)})throw MissingEvent()
        } finally {connection.disconnect()}
    }
}
