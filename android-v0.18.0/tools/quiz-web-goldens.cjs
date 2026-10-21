// Regenerate deterministic parity fixtures from the unchanged Web v2.3 round helper.
const fs=require('node:fs'),vm=require('node:vm'),path=require('node:path');
const root=path.resolve(__dirname,'..');
const pool=JSON.parse(fs.readFileSync(path.join(root,'app/src/main/assets/quiz/questions.json'),'utf8')).questions.map(q=>({...q,options:q.answers}));
const cases=[0,1,42,12345,4294967295].map(seed=>{
  let state=seed;const math=Object.create(Math);
  math.random=()=>{state=(Math.imul(state,1664525)+1013904223)>>>0;return state/4294967296;};
  const context={Math:math};vm.createContext(context);
  vm.runInContext(fs.readFileSync(path.join(root,'tests/fixtures/quiz-web-round-selection.js'),'utf8'),context);
  return {seed,round:context.rpgpqRound(pool).map(q=>({id:q.id,options:q.options,correctIndex:q.correctIndex}))};
});
process.stdout.write(JSON.stringify(cases,null,2)+'\n');
