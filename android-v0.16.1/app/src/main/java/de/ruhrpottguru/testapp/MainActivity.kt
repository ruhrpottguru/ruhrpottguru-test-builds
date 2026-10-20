package de.ruhrpottguru.testapp

import android.Manifest
import android.content.ActivityNotFoundException
import android.content.Intent
import android.content.pm.PackageManager
import android.net.Uri
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.BackHandler
import androidx.activity.compose.setContent
import androidx.activity.result.contract.ActivityResultContracts
import androidx.lifecycle.ViewModelProvider
import androidx.compose.foundation.*
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalConfiguration
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.viewinterop.AndroidView
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import org.json.JSONObject
import android.provider.CalendarContract
import java.time.LocalDate
import java.time.ZoneOffset
import java.time.format.DateTimeFormatter

private val Orange=Color(0xFFF59E0B)
private val Background=Color(0xFF07111F)
private val Panel=Color(0xFF122034)
private val Light=Color(0xFFF8FAFC)
private val Muted=Color(0xFFB6C2D3)
private val Scheme=darkColorScheme(primary=Orange,onPrimary=Background,background=Background,surface=Panel,onSurface=Light,onBackground=Light)

class MainActivity:ComponentActivity() {
    private lateinit var model:FoodController
    private lateinit var gps:FreshGps
    private var gpsRadius:Double?=null
    private val permission=registerForActivityResult(ActivityResultContracts.RequestMultiplePermissions()) {
        val km=gpsRadius ?: return@registerForActivityResult
        if(checkSelfPermission(Manifest.permission.ACCESS_COARSE_LOCATION)==PackageManager.PERMISSION_GRANTED || checkSelfPermission(Manifest.permission.ACCESS_FINE_LOCATION)==PackageManager.PERMISSION_GRANTED) startFix(km)
        else {gpsRadius=null;model.gpsFailure("Standortfreigabe abgelehnt. Bitte Stadt, PLZ oder Adresse nutzen.")}
    }
    override fun onCreate(savedInstanceState:Bundle?) {
        super.onCreate(savedInstanceState)
        model=ViewModelProvider(this)[FoodController::class.java];gps=FreshGps(this)
        if(model.timeTravel.openSerial==0)intent?.dataString?.let {model.timeTravel.receiveLink(it)}
        setContent {MaterialTheme(colorScheme=Scheme) {GuruApp(model,::requestGps,::cancelGps)}}
    }
    override fun onNewIntent(intent:Intent) {super.onNewIntent(intent);setIntent(intent);intent.dataString?.let {model.timeTravel.receiveLink(it)}}
    private fun requestGps(km:Double) {
        gps.cancel();gpsRadius=km;model.beginGps()
        if(checkSelfPermission(Manifest.permission.ACCESS_COARSE_LOCATION)==PackageManager.PERMISSION_GRANTED || checkSelfPermission(Manifest.permission.ACCESS_FINE_LOCATION)==PackageManager.PERMISSION_GRANTED)startFix(km)
        else permission.launch(arrayOf(Manifest.permission.ACCESS_FINE_LOCATION,Manifest.permission.ACCESS_COARSE_LOCATION))
    }
    private fun startFix(km:Double) {gps.request({lat,lng->gpsRadius=null;model.acceptGps(lat,lng,km)},{msg->gpsRadius=null;model.gpsFailure(msg)})}
    private fun cancelGps() {gpsRadius=null;gps.cancel()}
    override fun onStop() {
        if(gps.active) {cancelGps();model.gpsFailure("GPS-Suche pausiert. Bitte erneut starten.")}
        super.onStop()
    }
    override fun onDestroy() {if(gpsRadius!=null)model.gpsFailure("GPS-Suche beendet. Bitte erneut starten.");cancelGps();super.onDestroy()}
}

@Composable
private fun GuruApp(c:FoodController,onGps:(Double)->Unit,cancelGps:()->Unit) {
    var screen by rememberSaveable {mutableStateOf("Start")}
    var menu by remember {mutableStateOf(false)}
    var expanded by remember {mutableStateOf<String?>(null)}
    var chooseLocation by rememberSaveable {mutableStateOf(false)}
    val resultsState=rememberLazyListState()
    val ctx=LocalContext.current
    fun open(name:String) {
        menu=false
        expanded=null
        if(!AppNavigation.enabled(name) && name!="Entdecken" && name!="Food & Drinks")return
        if(name !in listOf("Route der Industriekultur","Halloween im Pott","Weihnachten im Pott","Standort & Radius"))c.leaveSpecial()
        when(name) {
            "Start","Entdecken"->{screen="Start";c.closeDetail()}
            "Food","Food & Drinks"->{screen="Food";c.closeDetail();c.openFood();if(c.location==null)chooseLocation=true}
            "Events"->{screen="Events";c.closeDetail();c.openCategory("events");if(c.location==null)chooseLocation=true}
            "Highlights"->{screen="Highlights";c.closeDetail();c.openCategory("highlights");if(c.location==null)chooseLocation=true}
            "Kultur"->{screen="Kultur";c.closeDetail();c.openCategory("culture");if(c.location==null)chooseLocation=true}
            "Aktivitäten"->{screen="Aktivitäten";c.closeDetail();c.openCategory("activities");if(c.location==null)chooseLocation=true}
            "Route der Industriekultur"->{screen="Industriekultur";c.closeDetail();c.openSpecial("route")}
            "Halloween im Pott"->{screen="Halloween";c.closeDetail();c.openSpecial("halloween");if(c.location==null)chooseLocation=true}
            "Weihnachten im Pott"->{screen="Weihnachten";c.closeDetail();c.openSpecial("weihnachten");if(c.location==null)chooseLocation=true}
            "Zeitreise von oben"->{screen="Zeitreise";c.closeDetail()}
            "Trinkhallen-Radar"->{screen="Büdchen";c.closeDetail();c.openCategory("buedchen");if(c.location==null)chooseLocation=true}
            "Hier geht’s um die Wurst"->{screen="Imbiss";c.closeDetail();c.openCategory("wurst");if(c.location==null)chooseLocation=true}
            "Merkliste"->{screen="Merkliste";c.closeDetail()}
            "Pott-Quiz"->{screen="Pott-Quiz";c.closeDetail()}
            "Standort & Radius"->chooseLocation=true
            in AppNavigation.infoPages->{screen=name;c.closeDetail()}
            else->return
        }
    }
    LaunchedEffect(c.timeTravel.openSerial) {if(c.timeTravel.openRequested){c.leaveSpecial();screen="Zeitreise";c.closeDetail();c.timeTravel.consumeOpenRequest()}}
    LaunchedEffect(c.ready) {if(c.ready && (c.needsFreshGps || c.location==null) && c.timeTravel.openSerial==0){chooseLocation=true;onGps(c.radius)}}
    LaunchedEffect(c.contextSerial,c.category,c.query,c.tag,c.subtype,c.dateFrom,c.dateTo,c.radarFilters,c.radarSort,c.specialMode,c.specialFilter,c.specialCity) {if(screen in listOf("Food","Events","Highlights","Kultur","Aktivitäten","Büdchen","Imbiss","Industriekultur","Halloween","Weihnachten"))resultsState.scrollToItem(0)}
    LaunchedEffect(screen) {if(screen=="Industriekultur")c.openSpecial("route") else if(screen=="Halloween")c.openSpecial("halloween") else if(screen=="Weihnachten")c.openSpecial("weihnachten") else if(screen=="Food")c.openCategory("food") else if(screen=="Events")c.openCategory("events") else if(screen=="Highlights")c.openCategory("highlights") else if(screen=="Kultur")c.openCategory("culture") else if(screen=="Aktivitäten")c.openCategory("activities") else if(screen=="Büdchen")c.openCategory("buedchen") else if(screen=="Imbiss")c.openCategory("wurst")}
    BackHandler(menu) {menu=false;expanded=null}
    BackHandler(!menu && (c.detail!=null || screen!="Start")) {if(c.detail!=null)c.closeDetail() else if(screen=="Zeitreise"&&c.timeTravel.fullscreen)c.timeTravel.fullscreen=false else {c.leaveSpecial();screen="Start"}}
    Scaffold(containerColor=Background,topBar={
        if(screen!="Zeitreise"||!c.timeTravel.fullscreen)Column(Modifier.windowInsetsPadding(WindowInsets.statusBars)) {
            Row(Modifier.fillMaxWidth().heightIn(min=56.dp).padding(horizontal=8.dp),verticalAlignment=Alignment.CenterVertically) {
                if(screen!="Start" || c.detail!=null)IconButton(onClick={if(c.detail!=null)c.closeDetail() else {c.leaveSpecial();screen="Start"}}) {Text("‹",fontSize=34.sp,color=Orange,modifier=Modifier.semantics {contentDescription="Zurück"})}
                Image(painterResource(R.drawable.ruhrpottguru_logo),"RuhrpottGuru",Modifier.width(96.dp).height(52.dp),contentScale=ContentScale.Fit)
                Column(Modifier.weight(1f).padding(start=8.dp).clickable {chooseLocation=true}.padding(vertical=8.dp)) {
                    Text(c.location?.label ?: "Ort wählen",fontSize=14.sp,fontWeight=FontWeight.SemiBold,maxLines=1,overflow=TextOverflow.Ellipsis)
                    Text(c.location?.let {"${radiusText(c.activeRadius)} km Umkreis"} ?: "GPS · Stadt · PLZ",fontSize=12.sp,color=Muted,maxLines=1,overflow=TextOverflow.Ellipsis)
                }
                IconButton(onClick={chooseLocation=true},modifier=Modifier.size(48.dp).semantics {contentDescription="Standort und Radius auswählen"}) {
                    Canvas(Modifier.size(24.dp)) {
                        val stroke=2.dp.toPx()
                        val radius=6.5.dp.toPx()
                        drawCircle(Orange,radius,center,style=androidx.compose.ui.graphics.drawscope.Stroke(stroke))
                        drawCircle(Orange,1.8.dp.toPx(),center)
                        val inner=8.5.dp.toPx();val outer=11.5.dp.toPx()
                        drawLine(Orange,center.copy(x=center.x-inner),center.copy(x=center.x-outer),stroke)
                        drawLine(Orange,center.copy(x=center.x+inner),center.copy(x=center.x+outer),stroke)
                        drawLine(Orange,center.copy(y=center.y-inner),center.copy(y=center.y-outer),stroke)
                        drawLine(Orange,center.copy(y=center.y+inner),center.copy(y=center.y+outer),stroke)
                    }
                }
                Box {
                    if(c.detail!=null) {
                        FilledIconButton(onClick=c::closeDetail,colors=IconButtonDefaults.filledIconButtonColors(containerColor=Orange,contentColor=Background),modifier=Modifier.size(48.dp).semantics {contentDescription="Details schließen"}) {Text("×",fontSize=30.sp)}
                    } else IconButton(onClick={menu=!menu;expanded=null},modifier=Modifier.semantics {contentDescription=if(menu)"Hauptmenü schließen" else "Hauptmenü öffnen"}) {Text("☰",fontSize=28.sp,color=Orange)}
                    DropdownMenu(expanded=menu,onDismissRequest={menu=false},modifier=Modifier.widthIn(min=260.dp,max=330.dp).heightIn(max=(LocalConfiguration.current.screenHeightDp-180).coerceIn(100,420).dp)) {
                        AppNavigation.groups.forEach { group ->
                            DropdownMenuItem(text={Text(group.label+if(expanded==group.label)"  ▴" else "  ▾",color=Orange,fontWeight=FontWeight.Bold)},onClick={expanded=if(expanded==group.label)null else group.label})
                            if(expanded==group.label)group.entries.forEach {entry->
                                DropdownMenuItem(enabled=entry.available,text={Column(Modifier.padding(start=12.dp)){Text(entry.label);if(!entry.available)Text("In Vorbereitung",fontSize=11.sp)}},onClick={open(entry.label)})
                            }
                        }
                    }
                }
            }

        }
    },bottomBar={
        if(screen!="Zeitreise"||!c.timeTravel.fullscreen)NavigationBar(containerColor=Panel,windowInsets=WindowInsets.navigationBars) {
            NavigationBarItem(selected=screen=="Start",onClick={open("Start")},icon={Text("⌂",fontSize=25.sp)},label={Text("Entdecken")})
            NavigationBarItem(selected=screen=="Events",onClick={open("Events")},icon={Text("▦",fontSize=24.sp)},label={Text("Events")})
            NavigationBarItem(selected=screen=="Food",onClick={open("Food")},icon={Text("⌕",fontSize=25.sp)},label={Text("Food-Suche")})
            NavigationBarItem(selected=screen=="Merkliste",onClick={open("Merkliste")},icon={Text("♡",fontSize=25.sp)},label={Text("Mein Pott")})
        }
    }) { pad ->
        Box(Modifier.fillMaxSize().padding(pad)) {
            // Computation-only local JS host: no network, no page UI, no JS bridge.
            AndroidView(factory={c.engine.view},modifier=Modifier.size(1.dp).alpha(0f))
            when {
                c.detail!=null->c.detail?.let { place->key(place.text("id")){NativeDetailScreen(place,c,c.photoFailureHandler(place.text("id")),{slug->screen=when(c.detail?.text("category")){"events"->"Events";"food"->"Food";"highlights"->"Highlights";"culture"->"Kultur";"activities"->"Aktivitäten";"buedchen"->"Büdchen";"wurst"->"Imbiss";else->screen};c.tagFromDetail(slug)},{u->openExternal(ctx,u)})}}
                screen=="Zeitreise"->TimeTravelScreen(c,{chooseLocation=true},{u->openExternal(ctx,u)})
                screen in listOf("Industriekultur","Halloween","Weihnachten")->SpecialScreen(c,resultsState){chooseLocation=true}
                screen in AppNavigation.infoPages->InfoScreen(screen){openExternal(ctx,it)}
                screen=="Merkliste"->SavedScreen(c)
                screen=="Pott-Quiz"->PottQuizScreen(::open)
                (screen in listOf("Food","Events","Highlights","Kultur","Aktivitäten","Büdchen","Imbiss"))->FoodScreen(c,resultsState,{chooseLocation=true})
                else->HomeScreen(::open)
            }
        }
    }
    if(chooseLocation)LocationDialog(c,onGps,cancelGps,{cancelGps();c.cancelLocation();chooseLocation=false})
}

private fun radiusText(value:Double)=if(value%1==0.0)value.toInt().toString() else value.toString().replace('.',',')

@Composable private fun HomeScreen(open:(String)->Unit) {
    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(16.dp),verticalArrangement=Arrangement.spacedBy(12.dp)) {
        Text("Dein Pott. Deine Entdeckungen.",fontSize=25.sp,fontWeight=FontWeight.Bold)
        Text("Events, Food, Highlights, Kultur und Aktivitäten entdecken.",color=Muted)
        Row(horizontalArrangement=Arrangement.spacedBy(12.dp)) {
            PhotoTile("Events",R.drawable.tile_events,Modifier.weight(1f),true){open("Events")}
            PhotoTile("Food",R.drawable.tile_food,Modifier.weight(1f),true){open("Food")}
        }
        PhotoTile("Trinkhallen-Radar",R.drawable.tile_buedchen,Modifier.fillMaxWidth(),true){open("Trinkhallen-Radar")}
        Row(horizontalArrangement=Arrangement.spacedBy(12.dp)) {
            PhotoTile("Highlights",R.drawable.tile_highlights,Modifier.weight(1f),true){open("Highlights")}
            PhotoTile("Kultur",R.drawable.tile_kultur,Modifier.weight(1f),true){open("Kultur")}
        }
        PhotoTile("Route der Industriekultur",R.drawable.tile_industrial,Modifier.fillMaxWidth(),true){open("Route der Industriekultur")}
        PhotoTile("Aktivitäten",R.drawable.tile_aktiv,Modifier.fillMaxWidth(),true){open("Aktivitäten")}
        OutlinedButton(onClick={open("Pott-Quiz")},modifier=Modifier.fillMaxWidth()){Text("Pott-Quiz · Teste dein Ruhrpott-Wissen")}
        Text("Specials",fontSize=22.sp,fontWeight=FontWeight.Bold,modifier=Modifier.padding(top=8.dp))
        PhotoTile("Halloween im Pott",R.drawable.tile_events,Modifier.fillMaxWidth(),true){open("Halloween im Pott")}
        PhotoTile("Weihnachten im Pott",R.drawable.tile_kultur,Modifier.fillMaxWidth(),true){open("Weihnachten im Pott")}
        PhotoTile("Zeitreise von oben",R.drawable.tile_time_historical,Modifier.fillMaxWidth(),true){open("Zeitreise von oben")}
        Text("© Regionalverband Ruhr · RVR1952 · dl-de/by-2-0",fontSize=10.sp,color=Muted)
        PhotoTile("Hier geht’s um die Wurst",R.drawable.tile_food,Modifier.fillMaxWidth(),true){open("Hier geht’s um die Wurst")}
        Text("Zeitreise: historisches RVR-Luftbild · © Regionalverband Ruhr · dl-de/by-2-0. Andere Fotokacheln: KI-Symbolmotive.",color=Muted,fontSize=12.sp)
        TextButton(onClick={open("App-Info")}) {Text("Was ist in dieser Testversion enthalten?")}
    }
}
@Composable private fun PhotoTile(title:String,image:Int,modifier:Modifier,available:Boolean,onClick:()->Unit) {
    Box(modifier.height(174.dp).clip(RoundedCornerShape(20.dp)).clickable(enabled=available,onClick=onClick)) {
        Image(painterResource(image),null,Modifier.matchParentSize(),contentScale=ContentScale.Crop)
        Box(Modifier.matchParentSize().background(Brush.verticalGradient(listOf(Color.Transparent,Color.Black.copy(alpha=.65f)))))
        if(!available)Text("In Vorbereitung",Modifier.align(Alignment.TopEnd).padding(10.dp).background(Background.copy(alpha=.85f),RoundedCornerShape(8.dp)).padding(horizontal=9.dp,vertical=4.dp),color=Light,fontSize=11.sp)
        Text(title,Modifier.align(Alignment.BottomStart).padding(12.dp).background(Orange,RoundedCornerShape(9.dp)).padding(horizontal=10.dp,vertical=7.dp),color=Background,fontWeight=FontWeight.Bold,fontSize=16.sp)
    }
}

@OptIn(ExperimentalLayoutApi::class)
@Composable private fun SpecialScreen(c:FoodController,state:androidx.compose.foundation.lazy.LazyListState,location:()->Unit) {
    val route=c.specialMode=="route"
    val title=when(c.specialMode){"route"->"Route der Industriekultur";"halloween"->"Halloween im Pott";else->"Weihnachten im Pott"}
    val filters=c.specialInfo.optJSONArray("filters")?.objects().orEmpty()
    LazyColumn(Modifier.fillMaxSize(),state=state,contentPadding=PaddingValues(16.dp),verticalArrangement=Arrangement.spacedBy(12.dp)) {
        item {Column(verticalArrangement=Arrangement.spacedBy(10.dp)) {
            Row(verticalAlignment=Alignment.CenterVertically) {
                Text(title,fontSize=26.sp,fontWeight=FontWeight.Bold,modifier=Modifier.weight(1f))
                IconButton(onClick=c::loadItems,enabled=!c.loading&&(c.location!=null||route),modifier=Modifier.semantics {contentDescription="Special aktualisieren"}){Text("↻",fontSize=26.sp,color=Orange)}
            }
            Text(c.specialInfo.text("kicker").ifBlank {when(c.specialMode){"route"->"Ankerpunkte, Panoramen und Siedlungen im Ruhrgebiet";"halloween"->"Grusel, Halloween-Partys und Familien-Events";else->"Weihnachtsmärkte, Musik, Familie und Advent"}},color=Orange)
            if(route)Text("Alle offiziellen Primärstandorte, unabhängig vom Umkreis. Die Reihenfolge kommt wie im Web aus dem Routenbestand; bei gewähltem Ort mit Entfernungen.",color=Muted,fontSize=13.sp)
            if(!route&&c.location!=null)FlowRow(horizontalArrangement=Arrangement.spacedBy(6.dp),verticalArrangement=Arrangement.spacedBy(4.dp)) {
                listOf(1.0,2.5,5.0,10.0,25.0,50.0).forEach {km->FilterChip(selected=c.activeRadius==km,onClick={c.changeRadius(km)},label={Text(radiusText(km)+" km")})}
            }
            if(filters.isNotEmpty()) {
                if(route) {
                    ChipMenu("Typ",filters.find {it.text("key")==c.specialFilter}?.text("label").orEmpty(),filters.map {it.text("label")}) {label->if(label.isBlank())c.setSpecialFilter("all") else filters.find {it.text("label")==label}?.let {c.setSpecialFilter(it.text("key"))}}
                    val cities=c.specialInfo.optJSONArray("cities");val values=listOf("Alle Städte")+(cities?.let {a->(0 until a.length()).map {a.getString(it)}} ?: emptyList())
                    ChipMenu("Stadt",c.specialCity.ifBlank {"Alle Städte"},values) {c.setSpecialFilter(c.specialFilter,if(it.isBlank()||it=="Alle Städte")"" else it)}
                } else FlowRow(horizontalArrangement=Arrangement.spacedBy(6.dp),verticalArrangement=Arrangement.spacedBy(4.dp)) {
                    filters.forEach {f->FilterChip(selected=f.text("key")==c.specialFilter,onClick={c.setSpecialFilter(f.text("key"))},label={Text(f.text("label")+if(f.optInt("count")>0)" · ${f.optInt("count")}" else "")})}
                }
            }
        }}
        when {
            !c.ready->item {Loading("App wird vorbereitet …")}
            c.location==null&&!route->item {MessageCard("Wähle deinen Standort für dieses Special.","Standort auswählen",location)}
            c.loading->item {Loading("$title wird geladen …")}
            c.error.isNotBlank()->item {MessageCard(c.error,"Erneut laden",c::loadItems)}
            else->{
                item {Text("${c.total} ${if(route)"Standorte" else "Treffer"}"+(if(!route)" · ${radiusText(c.activeRadius)} km" else ""),fontWeight=FontWeight.SemiBold)}
                if(c.total==0)item {MessageCard(if(route)"Keine Standorte für diese Auswahl." else "Für diesen Filter gibt es hier derzeit keine bestätigten Treffer.",if(route)"Filter zurücksetzen" else "Standort & Umkreis",if(route)({c.setSpecialFilter("all","")}) else location)}
                items(c.results,key={it.text("id")}) {item->FoodCard(item,c.photoFailureHandler(item.text("id"))){c.openDetail(item.text("id"))}}
                if(c.results.size<c.total)item {Button(onClick=c::more,modifier=Modifier.fillMaxWidth()){Text("Weitere anzeigen")}}
            }
        }
    }
}

@Composable private fun FoodScreen(c:FoodController,state:androidx.compose.foundation.lazy.LazyListState,location:()->Unit) {
    LazyColumn(Modifier.fillMaxSize(),state=state,contentPadding=PaddingValues(16.dp),verticalArrangement=Arrangement.spacedBy(12.dp)) {
        item {
            Column(verticalArrangement=Arrangement.spacedBy(10.dp)) {
                Row(Modifier.fillMaxWidth(),horizontalArrangement=Arrangement.SpaceBetween,verticalAlignment=Alignment.CenterVertically) {
                    Text(when(c.category){"events"->"Events";"highlights"->"Highlights";"culture"->"Kultur & Szene";"activities"->"Aktivitäten";"buedchen"->"Trinkhallen-Radar";"wurst"->"Hier geht’s um die Wurst";else->"Food & Drinks"},fontSize=28.sp,fontWeight=FontWeight.Bold,modifier=Modifier.weight(1f))
                    IconButton(onClick=c::loadItems,enabled=!c.loading && c.location!=null,modifier=Modifier.semantics {contentDescription="Ergebnisse aktualisieren"}) {Text("↻",fontSize=26.sp,color=Orange)}
                }
                Text(when(c.category){"events"->"Konzerte, Märkte, Ausstellungen & mehr";"highlights"->"Besondere Orte, Industriekultur & Ausblicke";"culture"->"Museen, Streetart, Theater & Kleinkunst";"activities"->"Action, Wasser, Outdoor & besondere Erlebnisse";"buedchen"->"Gemischte Tüte, kalte Pulle, netter Schnack.";"wurst"->"Currywurst, Pommes und ehrliche Imbisskultur im Pott.";else->"Gastronomie, Cafés, Buden & Bars"},color=Muted)
                if(c.location!=null && c.ready) {
                    if(c.category!="buedchen")OutlinedTextField(c.query,{c.setFilters(text=it)},label={Text(when(c.category){"events"->"Events durchsuchen";"highlights"->"Highlights durchsuchen";"culture"->"Kultur durchsuchen";"activities"->"Aktivitäten durchsuchen";"wurst"->"Bude, Stadt oder Spezialität suchen";else->"In Food suchen"})},singleLine=true,modifier=Modifier.fillMaxWidth(),enabled=!c.loading)
                    if(c.category in listOf("buedchen","wurst"))RadarFilters(c) else {
                    if(c.category=="events")EventDateFilters(c)
                    ChipMenu("Art",c.subtype,c.subtypes){c.setFilters(selectedSubtype=it)}
                    ChipMenu("Thema",c.tags.find {it.text("slug")==c.tag}?.text("label").orEmpty(),c.tags.map {it.text("label")}) { label ->c.setFilters(selectedTag=c.tags.find {it.text("label")==label}?.text("slug").orEmpty())}
                    if(c.query.isNotEmpty() || c.tag.isNotEmpty() || c.subtype.isNotEmpty() || c.dateFrom.isNotEmpty())TextButton(onClick=c::clearFilters){Text("Filter zurücksetzen")}
                    }
                }
            }
        }
        when {
            !c.ready->item {if(c.error.isBlank())Loading("App wird vorbereitet …") else MessageCard(c.error,"Erneut starten",null)}
            c.location==null->item {MessageCard("Wähle deinen Standort, um Angebote in deinem Umkreis zu entdecken.","Standort auswählen",location)}
            c.resolving->item {Loading("Standort wird ermittelt …")}
            c.loading->item {Loading(when(c.category){"events"->"Events in deinem Umkreis werden geladen …";"highlights"->"Highlights in deinem Umkreis werden geladen …";"culture"->"Kultur in deinem Umkreis wird geladen …";"activities"->"Aktivitäten in deinem Umkreis werden geladen …";"buedchen"->"Büdchen werden geortet …";"wurst"->"Imbissbuden werden gesucht …";else->"Food in deinem Umkreis wird geladen …"})}
            c.error.isNotBlank()->item {MessageCard(c.error,"Erneut laden",c::loadItems)}
            else->{
                item {Column {Text("${c.total} ${when(c.category){"events"->"Events";"buedchen"->"Büdchen";"wurst"->"Imbissbuden";else->"Orte"}}",fontWeight=FontWeight.SemiBold);c.effectiveRadius?.takeIf {it!=c.radius}?.let {Text("Gelieferter Umkreis: ${radiusText(it)} km",color=Muted,fontSize=12.sp)}}}
                if(c.total==0)item {MessageCard("Keine Treffer für diese Auswahl. Du kannst die Filter zurücksetzen oder Standort und Umkreis ändern.","Standort & Umkreis",location)}
                items(c.results,key={it.text("id")}) {item->FoodCard(item,c.photoFailureHandler(item.text("id"))){c.openDetail(item.text("id"))}}
                if(c.results.size<c.total)item {Button(onClick=c::more,modifier=Modifier.fillMaxWidth()){Text("Weitere Orte anzeigen")}}
                if(c.category=="wurst")item {WurstSuggestion(c)}
                if(c.category=="buedchen")item {Text("Öffnungszeiten und Angaben können sich ändern. Community-Hinweise werden vor Veröffentlichung geprüft.",fontSize=12.sp,color=Muted)}
            }
        }
    }
}
@Composable private fun Loading(text:String) {Row(Modifier.padding(vertical=24.dp),verticalAlignment=Alignment.CenterVertically,horizontalArrangement=Arrangement.spacedBy(14.dp)){CircularProgressIndicator(Modifier.size(24.dp),strokeWidth=2.dp);Text(text)}}
@Composable private fun MessageCard(text:String,action:String,onClick:(()->Unit)?) {Card(Modifier.fillMaxWidth()){Column(Modifier.padding(18.dp),verticalArrangement=Arrangement.spacedBy(12.dp)){Text(text);if(onClick!=null)Button(onClick=onClick){Text(action)}}}}
@Composable private fun ChipMenu(label:String,selected:String,options:List<String>,onSelect:(String)->Unit) {
    var open by remember {mutableStateOf(false)}
    Box {
        OutlinedButton(onClick={open=true},modifier=Modifier.fillMaxWidth(),enabled=options.isNotEmpty()){Text("$label: ${selected.ifBlank {"Alle"}}  ▾")}
        DropdownMenu(expanded=open,onDismissRequest={open=false},modifier=Modifier.heightIn(max=320.dp)) {
            DropdownMenuItem(text={Text("Alle")},onClick={onSelect("");open=false})
            options.forEach {v->DropdownMenuItem(text={Text(v)},onClick={onSelect(v);open=false})}
        }
    }
}
@Composable private fun FoodCard(item:JSONObject,onPhotoFailure:(String)->Unit,click:()->Unit) {
    if(item.text("category")=="buedchen"){BuedchenCard(item,onPhotoFailure,click);return}
    Card(onClick=click,modifier=Modifier.fillMaxWidth(),shape=RoundedCornerShape(18.dp)) {
        PlacePhoto(item,onFailure=onPhotoFailure)
        Column(Modifier.padding(18.dp),verticalArrangement=Arrangement.spacedBy(7.dp)) {
            Text(item.text("title"),fontSize=20.sp,fontWeight=FontWeight.Bold)
            item.optJSONObject("special")?.let {special->
                if(item.text("category")=="buedchen") {Text(special.optJSONObject("opening")?.text("label").orEmpty());Text(special.optJSONArray("features")?.objects()?.take(3)?.joinToString(" · "){it.text("label")}.orEmpty(),fontSize=12.sp)}
                else if(item.text("category")=="wurst")Text(listOf(special.text("tier"),special.optJSONArray("menu")?.let {a->(0 until a.length()).take(3).joinToString(" · "){a.getString(it)}}.orEmpty()).filter {it.isNotBlank()}.joinToString(" · "),fontSize=12.sp,color=Orange)
            }
            if(item.text("routeLabel").isNotBlank())Text(item.text("routeLabel"),color=Orange,fontSize=12.sp)
            item.optJSONObject("event")?.let {event->Text(event.text("cardDate"),color=Orange,fontWeight=FontWeight.SemiBold)}
            Text(listOf(item.text("subtitle"),item.text("distance")).filter {it.isNotBlank()}.joinToString(" · "),color=Orange)
            Text(listOf(item.text("address"),item.text("city")).filter {it.isNotBlank()}.joinToString(" · "),color=Muted)
            if(item.text("hours").isNotBlank())Text(item.text("hours"),fontSize=13.sp)
            Text("Details ansehen  →",color=Orange,fontSize=13.sp)
        }
    }
}

@Composable private fun LocationDialog(c:FoodController,onGps:(Double)->Unit,cancelGps:()->Unit,close:()->Unit) {
    var mode by rememberSaveable {mutableStateOf("GPS")}
    var km by rememberSaveable {mutableStateOf(c.activeRadius)}
    var locationQuery by rememberSaveable {mutableStateOf("")}
    var postcode by rememberSaveable {mutableStateOf("")}
    var street by rememberSaveable {mutableStateOf("")}
    var house by rememberSaveable {mutableStateOf("")}
    var city by rememberSaveable {mutableStateOf("")}
    val initialSerial=remember {c.contextSerial}
    LaunchedEffect(c.contextSerial) {if(c.contextSerial!=initialSerial)close()}
    Dialog(onDismissRequest=close,properties=DialogProperties(usePlatformDefaultWidth=false)) {
        Surface(Modifier.fillMaxSize(),color=Background) {
            Column(Modifier.safeDrawingPadding().imePadding().padding(18.dp)) {
                Row(Modifier.fillMaxWidth(),horizontalArrangement=Arrangement.SpaceBetween,verticalAlignment=Alignment.CenterVertically) {
                    Text("Standort & Umkreis",fontSize=24.sp,fontWeight=FontWeight.Bold,modifier=Modifier.weight(1f))
                    TextButton(onClick=close){Text("Schließen")}
                }
                Text("Womit möchtest du suchen?",color=Muted)
                Row(Modifier.horizontalScroll(rememberScrollState()),horizontalArrangement=Arrangement.spacedBy(8.dp)) {
                    listOf("GPS","Ort","Adresse").forEach {v->FilterChip(selected=mode==v,onClick={cancelGps();c.cancelLocation();mode=v},label={Text(v)})}
                }
                Text("Umkreis",fontWeight=FontWeight.SemiBold,modifier=Modifier.padding(top=8.dp))
                Row(Modifier.horizontalScroll(rememberScrollState()),horizontalArrangement=Arrangement.spacedBy(6.dp)) {
                    listOf(1.0,2.5,5.0,10.0,25.0,50.0).forEach {v->FilterChip(selected=km==v,onClick={km=v},label={Text("${radiusText(v)} km")})}
                }
                if(c.resolving)Loading("Standort wird ermittelt …")
                if(c.locationError.isNotBlank())Text(c.locationError,color=MaterialTheme.colorScheme.error,modifier=Modifier.padding(vertical=8.dp))
                if(!c.ready) {Text("Standortauswahl wird vorbereitet …",color=Muted);return@Column}
                when(mode) {
                    "GPS"->Column(verticalArrangement=Arrangement.spacedBy(14.dp)) {
                        Text("Verwendet einmalig einen frischen Standort deines Geräts. Alternativ kannst du Stadt, PLZ oder Adresse eingeben.")
                        Button(onClick={c.chooseLocationRadius();onGps(km)},enabled=!c.resolving,modifier=Modifier.fillMaxWidth()){Text("Meinen Standort verwenden")}
                    }
                    "Ort"->{
                        val input=locationQuery.trim()
                        val isPostcode=input.matches(Regex("\\d{5}"))
                        val postcodeInput=input.isNotBlank() && input.all(Char::isDigit)
                        Column(Modifier.weight(1f),verticalArrangement=Arrangement.spacedBy(10.dp)) {
                            OutlinedTextField(locationQuery,{locationQuery=it.take(80)},singleLine=true,label={Text("PLZ oder Stadt auswählen")},modifier=Modifier.fillMaxWidth())
                            if(postcodeInput) {
                                Button(onClick={cancelGps();c.resolveLocation(input,"","","",km)},enabled=isPostcode && !c.resolving,modifier=Modifier.fillMaxWidth()){Text("Standort übernehmen")}
                                if(!isPostcode)Text("Bitte eine fünfstellige PLZ eingeben.",color=Muted)
                            } else {
                                val choices=c.cities.filter {it.name.contains(input,ignoreCase=true)}
                                LazyColumn(Modifier.weight(1f),contentPadding=PaddingValues(vertical=8.dp)) {
                                    items(choices,key={it.name}) {v->TextButton(onClick={cancelGps();c.chooseCity(v,km)},modifier=Modifier.fillMaxWidth(),enabled=!c.resolving){Text(v.name,Modifier.fillMaxWidth().padding(vertical=6.dp))}}
                                    if(choices.isEmpty())item {Text("Keine Stadt gefunden. Bitte Stadt oder fünfstellige PLZ eingeben.",Modifier.padding(16.dp))}
                                }
                            }
                        }
                    }
                    else->Column(Modifier.weight(1f).verticalScroll(rememberScrollState()),verticalArrangement=Arrangement.spacedBy(12.dp)) {
                        if(mode=="Adresse") {
                            OutlinedTextField(street,{street=it},label={Text("Straße")},singleLine=true,modifier=Modifier.fillMaxWidth())
                            OutlinedTextField(house,{house=it},label={Text("Hausnummer")},singleLine=true,modifier=Modifier.fillMaxWidth())
                        }
                        OutlinedTextField(postcode,{postcode=it.filter(Char::isDigit).take(5)},label={Text("Postleitzahl")},singleLine=true,keyboardOptions=KeyboardOptions(keyboardType=KeyboardType.Number),modifier=Modifier.fillMaxWidth())
                        if(mode=="Adresse")OutlinedTextField(city,{city=it},label={Text("Stadt")},singleLine=true,modifier=Modifier.fillMaxWidth())
                        Button(onClick={cancelGps();c.resolveLocation(postcode,if(mode=="Adresse")street else "",house,city,km)},enabled=!c.resolving,modifier=Modifier.fillMaxWidth()){Text("Standort übernehmen")}
                    }
                }
                c.location?.let { point->
                    Text("Aktuell: ${point.label}",color=Muted,fontSize=12.sp)
                    if(km!=c.activeRadius)TextButton(onClick={cancelGps();c.changeRadius(km)}) {Text("Nur Umkreis ändern")}
                }
            }
        }
    }
}

private fun openExternal(context:android.content.Context,value:String) {
    val uri=runCatching {Uri.parse(value.trim())}.getOrNull() ?: return
    val allowed=allowedExternalUrl(value.trim())
    if(!allowed)return
    try {context.startActivity(Intent(if(uri.scheme=="tel")Intent.ACTION_DIAL else Intent.ACTION_VIEW,uri))} catch(_:ActivityNotFoundException) {android.widget.Toast.makeText(context,"Keine passende App installiert.",android.widget.Toast.LENGTH_SHORT).show()}
}
