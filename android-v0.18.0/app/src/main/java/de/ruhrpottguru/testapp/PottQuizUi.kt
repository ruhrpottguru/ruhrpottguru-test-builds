package de.ruhrpottguru.testapp

import android.content.Context
import android.content.Intent
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.runtime.saveable.Saver
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp

@Composable internal fun PottQuizScreen(open:(String)->Unit) {
    val context=LocalContext.current
    val pool=remember {context.assets.open("quiz/questions.json").bufferedReader().use {PottQuiz.parsePool(it.readText())}}
    val quizSaver=remember(pool) {Saver<QuizSession,Any>(save={it.snapshot(pool)},restore={QuizSession.restore(it,pool)})}
    fun newSession()=QuizSession(PottQuiz.newRound(pool.questions))
    val preferences=remember {context.getSharedPreferences("ruhrpott_quiz",Context.MODE_PRIVATE)}
    var highScore by remember {mutableIntStateOf(preferences.getInt("highscore",0))}
    var started by rememberSaveable {mutableStateOf(false)}
    var session by rememberSaveable(stateSaver=quizSaver) {mutableStateOf(newSession())}
    val scroll=rememberScrollState()
    LaunchedEffect(session.index,session.finished,started){scroll.scrollTo(0)}
    LaunchedEffect(session.finished) {
        if(session.finished) {
            val percentage=PottQuiz.percentage(session.score)
            if(percentage>highScore){highScore=percentage;preferences.edit().putInt("highscore",percentage).apply()}
        }
    }
    Column(Modifier.fillMaxSize().verticalScroll(scroll).padding(16.dp),verticalArrangement=Arrangement.spacedBy(14.dp)) {
        Text("Pott-Quiz",fontSize=28.sp,fontWeight=FontWeight.Bold)
        Text("Teste dein Wissen über Zechen, Welterbe, Pott-Sprache, Kult-Imbisse und Haldenkunst.")
        when {
            !started -> {
                Text("Welcher Ruhrpott-Rang steckt in dir?",fontSize=22.sp,fontWeight=FontWeight.Bold)
                Text("Pro Runde 10 gemischte Fragen aus ${pool.questions.size}. Je vier Antworten, immer neu gemischt. Am Ende bekommst du deinen persönlichen Pott-Rang.")
                Text("Highscore: $highScore % · ${PottQuiz.rank(highScore).title}")
                Button(onClick={session=newSession();started=true}){Text("Quiz starten")}
            }
            session.finished -> {
                val percentage=PottQuiz.percentage(session.score)
                val rank=PottQuiz.rank(percentage)
                Text("${rank.badge} ${rank.title}",fontSize=24.sp,fontWeight=FontWeight.Bold)
                Text(rank.description)
                Text("Richtige Antworten: ${session.score} / ${session.round.size} · $percentage %")
                Text("Highscore: $highScore %")
                Button(onClick={session=newSession()}){Text("Nochmal spielen")}
                OutlinedButton(onClick={
                    val text="🏆 Mein Pott-Quiz-Rang: ${rank.badge} ${rank.title} ($percentage %). Teste dein Ruhrpott-Wissen auf RuhrpottGuru! https://www.ruhrpottguru.de/pott-quiz/"
                    context.startActivity(Intent.createChooser(Intent(Intent.ACTION_SEND).apply {type="text/plain";putExtra(Intent.EXTRA_TEXT,text)},"Ergebnis teilen"))
                }){Text("Ergebnis teilen")}
                Text("Danach weiter durchs Revier:")
                Row(Modifier.fillMaxWidth(),horizontalArrangement=Arrangement.SpaceBetween) {
                    listOf("Events","Food","Highlights").forEach {name->TextButton(onClick={open(name)}){Text(name)}}
                }
                Row {
                    listOf("Kultur","Aktivitäten").forEach {name->TextButton(onClick={open(name)}){Text(name)}}
                }
            }
            else -> {
                val question=session.round[session.index]
                Text("${question.category} · Frage ${session.index+1} von ${session.round.size}")
                LinearProgressIndicator(progress={(session.index+1).toFloat()/session.round.size},modifier=Modifier.fillMaxWidth())
                Text(question.question,fontSize=22.sp,fontWeight=FontWeight.Bold)
                question.options.forEachIndexed {index,answer->
                    val prefix=when {
                        session.checked && index==question.correctIndex -> "✓ "
                        session.checked && index==session.selected -> "× "
                        index==session.selected -> "● "
                        else -> ""
                    }
                    OutlinedButton(onClick={session=session.select(index)},enabled=!session.checked,modifier=Modifier.fillMaxWidth()) {
                        Text("$prefix${('A'.code+index).toChar()}. $answer",modifier=Modifier.fillMaxWidth())
                    }
                }
                if(session.checked) {
                    Text(if(session.selected==question.correctIndex)"Richtig! Revier-Wissen:" else "Nicht ganz. Hintergrund:",fontWeight=FontWeight.Bold)
                    Text("Richtige Antwort: ${question.options[question.correctIndex]}")
                    Text(question.explanation)
                }
                Text("Punktestand: ${session.score} / ${session.round.size}")
                if(session.checked)Button(onClick={session=session.next()}) {
                    Text(if(session.index+1==session.round.size)"Ergebnis anzeigen" else "Nächste Frage")
                } else Button(onClick={session=session.check()},enabled=session.selected>=0){Text("Antwort einloggen")}
            }
        }
    }
}
