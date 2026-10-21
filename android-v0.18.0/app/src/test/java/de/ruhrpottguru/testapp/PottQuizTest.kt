package de.ruhrpottguru.testapp

import org.json.JSONArray
import org.json.JSONObject
import org.junit.Assert.*
import org.junit.Test
import kotlin.random.Random

class PottQuizTest {
    private fun resource(name:String)=javaClass.getResourceAsStream("/$name")!!.bufferedReader().use {it.readText()}
    private fun pool()=PottQuiz.parsePool(resource("questions.json"))
    private fun round(seed:Int=42)=PottQuiz.newRound(pool().questions,Random(seed)::nextInt)
    @Test fun canonicalPoolHas200QuestionsFourUniqueChoicesAndExplanations() {
        val p=pool();assertEquals("2.3.0",p.version);assertEquals(200,p.questions.size)
        assertEquals(200,p.questions.map {it.id}.toSet().size);assertEquals(6,p.questions.map {it.category}.toSet().size)
        p.questions.forEach {assertEquals(4,it.options.size);assertEquals(4,it.options.toSet().size);assertTrue(it.explanation.isNotBlank());assertTrue(it.correctIndex in 0..3)}
    }
    @Test fun matchesOriginalWebAlgorithmAndAnswerPermutationExactly() {
        val p=pool();val refs=JSONArray(resource("pottquiz-web-rounds.json"))
        for(i in 0 until refs.length()) {
            val ref=refs.getJSONObject(i);var state=ref.getLong("seed")
            val actual=PottQuiz.newRound(p.questions) {bound->state=(state*1664525L+1013904223L) and 0xffffffffL;((state/4294967296.0)*bound).toInt()}
            val expected=ref.getJSONArray("round")
            actual.forEachIndexed {j,q->val r=expected.getJSONObject(j);assertEquals(r.getString("id"),q.id);assertEquals(r.getInt("correctIndex"),q.correctIndex);assertEquals((0..3).map {r.getJSONArray("options").getString(it)},q.options)}
        }
    }
    @Test fun balancedRoundsAreUniqueCoverPoolAndRetainCorrectAnswer() {
        val p=pool();val seen=mutableSetOf<String>();val variations=mutableSetOf<List<String>>()
        repeat(1000) {seed->
            val round=PottQuiz.newRound(p.questions,Random(seed)::nextInt)
            assertEquals(10,round.size);assertEquals(10,round.map {it.id}.toSet().size)
            assertEquals(6,round.map {it.category}.toSet().size)
            assertTrue(round.groupBy {it.category}.values.all {it.size in 1..2})
            round.forEach {q->val original=p.questions.first {it.id==q.id};assertEquals(original.options[original.correctIndex],q.options[q.correctIndex]);assertEquals(original.explanation,q.explanation);seen.add(q.id)}
            variations.add(round.map {it.id})
        }
        assertEquals(200,seen.size);assertTrue(variations.size>990)
    }
    @Test fun everyPoolAnswerScoresOnlyOnceAndLocks() {
        pool().questions.forEach {q->q.options.indices.forEach {answer->
            val state=QuizSession(listOf(q)).select(answer).check()
            assertEquals(if(answer==q.correctIndex)1 else 0,state.score)
            assertEquals(state,state.check());assertEquals(state,state.select((answer+1)%4))
            val end=state.next();assertTrue(end.finished);assertEquals(end,end.next())
        }}
    }
    @Test fun completeRoundsStillScoreOutOfTenAndCannotSkip() {
        for(correct in 0..10) {
            var state=QuizSession(round(correct))
            assertEquals(state,state.check());assertEquals(state,state.next());assertEquals(state,state.select(-1));assertEquals(state,state.select(4))
            repeat(10) {i->val q=state.round[i];state=state.select(if(i<correct)q.correctIndex else (q.correctIndex+1)%4).check().next()}
            assertTrue(state.finished);assertEquals(correct,state.score);assertEquals(correct*10,PottQuiz.percentage(state.score))
        }
    }
    @Test fun roundAndAnswerOrderSurviveRecreationAtEveryStep() {
        val p=pool();var state=QuizSession(round())
        repeat(10) {
            assertEquals(state,QuizSession.restore(state.snapshot(p),p))
            state=state.select(state.round[state.index].correctIndex).check()
            assertEquals(state,QuizSession.restore(state.snapshot(p),p))
            state=state.next()
        }
        assertEquals(state,QuizSession.restore(state.snapshot(p),p));assertTrue(state.finished)
    }
    @Test fun incompatibleOrBrokenSavedRoundsAreRejected() {
        val p=pool();val raw=QuizSession(round()).snapshot(p)
        assertNull(QuizSession.restore("not json",p))
        assertNull(QuizSession.restore(listOf(0,-1,0,0,0),p))
        assertNull(QuizSession.restore(JSONObject(raw).put("poolVersion","future").toString(),p))
        assertNull(QuizSession.restore(JSONObject(raw).put("index",10).toString(),p))
        val duplicate=JSONObject(raw);duplicate.getJSONArray("round").put(1,duplicate.getJSONArray("round").getJSONObject(0));assertNull(QuizSession.restore(duplicate.toString(),p))
        val answers=JSONObject(raw);answers.getJSONArray("round").getJSONObject(0).getJSONArray("answers").put(0,"wrong answer");assertNull(QuizSession.restore(answers.toString(),p))
    }
    @Test fun invalidQuestionPoolsFailInsteadOfDisplayingWrongAnswers() {
        val raw=resource("questions.json");val d=JSONObject(raw);d.getJSONArray("questions").getJSONObject(0).put("correctIndex",4)
        assertTrue(runCatching {PottQuiz.parsePool(d.toString())}.isFailure)
        assertTrue(runCatching {PottQuiz.parsePool(JSONObject(raw).put("schemaVersion",2).toString())}.isFailure)
    }
    @Test fun originalRanksAndNavigationRemain() {
        listOf(0 to "Gruben-Lehrling",34 to "Gruben-Lehrling",35 to "Wegbier-Genießer",59 to "Wegbier-Genießer",60 to "Erfahrener Steiger",79 to "Erfahrener Steiger",80 to "Echter Ruhrpott Guru",100 to "Echter Ruhrpott Guru").forEach {(pct,title)->assertEquals(title,PottQuiz.rank(pct).title)}
        assertTrue(AppNavigation.enabled("Pott-Quiz"));assertFalse(AppNavigation.enabled("Ruhrpott-Lexikon"))
    }
}
