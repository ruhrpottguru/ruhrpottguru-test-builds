package de.ruhrpottguru.testapp

import android.content.Context
import android.content.Intent
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.runtime.saveable.listSaver
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp

private val QuizSaver=listSaver<QuizSession,Int>(
    save={listOf(it.index,it.selected,if(it.checked)1 else 0,it.score,if(it.finished)1 else 0)},
    restore={QuizSession(it[0],it[1],it[2]==1,it[3],it[4]==1)}
)

@Composable internal fun PottQuizScreen(open:(String)->Unit) {
    val context=LocalContext.current
    val preferences=remember {context.getSharedPreferences("ruhrpott_quiz",Context.MODE_PRIVATE)}
    var highScore by remember {mutableIntStateOf(preferences.getInt("highscore",0))}
    var started by rememberSaveable {mutableStateOf(false)}
    var session by rememberSaveable(stateSaver=QuizSaver) {mutableStateOf(QuizSession())}
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
                Text("10 Fragen. Vier Antworten. Am Ende bekommst du deinen persönlichen Pott-Rang.")
                Text("Highscore: $highScore % · ${PottQuiz.rank(highScore).title}")
                Button(onClick={session=QuizSession();started=true}){Text("Quiz starten")}
            }
            session.finished -> {
                val percentage=PottQuiz.percentage(session.score)
                val rank=PottQuiz.rank(percentage)
                Text("${rank.badge} ${rank.title}",fontSize=24.sp,fontWeight=FontWeight.Bold)
                Text(rank.description)
                Text("Richtige Antworten: ${session.score} / ${PottQuiz.questions.size} · $percentage %")
                Text("Highscore: $highScore %")
                Button(onClick={session=QuizSession()}){Text("Nochmal spielen")}
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
                val question=PottQuiz.questions[session.index]
                Text("${question.category} · Frage ${session.index+1} von ${PottQuiz.questions.size}")
                LinearProgressIndicator(progress={(session.index+1).toFloat()/PottQuiz.questions.size},modifier=Modifier.fillMaxWidth())
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
                Text("Punktestand: ${session.score} / ${PottQuiz.questions.size}")
                if(session.checked)Button(onClick={session=session.next()}) {
                    Text(if(session.index+1==PottQuiz.questions.size)"Ergebnis anzeigen" else "Nächste Frage")
                } else Button(onClick={session=session.check()},enabled=session.selected>=0){Text("Antwort einloggen")}
            }
        }
    }
}
