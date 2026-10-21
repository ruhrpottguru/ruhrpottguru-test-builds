package de.ruhrpottguru.testapp

import android.app.Application
import android.os.Handler
import android.os.Looper
import androidx.lifecycle.AndroidViewModel
import androidx.compose.runtime.*
import org.json.JSONArray
import org.json.JSONObject
import java.net.HttpURLConnection
import java.net.URL
import java.util.concurrent.Executors

const val API_ROOT = "https://www.ruhrpottguru.de/wp-json/ruhrpottguru/v1/"
data class GuruLocation(val lat: Double, val lng: Double, val city: String, val mode: String, val postcode: String = "") {
    val label get() = listOf(postcode, city).filter { it.isNotBlank() }.joinToString(" ").ifBlank { "GPS-Standort" }
    fun json() = JSONObject().put("lat",lat).put("lng",lng).put("city",city).put("mode",mode).put("postcode",postcode)
}
data class GuruCity(val name: String, val lat: Double, val lng: Double)
fun JSONArray.objects() = (0 until length()).map { getJSONObject(it) }
fun JSONObject.text(key: String) = optString(key, "").takeUnless { it == "null" }.orEmpty()

class FoodController(app: Application) : AndroidViewModel(app) {
    val timeTravel=TimeTravelController(app)
    private val handler = Handler(Looper.getMainLooper())
    private val pool = Executors.newFixedThreadPool(2)
    private val mediaPool = Executors.newFixedThreadPool(3)
    private val mediaAttempts=MediaAttemptGate()
    private val prefs = app.getSharedPreferences("native-location-v2",0)
    @Volatile private var generation = 0
    private var filterGeneration = 0
    private var detailGeneration = 0
    private var resolveGeneration = 0
    private var globalSerial=0
    private var searchDetailActive=false
    var globalQuery by mutableStateOf(""); private set
    var globalExecutedQuery by mutableStateOf(""); private set
    var globalLoading by mutableStateOf(false); private set
    var globalError by mutableStateOf(""); private set
    var globalTotal by mutableStateOf(0); private set
    internal var globalSections by mutableStateOf(emptyList<SearchSection>()); private set
    var searchDetailOpening by mutableStateOf(false); private set
    var searchDetailError by mutableStateOf(""); private set
    fun editGlobalQuery(value:String) {globalQuery=value}
    fun cancelGlobalSearch() {globalSerial++;globalLoading=false}
    fun clearGlobalSearch() {cancelGlobalSearch();globalQuery="";globalExecutedQuery="";globalError="";globalSections=emptyList();globalTotal=0;closeDetail()}
    fun searchGlobally() {
        val token=++globalSerial
        val body=runCatching {GlobalSearchPolicy.request(globalQuery)}.getOrElse {globalLoading=false;globalError=it.message.orEmpty();return}
        globalExecutedQuery=body.text("q");globalLoading=true;globalError="";globalSections=emptyList();globalTotal=0
        pool.execute {
            val result=runCatching {http("global-search",body)}
            handler.post {
                if(disposed||token!=globalSerial)return@post
                globalLoading=false
                result.onSuccess {data->globalSections=GlobalSearchPolicy.sections(data);globalTotal=data.optInt("total");globalError=""}
                    .onFailure {globalError="Die Suche konnte gerade nicht geladen werden. Bitte erneut versuchen."}
            }
        }
    }
    internal fun openSearchResult(destination:SearchDestination.Detail) {
        val descriptor=JSONObject().put("token",destination.token).put("category",destination.category)
        openSnapshot(JSONObject().put("category",destination.category).put("descriptor",descriptor),true)
    }
    private var foodOpened = false
    private var disposed = false
    var radarCamera by mutableStateOf<TravelCamera?>(null)
    var radarMoved by mutableStateOf(false); private set
    var radarFilters by mutableStateOf(JSONObject()); private set
    var radarSort by mutableStateOf("distance"); private set
    var radarStats by mutableStateOf(JSONObject()); private set
    var radarMapItems by mutableStateOf(emptyList<JSONObject>()); private set
    var specialMode by mutableStateOf(""); private set
    var specialFilter by mutableStateOf("all"); private set
    var specialCity by mutableStateOf(""); private set
    var specialInfo by mutableStateOf(JSONObject()); private set
    private var specialRadiusOverride by mutableStateOf<Double?>(null)
    val activeRadius get()=specialRadiusOverride ?: radius
    private var chosenRadiusPending=false
    fun chooseLocationRadius() {chosenRadiusPending=true}
    fun openSpecial(mode:String) {
        if(mode !in listOf("route","halloween","weihnachten"))return
        if(specialMode!=mode)specialRadiusOverride=if(mode=="weihnachten"&&!prefs.contains("radius"))25.0 else null
        openCategory(if(mode=="route")"highlights" else "events",mode)
    }
    fun leaveSpecial() {
        if(specialMode.isBlank())return
        generation++;filterGeneration++;closeDetail();specialMode="";specialRadiusOverride=null;chosenRadiusPending=false;foodOpened=false
        specialInfo=JSONObject();specialFilter="all";specialCity="";loading=false;error="";results=emptyList();total=0
    }
    fun setSpecialFilter(value:String,city:String=specialCity) {
        val filters=specialInfo.optJSONArray("filters")?.objects().orEmpty()
        if(filters.none {it.text("key")==value})return
        val cities=specialInfo.optJSONArray("cities")
        if(city.isNotBlank()&&(cities==null||(0 until cities.length()).none {cities.optString(it)==city}))return
        specialFilter=value;specialCity=city;visible=24
        engine.call("NativeFood.specialSelect(${JSONObject.quote(value)},${JSONObject.quote(city)})") {refresh()}
    }
    var category by mutableStateOf("food"); private set
    var dateMode by mutableStateOf(""); private set
    var dateFrom by mutableStateOf(""); private set
    var dateTo by mutableStateOf(""); private set
    var dateLabel by mutableStateOf(""); private set
    var relatedItems by mutableStateOf(emptyList<JSONObject>()); private set
    var relatedLoading by mutableStateOf(false); private set
    var detailError by mutableStateOf(""); private set
    var ready by mutableStateOf(false); private set
    var cities by mutableStateOf(emptyList<GuruCity>()); private set
    var location by mutableStateOf<GuruLocation?>(null); private set
    var radius by mutableStateOf(prefs.getFloat("radius",5f).toDouble().takeIf { it in 1.0..50.0 } ?: 5.0); private set
    var needsFreshGps by mutableStateOf(prefs.getString("mode","") == "gps"); private set
    var loading by mutableStateOf(false); private set
    var resolving by mutableStateOf(false); private set
    var error by mutableStateOf(""); private set
    var locationError by mutableStateOf(""); private set
    var results by mutableStateOf(emptyList<JSONObject>()); private set
    var total by mutableStateOf(0); private set
    var tags by mutableStateOf(emptyList<JSONObject>()); private set
    var subtypes by mutableStateOf(emptyList<String>()); private set
    var detail by mutableStateOf<JSONObject?>(null); private set
    var query by mutableStateOf(""); private set
    var tag by mutableStateOf(""); private set
    var subtype by mutableStateOf(""); private set
    var visible by mutableStateOf(24); private set
    var effectiveRadius by mutableStateOf<Double?>(null); private set
    var contextSerial by mutableStateOf(0); private set
    var placeEvents by mutableStateOf(emptyList<JSONObject>()); private set
    var placeEventsError by mutableStateOf(""); private set
    var stories by mutableStateOf(emptyList<JSONObject>()); private set
    var storiesLoading by mutableStateOf(false); private set
    var storiesError by mutableStateOf(""); private set
    var relatedLabel by mutableStateOf(""); private set
    var relatedCount by mutableStateOf(0); private set
    private val savedStore=SavedStore(app)
    var savedItems by mutableStateOf(savedStore.read()); private set
    var pendingSavedId by mutableStateOf<String?>(null)
    private val savedListener=android.content.SharedPreferences.OnSharedPreferenceChangeListener {_,key->
        if(key=="saved-v1")handler.post {if(!disposed)savedItems=savedStore.read()}
    }
    init {prefs.registerOnSharedPreferenceChangeListener(savedListener);EventReminders.sync(app)}
    fun refreshReminders() {EventReminders.refresh(getApplication())}
    fun clearReminder(id:String) {
        savedItems=savedStore.edit {rows->rows.firstOrNull {it.text("id")==id}?.remove("reminder")}
        EventReminders.cancel(getApplication(),id);EventReminders.sync(getApplication())
    }
    fun configureReminder(item:JSONObject,lead:Int) {
        error=""
        val token=generation;val selection=detailGeneration
        engine.call("NativeFood.snapshot(${JSONObject.quote(item.text("id"))})") {result->
            if(disposed||token!=generation||selection!=detailGeneration)return@call
            runCatching {
                val entry=result.getOrNull() as? JSONObject ?: error("Termin nicht verfügbar.")
                val config=EventReminders.configure(getApplication(),entry,lead)
                entry.put("reminder",config)
                savedItems=savedStore.edit {rows->rows.removeAll {it.text("id")==entry.text("id")};rows.add(entry)}
                EventReminders.schedule(getApplication(),entry);EventReminders.sync(getApplication());EventReminders.refresh(getApplication())
            }.onFailure {error=it.message ?: "Erinnerung konnte nicht gespeichert werden."}
        }
    }
    val engine = SharedFoodEngine(app) { result ->
        result.onSuccess {
            correctSavedImages()
            engineMetadata()
        }.onFailure { error = it.message.orEmpty() }
    }
    private fun correctSavedImages() {
        val original=savedItems.associate {it.text("id") to it.toString()}
        engine.call("NativeFood.correctedSavedMedia(${JSONArray(savedItems)})") {result->
            if(disposed)return@call
            val corrected=(result.getOrNull() as? JSONArray)?.objects()?.associateBy {it.text("id")} ?: return@call
            savedItems=savedStore.edit {rows->for(i in rows.indices) {val entry=rows[i];if(original[entry.text("id")]==entry.toString())rows[i]=corrected[entry.text("id")] ?: entry}}
        }
    }
    private fun engineMetadata() {
        engine.call("NativeFood.metadata()") { r ->
            r.onSuccess { data ->
                cities=(data as JSONArray).objects().map { GuruCity(it.getString("name"),it.getDouble("lat"),it.getDouble("lng")) }
                ready=true
                if (!needsFreshGps) {
                    runCatching { JSONObject(prefs.getString("point",null) ?: "") }.getOrNull()?.let { p ->
                        val lat=p.optDouble("lat");val lng=p.optDouble("lng")
                        if (lat.isFinite() && lng.isFinite() && lat in -90.0..90.0 && lng in -180.0..180.0)
                            location=GuruLocation(lat,lng,p.text("city"),p.text("mode"),p.text("postcode"))
                    }
                    if (foodOpened && (location != null || specialMode=="route")) loadItems()
                }
            }.onFailure { error="Die Standortliste ist nicht verfügbar." }
        }
    }
    fun openFood() = openCategory("food")
    fun openCategory(value:String,mode:String="") {
        if(value !in listOf("food","events","highlights","culture","activities","buedchen","wurst"))return
        foodOpened=true
        if(category!=value || specialMode!=mode) {
            generation++;filterGeneration++;closeDetail();category=value;specialMode=mode;specialFilter=if(mode=="weihnachten")"weihnachtsmaerkte" else "all";specialCity="";specialInfo=JSONObject();if(mode.isBlank())specialRadiusOverride=null
            query="";tag="";subtype="";dateMode="";dateFrom="";dateTo="";dateLabel="";visible=24;radarCamera=null;radarMoved=false;radarFilters=JSONObject();radarSort="distance";radarMapItems=emptyList()
            results=emptyList();tags=emptyList();subtypes=emptyList();total=0;loading=false;error=""
        }
        if(value=="events" && mode.isBlank()) {
            dateMode="today";dateFrom="";dateTo="";dateLabel=""
            if(ready && !loading && results.isNotEmpty())setDatePreset("today")
        }
        if(ready && (location!=null||specialMode=="route") && results.isEmpty() && !loading)loadItems()
    }
    fun chooseCity(city: GuruCity, km: Double) {
        cancelLocation();chooseLocationRadius(); commit(GuruLocation(city.lat,city.lng,city.name,"city"),km)
    }
    fun cancelLocation() { chosenRadiusPending=false;resolveGeneration++; resolving=false; locationError=""; needsFreshGps=false }
    fun beginGps() { resolveGeneration++; resolving=true; locationError=""; needsFreshGps=false }
    fun gpsFailure(message: String) { chosenRadiusPending=false;resolving=false; locationError=message }
    fun acceptGps(lat: Double,lng: Double, km: Double) {
        val token=resolveGeneration
        engine.call("NativeFood.nearest($lat,$lng)") { r ->
            if(token!=resolveGeneration || disposed)return@call
            r.onSuccess { commit(GuruLocation(lat,lng,it as? String ?: "","gps"),km) }
                .onFailure { gpsFailure("Der GPS-Standort konnte nicht übernommen werden.") }
        }
    }
    fun resolveLocation(postcode: String, street: String, house: String, city: String, km: Double) {
        val address=street.isNotBlank()
        if (!Regex("\\d{5}").matches(postcode) || (address && (house.isBlank() || city.isBlank()))) {
            locationError=if(address) "Bitte Straße, Hausnummer, PLZ und Stadt vollständig eingeben." else "Bitte eine fünfstellige PLZ eingeben."
            return
        }
        val token=++resolveGeneration;resolving=true;locationError="";needsFreshGps=false
        pool.execute {
            val result=runCatching {
                if(address) {
                    val fresh=http("address-location-token",null)
                    val nonce=fresh.text("token");check(nonce.isNotBlank()) { "Die Adresssuche konnte nicht gestartet werden." }
                    http("address-location",JSONObject().put("street",street.trim()).put("housenumber",house.trim()).put("postcode",postcode.trim()).put("city",city.trim()),nonce)
                } else http("postcode-location",JSONObject().put("postcode",postcode.trim()))
            }
            handler.post {
                if(token!=resolveGeneration || disposed)return@post
                result.onSuccess { p ->
                    val lat=p.optDouble("lat");val lng=p.optDouble("lng")
                    if(lat.isFinite() && lng.isFinite() && lat in -90.0..90.0 && lng in -180.0..180.0)
                        engine.call("NativeFood.location($p,$address)") { r ->
                            if(token!=resolveGeneration || disposed)return@call
                            r.onSuccess { normalized -> chooseLocationRadius();commit(GuruLocation(lat,lng,(normalized as JSONObject).text("city"),if(address) "address" else "postcode",p.text("postcode").ifBlank { postcode }),km) }
                                .onFailure { gpsFailure("Der Standort konnte nicht dem RuhrpottGuru-Gebiet zugeordnet werden.") }
                        }
                    else gpsFailure("Zu dieser Eingabe wurde kein Standort gefunden.")
                }.onFailure { gpsFailure(it.message ?: "Die Standortsuche ist fehlgeschlagen.") }
            }
        }
    }
    fun changeRadius(km: Double) { specialRadiusOverride=null;location?.let { commit(it,km) } }
    private fun commit(point: GuruLocation, km: Double) {
        if(chosenRadiusPending){specialRadiusOverride=null;chosenRadiusPending=false}
        resolveGeneration++;resolving=false;locationError="";needsFreshGps=false
        location=point;radius=km;contextSerial++;detail=null
        prefs.edit().putString("mode",point.mode).putFloat("radius",km.toFloat()).apply {
            if(point.mode=="gps")remove("point") else putString("point",point.json().toString())
        }.apply()
        if(category !in listOf("buedchen","wurst")) {
            query="";tag="";subtype="";dateMode="";dateFrom="";dateTo="";dateLabel="";visible=24
        }
        if(category=="events" && specialMode.isBlank())dateMode="today"
        if(foodOpened) loadItems()
    }
    fun loadItems() {radarCamera=null;radarMoved=false;val point=location ?: if(specialMode=="route")GuruLocation(0.0,0.0,"","none") else return;loadAt(point,activeRadius)}
    fun loadRadarViewport(camera:TravelCamera,width:Double,height:Double) {
        if(category!="buedchen")return
        radarCamera=camera;radarMoved=true
        loadAt(GuruLocation(camera.lat,camera.lng,"","map"),RadarGeometry.radiusKm(camera.lat,camera.zoom,width,height))
    }
    private fun loadAt(point:GuruLocation,km:Double) {
        if(!ready)return
        closeDetail();val token=++generation;mediaAttempts.clear();filterGeneration++;loading=true;error="";results=emptyList();radarMapItems=emptyList();total=0;tags=emptyList();subtypes=emptyList();detail=null;effectiveRadius=null
        val selectedCategory=category;val selectedSpecial=specialMode
        if(category in listOf("buedchen","wurst"))visible=radarPageSize()
        pool.execute {
            val r=runCatching {
                if(selectedSpecial=="route")http("places/industrial-route"+(if(point.mode=="none")"" else "?lat=${point.lat}&lng=${point.lng}"),null)
                else if(selectedCategory=="wurst")loadWurst(point,km)
                else if(selectedCategory=="buedchen")http("places/buedchen",JSONObject().put("lat",point.lat).put("lng",point.lng).put("radiusKm",km).put("limit",200))
                else if(selectedCategory=="events")http("events",JSONObject().put("lat",point.lat).put("lng",point.lng).put("city",point.city).put("maxRadiusKm",km))
                else http("places/home",JSONObject().put("category",selectedCategory).put("lat",point.lat).put("lng",point.lng).put("radiusKm",km).put("limit",1500))
            }
            handler.post {
                if(token!=generation || disposed)return@post
                r.onSuccess { payload ->
                    radarStats=JSONObject().put("verifiedTotal",payload.optInt("verifiedTotal")).put("radarTotal",payload.optInt("radarTotal",payload.optInt("verifiedTotal")))
                    val elements=payload.optJSONArray(if(selectedCategory=="events"||selectedSpecial=="route")"items" else "elements") ?: JSONArray()
                    effectiveRadius=payload.optDouble("effectiveRadiusKm").takeIf { it.isFinite() }
                    engine.call("NativeFood.reset(${JSONObject.quote(selectedCategory)},${JSONObject.quote(selectedSpecial)})") { reset ->
                        if(token!=generation || disposed)return@call
                        if(reset.isFailure) failLoad() else ingest(elements,0,point,km,token)
                    }
                }.onFailure { loading=false;error=it.message ?: "Die Liste konnte nicht geladen werden." }
            }
        }
    }
    private fun ingest(elements: JSONArray, offset: Int, point: GuruLocation, km:Double, token: Int) {
        if(token!=generation || disposed)return
        if(offset>=elements.length()) {
            engine.call("NativeFood.commit(${point.json()},$km)") { r ->
                if(token!=generation || disposed)return@call
                r.onSuccess { count ->
                    if(specialMode=="halloween" && (count as? Number)?.toInt()==0 && (km==5.0||km==10.0)) {
                        changeRadius(if(km==5.0)10.0 else 25.0);return@onSuccess
                    }
                    if(specialMode.isNotBlank()) {
                        engine.call("NativeFood.specialSelect(${JSONObject.quote(specialFilter)},${JSONObject.quote(specialCity)})") {if(token==generation&&!disposed){loading=false;refresh();if(category=="events")resolveEventMedia(token)}}
                    } else if(category=="buedchen") {
                        val keys=radarFilters.keys().asSequence().filter {radarFilters.optBoolean(it)}.toList()
                        engine.call("(function(){"+keys.joinToString(""){"NativeFood.radarToggle("+JSONObject.quote(it)+");"}+"return true;})()") {if(token==generation && !disposed){loading=false;refresh()}}
                    } else if(category=="wurst") {engine.call("NativeFood.radarSetSort(${JSONObject.quote(radarSort)})") {if(token==generation&&!disposed){loading=false;refresh()}}} else {loading=false;if(category=="events"&&dateMode=="today")setDatePreset("today") else refresh();if(category=="events")resolveEventMedia(token)}
                }.onFailure { failLoad() }
            };return
        }
        val batch=JSONArray();for(i in offset until minOf(offset+80,elements.length()))batch.put(elements.get(i))
        engine.call("NativeFood.append($batch)") { r ->
            if(token!=generation || disposed)return@call
            r.onSuccess { ingest(elements,offset+80,point,km,token) }.onFailure { failLoad() }
        }
    }
    private fun failLoad() { loading=false;error="Die Daten konnten nicht verarbeitet werden." }
    fun setFilters(text: String=query, selectedTag: String=tag, selectedSubtype: String=subtype) {
        query=text;tag=selectedTag;subtype=selectedSubtype;visible=radarPageSize();refresh()
    }
    private fun radarPageSize()=when(category){"buedchen"->20;"wurst"->18;else->24}
    fun more() { visible+=radarPageSize();refresh() }
    fun closeDetail() { searchDetailActive=false;searchDetailOpening=false;searchDetailError="";detailGeneration++;detail=null;placeEvents=emptyList();placeEventsError="";stories=emptyList();storiesLoading=false;storiesError="";relatedLabel="";relatedCount=0;relatedItems=emptyList();relatedLoading=false;detailError="" }
    fun openDetail(id: String) {
        val token=generation;val selection=++detailGeneration
        engine.call("NativeFood.detail(${JSONObject.quote(id)})") { r ->
            if(token==generation && selection==detailGeneration && !disposed)r.onSuccess { detail=it as? JSONObject;relatedItems=emptyList();detailError="";if(detail?.text("category")=="events")loadRelated(id,selection) else if(detail?.text("category")!="buedchen")loadPlaceExtras(id) }
        }
    }
    fun tagFromDetail(slug: String) { val target=detail?.text("category").orEmpty();closeDetail();if(target in listOf("food","events","highlights","culture","activities","buedchen","wurst")&&(target!=category||specialMode.isNotBlank()))openCategory(target);dateMode="";dateFrom="";dateTo="";dateLabel="";setFilters(text="",selectedTag=slug,selectedSubtype="") }
    private fun refresh() {
        if(loading || !ready)return
        val token=++filterGeneration;val context=generation
        engine.call("NativeFood.query(${JSONObject.quote(query)},${JSONObject.quote(tag)},${JSONObject.quote(subtype)},$visible,${JSONObject.quote(dateFrom)},${JSONObject.quote(dateTo)})") { r ->
            if(token!=filterGeneration || context!=generation || disposed)return@call
            r.onSuccess { data ->
                val p=data as JSONObject;specialInfo=p.optJSONObject("specialInfo") ?: JSONObject();results=p.getJSONArray("items").objects();radarMapItems=p.optJSONArray("mapItems")?.objects().orEmpty();total=p.getInt("total");tags=p.getJSONArray("tags").objects()
                results.filter {it.optJSONObject("media")?.text("url").isNullOrBlank()}.forEach { resolveMedia(it.text("id"),"") }
                val s=p.getJSONArray("subtypes");subtypes=(0 until s.length()).map { s.getString(it) };error=""
            }.onFailure { error="Die Filter konnten nicht angewendet werden." }
        }
    }
    fun toggleRadarFilter(key:String) {
        val token=generation
        engine.call("NativeFood.radarToggle(${JSONObject.quote(key)})") {r->if(token==generation && !disposed)(r.getOrNull() as? JSONObject)?.let {radarFilters=it;visible=20;refresh()}}
    }
    fun sortRadar(value:String) {
        radarSort=value
        engine.call("NativeFood.radarSetSort(${JSONObject.quote(value)})") {refresh()}
    }
    fun clearRadarFilters() {
        radarFilters=JSONObject();radarSort="distance";query="";tag="";subtype="";visible=radarPageSize()
        engine.call("NativeFood.radarClear()") {refresh()}
    }
    fun submitWurstSuggestion(input:JSONObject,callback:(Result<JSONObject>)->Unit) {
        if(input.text("name").isBlank()){callback(Result.failure(IllegalArgumentException("Bitte den Namen der Bude angeben.")));return}
        pool.execute {val result=runCatching {http("places/wurst/submit",input)};handler.post {if(!disposed)callback(result)}}
    }
    fun clearFilters() { dateMode="";dateFrom="";dateTo="";dateLabel="";setFilters("","","") }
    fun setDatePreset(mode:String) {
        val token=generation
        engine.call("NativeFood.preset(${JSONObject.quote(mode)})") { r->
            if(token!=generation || disposed)return@call
            r.onSuccess { data->val p=data as JSONObject;dateMode=mode;dateFrom=p.text("from");dateTo=p.text("to");dateLabel=p.text("label");visible=24;refresh() }
        }
    }
    fun setDateRange(from:String,to:String) {
        val end=to.ifBlank {from};val a=minOf(from,end);val b=maxOf(from,end);val token=generation
        engine.call("NativeFood.rangeLabel(${JSONObject.quote(a)},${JSONObject.quote(b)})") { r->
            if(token!=generation || disposed)return@call
            r.onSuccess {dateMode="range";dateFrom=a;dateTo=b;dateLabel=it as? String ?: "";visible=24;refresh()}
        }
    }
    private fun resolveEventMedia(token:Int) {
        engine.call("NativeFood.eventMediaRequest()") { r->
            if(token!=generation || disposed)return@call
            val request=r.getOrNull() as? JSONObject ?: return@call
            if(request.text("url")!=API_ROOT+"event-media-diversity")return@call
            val body=request.optJSONObject("body") ?: return@call
            mediaPool.execute {
                if(token!=generation)return@execute
                val response=runCatching {http("event-media-diversity",body)}
                handler.post {
                    if(token!=generation || disposed)return@post
                    engine.call("NativeFood.eventMediaResponse(${response.getOrNull() ?: "null"})") {
                        if(token==generation && !disposed)refresh()
                    }
                }
            }
        }
    }
    private fun loadRelated(id:String,selection:Int) {
        val token=generation;relatedLoading=true
        engine.call("NativeFood.relatedRequest(${JSONObject.quote(id)})") { r->
            if(token!=generation || selection!=detailGeneration || disposed)return@call
            val url=(r.getOrNull() as? JSONObject)?.text("url").orEmpty()
            val path=url.removePrefix(API_ROOT)
            if(!url.startsWith(API_ROOT) || !Regex("events/[0-9]+/related").matches(path)){relatedLoading=false;return@call}
            pool.execute {
                val response=runCatching {http(path,null)}
                handler.post {
                    if(token!=generation || selection!=detailGeneration || disposed)return@post
                    relatedLoading=false
                    response.onSuccess {data->
                        relatedLabel=data.optJSONObject("series")?.text("label").orEmpty();relatedCount=data.optInt("count")
                        engine.call("NativeFood.relatedRows($data)") { rows->
                            if(token==generation && selection==detailGeneration && !disposed)relatedItems=(rows.getOrNull() as? JSONArray)?.objects().orEmpty()
                        }
                    }
                }
            }
        }
    }
    fun openRelated(eventId:Long) {
        if(eventId<=0)return
        val point=location;val token=generation;val selection=++detailGeneration;relatedLoading=true;detailError=""
        pool.execute {
            val response=runCatching {http("share-item?token=e$eventId&category=events",null)}
            handler.post {
                if(token!=generation || selection!=detailGeneration || disposed)return@post
                response.onSuccess {data->
                    val item=data.optJSONObject("item")
                    if(data.text("kind")!="event" || item==null){relatedLoading=false;detailError="Der Termin konnte nicht geladen werden.";return@onSuccess}
                    engine.call("NativeFood.externalDetail($item,${point?.json() ?: "null"})") { result->
                        if(token!=generation || selection!=detailGeneration || disposed)return@call
                        relatedLoading=false
                        result.onSuccess {next->detail=next as? JSONObject;placeEvents=emptyList();stories=emptyList();relatedItems=emptyList();detail?.text("id")?.let {loadRelated(it,selection)}}
                            .onFailure {detailError="Der Termin konnte nicht aufbereitet werden."}
                    }
                }.onFailure {relatedLoading=false;detailError="Der Termin konnte nicht geladen werden. Bitte erneut versuchen."}
            }
        }
    }
    private fun mediaOutcome(error:Throwable?):MediaAttemptGate.Outcome {
        val transient=error is java.io.IOException || (error is ApiFailure && ImageTransportPolicy.transientStatus(error.status))
        return if(transient)MediaAttemptGate.Outcome.TRANSIENT_FAILURE else MediaAttemptGate.Outcome.NO_IMAGE
    }
    private fun finishMedia(ticket:MediaAttemptGate.Ticket,token:Int,failedUrl:String,outcome:MediaAttemptGate.Outcome) {
        if(token!=generation||disposed)return
        if(mediaAttempts.finish(ticket,outcome))handler.postDelayed({
            if(token==generation&&!disposed)resolveMedia(ticket.id,failedUrl)
        },MediaAttemptGate.RETRY_DELAY_MS+50)
    }
    fun photoFailureHandler(id:String):(String)->Unit {
        val token=generation
        return {failed->if(token==generation&&!disposed)resolveMedia(id,failed)}
    }
    fun resolveMedia(id:String,failedUrl:String) {
        val mediaCategory=if(searchDetailActive)detail?.text("category").orEmpty().ifBlank {category} else category
        if(mediaCategory=="wurst")return
        val ticket=mediaAttempts.begin(id,failedUrl.isNotBlank()) ?: return
        val token=generation
        if(mediaCategory in listOf("highlights","culture","activities","buedchen")) {
            engine.call("NativeFood.highlightsMediaRequest(${JSONObject.quote(id)},${JSONObject.quote(failedUrl)})") { r->
                if(token!=generation||disposed)return@call
                val request=r.getOrNull() as? JSONObject
                if(request!=null)continueHighlightsMedia(id,request,token,0,ticket,failedUrl,false)
                else finishMedia(ticket,token,failedUrl,if(r.isFailure)MediaAttemptGate.Outcome.TRANSIENT_FAILURE else MediaAttemptGate.Outcome.NO_IMAGE)
            }
            return
        }
        engine.call("NativeFood.mediaRequest(${JSONObject.quote(id)},${JSONObject.quote(failedUrl)})") { r ->
            if(token!=generation || disposed)return@call
            val request=r.getOrNull() as? JSONObject
            if(request==null || request.text("url")!=API_ROOT+"food-media" || request.optJSONObject("body")==null) {
                finishMedia(ticket,token,failedUrl,if(r.isFailure)MediaAttemptGate.Outcome.TRANSIENT_FAILURE else MediaAttemptGate.Outcome.NO_IMAGE);return@call
            }
            val body=request.getJSONObject("body")
            mediaPool.execute {
                if(token!=generation)return@execute
                val response=runCatching {http("food-media",body)}
                handler.post {
                    if(token!=generation || disposed)return@post
                    response.onSuccess { data ->
                        engine.call("NativeFood.mediaResponse(${JSONObject.quote(id)},$data,${JSONObject.quote(failedUrl)})") { applied ->
                            if(token!=generation || disposed)return@call
                            val didApply=applied.getOrNull()==true
                            finishMedia(ticket,token,failedUrl,if(didApply)MediaAttemptGate.Outcome.APPLIED else MediaAttemptGate.Outcome.NO_IMAGE)
                            if(didApply) {
                                refresh()
                                if(detail?.text("id")==id)refreshDetail(id)
                            }
                        }
                    }.onFailure {finishMedia(ticket,token,failedUrl,mediaOutcome(it))}
                }
            }
        }
    }
    private fun continueHighlightsMedia(id:String,request:JSONObject,token:Int,step:Int,ticket:MediaAttemptGate.Ticket,failedUrl:String,hadTransientFailure:Boolean) {
        if(token!=generation || disposed)return
        val path=request.text("url").removePrefix(API_ROOT)
        val body=request.optJSONObject("body")
        if(step>1||request.text("url")!=API_ROOT+path||path !in listOf("place-info","food-media")||body==null) {
            finishMedia(ticket,token,failedUrl,MediaAttemptGate.Outcome.NO_IMAGE);return
        }
        mediaPool.execute {
            if(token!=generation)return@execute
            val response=runCatching {http(path,body)}
            val transient=hadTransientFailure || (response.isFailure&&mediaOutcome(response.exceptionOrNull())==MediaAttemptGate.Outcome.TRANSIENT_FAILURE)
            handler.post {
                if(token!=generation || disposed)return@post
                engine.call("NativeFood.highlightsMediaAdvance(${JSONObject.quote(id)},${response.getOrNull() ?: "null"})") {
                    if(token==generation&&!disposed)checkHighlightsMedia(id,token,step,ticket,failedUrl,transient,0)
                }
            }
        }
    }
    private fun checkHighlightsMedia(id:String,token:Int,step:Int,ticket:MediaAttemptGate.Ticket,failedUrl:String,hadTransientFailure:Boolean,poll:Int) {
        if(token!=generation||disposed)return
        engine.call("NativeFood.highlightsMediaState(${JSONObject.quote(id)})") state@{ result->
            if(token!=generation||disposed)return@state
            val state=result.getOrNull() as? JSONObject
            if(state==null) {
                if(poll<3)handler.postDelayed({checkHighlightsMedia(id,token,step,ticket,failedUrl,hadTransientFailure,poll+1)},50)
                else finishMedia(ticket,token,failedUrl,MediaAttemptGate.Outcome.TRANSIENT_FAILURE)
                return@state
            }
            val next=state.optJSONObject("request")
            if(next!=null){continueHighlightsMedia(id,next,token,step+1,ticket,failedUrl,hadTransientFailure);return@state}
            val applied=state.optBoolean("applied")
            finishMedia(ticket,token,failedUrl,when {applied->MediaAttemptGate.Outcome.APPLIED;hadTransientFailure->MediaAttemptGate.Outcome.TRANSIENT_FAILURE;else->MediaAttemptGate.Outcome.NO_IMAGE})
            if(applied){refresh();if(detail?.text("id")==id)refreshDetail(id)}
        }
    }
    private fun refreshDetail(id:String) {
        val token=generation;val selection=detailGeneration
        engine.call("NativeFood.detail(${JSONObject.quote(id)})") {r->if(token==generation && selection==detailGeneration && !disposed)(r.getOrNull() as? JSONObject)?.let {detail=it}}
    }
    fun detailWork(job:()->Any,callback:(Result<Any>)->Unit) {
        val token=generation;val selection=detailGeneration
        pool.execute {val result=runCatching(job);handler.post {if(token==generation && selection==detailGeneration && !disposed)callback(result)}}
    }
    fun detailApi(path:String,body:JSONObject?=null,callback:(Result<JSONObject>)->Unit) {
        detailWork({http(path,body)}) {r->callback(r.map {it as JSONObject})}
    }
    private fun loadPlaceExtras(id:String) {
        val token=generation;val selection=detailGeneration
        if(detail?.text("category")=="food")engine.call("NativeFood.foodInfoRequest(${JSONObject.quote(id)})") {r->
            if(token!=generation || selection!=detailGeneration || disposed)return@call
            val request=r.getOrNull() as? JSONObject ?: return@call
            if(request.text("url")!=API_ROOT+"food-info")return@call
            detailApi("food-info",request.optJSONObject("body")) {data->
                data.onSuccess {value->engine.call("NativeFood.foodInfoResponse(${JSONObject.quote(id)},$value)") {result->if(token==generation && selection==detailGeneration && !disposed)(result.getOrNull() as? JSONObject)?.let {detail=it}}}
            }
        }
        engine.call("NativeFood.placeEventsRequest(${JSONObject.quote(id)})") {r->
            if(token!=generation || selection!=detailGeneration || disposed)return@call
            val request=r.getOrNull() as? JSONObject
            if(request==null){readPlaceEvents(id,token,selection);return@call}
            if(request.text("url")!=API_ROOT+"events")return@call
            val ticket=request.optInt("ticket")
            pool.execute {
                val data=runCatching {http("events",request.optJSONObject("body"))}.getOrNull()
                handler.post {
                    if(token==generation && !disposed)engine.call("NativeFood.placeEventsResponse($ticket,${data ?: "null"})") {}
                }
            }
            readPlaceEvents(id,token,selection)
        }
        loadStories()
    }
    private fun readPlaceEvents(id:String,token:Int,selection:Int,attempt:Int=0) {
        engine.call("NativeFood.placeEventsState(${JSONObject.quote(id)})") {r->
            if(token!=generation || selection!=detailGeneration || disposed)return@call
            val state=r.getOrNull() as? JSONObject
            if(state!=null){placeEvents=state.optJSONArray("items")?.objects().orEmpty();placeEventsError=state.text("error")}
            else if(attempt<160)handler.postDelayed({if(token==generation && selection==detailGeneration && !disposed)readPlaceEvents(id,token,selection,attempt+1)},250)
            else placeEventsError="Events konnten gerade nicht geladen werden."
        }
    }
    fun loadStories() {
        val item=detail ?: return;if(item.text("category")=="events")return
        storiesLoading=true;storiesError=""
        val id=java.net.URLEncoder.encode(item.text("canonicalId"),"UTF-8")
        detailApi("places/$id/community-stories") {r->storiesLoading=false;r.onSuccess {stories=it.optJSONArray("items")?.objects().orEmpty()}.onFailure {storiesError="Community-Beiträge konnten gerade nicht geladen werden."}}
    }
    fun isSaved(item:JSONObject)=savedItems.any {it.text("id")==item.text("canonicalId")}
    fun toggleSaved(item:JSONObject) {
        val id=item.text("id");val token=generation;val selection=detailGeneration
        engine.call("NativeFood.snapshot(${JSONObject.quote(id)})") {r->
            if(token!=generation || selection!=detailGeneration || disposed)return@call
            val entry=r.getOrNull() as? JSONObject ?: return@call
            val removing=savedStore.read().any {it.text("id")==entry.text("id")}
            savedItems=savedStore.edit {rows->if(removing)rows.removeAll {it.text("id")==entry.text("id")} else rows.add(entry)}
            if(removing){EventReminders.cancel(getApplication(),entry.text("id"));EventReminders.sync(getApplication())}
        }
    }
    fun removeSaved(id:String) {
        savedItems=savedStore.edit {rows->rows.removeAll {it.text("id")==id}}
        EventReminders.cancel(getApplication(),id);EventReminders.sync(getApplication())
    }
    fun openSaved(saved:JSONObject) = openSnapshot(saved,false)
    private fun openSnapshot(saved:JSONObject,fromSearch:Boolean) {
        closeDetail();searchDetailActive=fromSearch;searchDetailOpening=fromSearch;error="";val token=generation;val selection=detailGeneration
        fun failed(message:String) {if(fromSearch){searchDetailOpening=false;searchDetailError=message}else error=message}
        fun applySaved(entry:JSONObject) {
            engine.call("NativeFood.restoreSaved($entry,${location?.json() ?: "null"})") {r->
                if(token!=generation || selection!=detailGeneration || disposed)return@call
                r.onSuccess {searchDetailOpening=false;detail=it as? JSONObject;val id=detail?.text("id").orEmpty();if(detail?.text("category")=="events")loadRelated(id,selection) else loadPlaceExtras(id)}.onFailure {if(fromSearch)failed("Der Suchtreffer konnte nicht geöffnet werden.")}
            }
        }
        val descriptor=saved.optJSONObject("descriptor")
        if(descriptor!=null) {
            val path="share-item?token="+java.net.URLEncoder.encode(descriptor.text("token"),"UTF-8")+"&category="+java.net.URLEncoder.encode(descriptor.text("category"),"UTF-8")
            detailApi(path) {r->r.onSuccess {data->(data.optJSONObject("item") ?: data.optJSONObject("element"))?.let {applySaved(JSONObject(saved.toString()).put("raw",it))} ?: run {failed(if(fromSearch)"Der Suchtreffer ist nicht mehr verfügbar." else "Der gespeicherte Eintrag ist nicht verfügbar.")}}.onFailure {failed(if(fromSearch)"Der Suchtreffer konnte nicht geladen werden. Bitte erneut versuchen." else "Der gespeicherte Eintrag konnte nicht geladen werden.")}}
        } else applySaved(saved)
    }
    fun shareDetail(item:JSONObject,mode:String,message:String,duration:String,callback:(JSONObject)->Unit) {
        val selection=detailGeneration
        engine.call("NativeFood.sharePayload(${JSONObject.quote(item.text("id"))},${JSONObject.quote(mode)},${JSONObject.quote(message)},${JSONObject.quote(duration)})") {r->if(selection==detailGeneration && !disposed)(r.getOrNull() as? JSONObject)?.let(callback)}
    }
    fun submitDetailForm(item:JSONObject,mode:String,input:JSONObject,photos:List<UploadPhoto>,callback:(Result<JSONObject>)->Unit) {
        val token=generation;val selection=detailGeneration
        engine.call("NativeFood.formPayload(${JSONObject.quote(item.text("id"))},${JSONObject.quote(mode)},$input)") {r->
            if(token!=generation || selection!=detailGeneration || disposed)return@call
            val payload=r.getOrNull() as? JSONObject
            if(payload==null || payload.text("error").isNotBlank()){callback(Result.failure(IllegalArgumentException(payload?.text("error") ?: "Die Angaben konnten nicht geprüft werden.")));return@call}
            val fields=payload.optJSONObject("fields") ?: return@call
            detailWork({CommunityTransport.submit(if(mode=="story")"places/community-story" else "places/contribute",fields,photos)}) {result->callback(result.map {it as JSONObject})}
        }
    }
    private class ApiFailure(val status:Int,val payload:JSONObject):Exception(payload.text("message").ifBlank {"Die Anfrage ist fehlgeschlagen (HTTP $status)."})
    private fun loadWurst(point:GuruLocation,km:Double):JSONObject {
        val body=JSONObject().put("lat",point.lat).put("lng",point.lng).put("radiusKm",km).put("limit",600)
        try {return http("places/wurst",body)} catch(e:ApiFailure) {
            if(e.status!=404 && e.payload.text("code")!="rest_no_route" && e.payload.text("error")!="rest_no_route")throw e
            val url="https://www.ruhrpottguru.de/wp-admin/admin-ajax.php?action=rg_wurst_frontend&lat=${point.lat}&lng=${point.lng}&radiusKm=$km&limit=600"
            val conn=URL(url).openConnection() as HttpURLConnection
            try {conn.connectTimeout=15000;conn.readTimeout=25000;conn.instanceFollowRedirects=false
                val code=conn.responseCode;val result=JSONObject((if(code in 200..299)conn.inputStream else conn.errorStream).bufferedReader().use {it.readText()})
                val data=result.optJSONObject("data");check(code in 200..299&&result.optBoolean("success")&&data!=null&&(!data.has("ok")||data.optBoolean("ok"))){"Wurst-Daten konnten nicht geladen werden."};return data!!
            }finally{conn.disconnect()}
        }
    }
    private fun http(path: String, body: JSONObject?, nonce: String? = null): JSONObject {
        val conn=URL(API_ROOT+path).openConnection() as HttpURLConnection
        try {
            conn.connectTimeout=15000;conn.readTimeout=25000;conn.instanceFollowRedirects=false;conn.useCaches=false
            if(path.startsWith("address-location")||path=="feedback-token")conn.setRequestProperty("Cache-Control","no-store")
            conn.setRequestProperty("Accept","application/json")
            if(body!=null) { conn.requestMethod="POST";conn.doOutput=true;conn.setRequestProperty("Content-Type","application/json; charset=utf-8");nonce?.let { conn.setRequestProperty("X-RPG-Address-Token",it) };conn.outputStream.use { it.write(body.toString().toByteArray(Charsets.UTF_8)) } }
            val code=conn.responseCode
            val stream=if(code in 200..299)conn.inputStream else conn.errorStream
            val raw=stream?.bufferedReader()?.use {it.readText()} ?: "{}"
            val p=runCatching {JSONObject(raw)}.getOrElse {if(code !in 200..299)JSONObject() else throw it}
            if(code !in 200..299 || (p.has("ok") && !p.optBoolean("ok")))throw ApiFailure(code,p)
            return p
        } finally {conn.disconnect()}
    }
    override fun onCleared() { prefs.unregisterOnSharedPreferenceChangeListener(savedListener);timeTravel.close();disposed=true;generation++;resolveGeneration++;pool.shutdownNow();mediaPool.shutdownNow();handler.removeCallbacksAndMessages(null);engine.destroy() }
}
