package de.ruhrpottguru.testapp

import org.junit.Assert.*
import org.junit.Test

class PottQuizTest {
    @Test fun everyAnswerScoresOnlyOnceAndStaysLocked() {
        PottQuiz.questions.forEachIndexed {index,question ->
            question.options.indices.forEach {answer ->
                val state=QuizSession(index=index).select(answer).check()
                assertEquals(if(answer==question.correctIndex)1 else 0,state.score)
                assertEquals(state,state.check())
                assertEquals(state,state.select((answer+1)%4))
                val next=state.next()
                if(index==9){assertTrue(next.finished);assertEquals(next,next.next())}
                else {assertEquals(index+1,next.index);assertEquals(-1,next.selected);assertFalse(next.checked)}
            }
        }
    }
    @Test fun completeRoundScoresAndRestart() {
        for(correct in 0..10) {
            var state=QuizSession()
            repeat(10) {i->
                val answer=PottQuiz.questions[i].correctIndex
                state=state.select(if(i<correct)answer else (answer+1)%4).check().next()
            }
            assertTrue(state.finished)
            assertEquals(correct,state.score)
            assertEquals(correct*10,PottQuiz.percentage(state.score))
        }
        assertEquals(0,QuizSession().score)
        assertFalse(QuizSession().finished)
    }
    @Test fun noSkippingOrInvalidSelection() {
        val state=QuizSession()
        assertEquals(state,state.check());assertEquals(state,state.next())
        assertEquals(state,state.select(-1));assertEquals(state,state.select(4))
    }
    @Test fun originalRankBoundariesAndNavigation() {
        listOf(0 to "Gruben-Lehrling",34 to "Gruben-Lehrling",35 to "Wegbier-Genießer",59 to "Wegbier-Genießer",60 to "Erfahrener Steiger",79 to "Erfahrener Steiger",80 to "Echter Ruhrpott Guru",100 to "Echter Ruhrpott Guru").forEach {(pct,title)->assertEquals(title,PottQuiz.rank(pct).title)}
        assertEquals(10,PottQuiz.questions.size)
        assertEquals(10,PottQuiz.questions.map {it.id}.toSet().size)
        PottQuiz.questions.forEach {assertEquals(4,it.options.size);assertTrue(it.correctIndex in 0..3)}
        assertTrue(AppNavigation.enabled("Pott-Quiz"))
        assertFalse(AppNavigation.enabled("Ruhrpott-Lexikon"))
    }
}
