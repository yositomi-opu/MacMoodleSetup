<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require(__DIR__ . '/policy_test.php');
require_once(__DIR__ . '/../classes/experiment.php');
require_once(__DIR__ . '/../classes/decision.php');
use local_virtuallearner\experiment;
use local_virtuallearner\decision;
use local_virtuallearner\policy;
$p = policy::defaults(); $p['skills']['LA000002'] = 0.5;
$opts = experiment::options(['model'=>'compare_dina','initial'=>['LA000001'=>false,'LA000002'=>true],
    'qmatrix'=>[1=>['LA000001'=>1,'LA000002'=>0]]], $p, [1=>$spec]);
vl_assert(experiment::initial($p,7,$opts)===experiment::initial($p,7,$opts),'paired exact state');
vl_assert(experiment::initial($p,7,$opts)===['LA000001'=>false,'LA000002'=>true],'explicit state overrides probabilities');
$scenario=experiment::scenario([1=>$spec],$opts,$p);
vl_assert($scenario[1]['skills']===['LA000001'],'Q-matrix excludes zero cell');
$state=experiment::initial($p,7,$opts);
vl_assert(experiment::dina($p,$state,$spec,7,1,1)['id']==='bug','DINA missing required skill');
$after=policy::learn($p,$state,$scenario[1]['skills'],true,true,7,1,1);
vl_assert($after===['LA000001'=>true,'LA000002'=>true],'only mapped skill transitions');
vl_assert(experiment::dina($p,$after,$spec,7,2,1)['id']==='yes','DINA after learning');
$unrelated=['LA000001'=>false,'LA000002'=>false];
vl_assert(policy::learn($p,$unrelated,$scenario[1]['skills'],true,true,7,1,1)===
    ['LA000001'=>true,'LA000002'=>false],'Q-matrix zero skill never learned');
$both=$spec;$both['skills']=['LA000001','LA000002'];$both['responses'][1]['skills']=$both['skills'];
vl_assert(experiment::dina($p,['LA000001'=>true,'LA000002'=>false],$both,7,1,1)['id']==='bug',
    'conjunctive response requires every Q-matrix skill');
$zero=$p;$zero['feedbacklearn']=0;
vl_assert(policy::learn($zero,$state,['LA000001'],true,true,7,1,1)===$state,'fixed baseline');
foreach ([['model'=>'unknown'], ['initial'=>['LA000001'=>false]], ['initial'=>['LA000001'=>0,'LA000002'=>true]],
    ['qmatrix'=>[1=>['LA999999'=>1]]], ['qmatrix'=>[2=>['LA000001'=>1]]], ['qmatrix'=>[1=>['LA000001'=>0]]],
    ['qmatrix'=>[1=>['LA000001'=>true]]], ['qmatrix'=>[1=>['LA000001'=>'1']]], ['unknown'=>true]] as $bad) {
    vl_reject(fn()=>experiment::options($bad,$p,[1=>$spec]));
}
$coverage=$p;$coverage['skills']['LA000001']=1;
$nospec=$spec;array_splice($nospec['responses'],1,1);
vl_reject(fn()=>experiment::scenario([1=>$nospec],$opts,$coverage));
$onlycorrect=$spec;$onlycorrect['responses']=[$spec['responses'][0]];
$masteredoptions=experiment::options(['initial'=>['LA000001'=>true,'LA000002'=>true]],$p,[1=>$onlycorrect]);
vl_assert(experiment::scenario([1=>$onlycorrect],$masteredoptions,$p)[1]['responses']===$onlycorrect['responses'],
    'exact mastered initial state permits no misconception despite zero prior');
vl_reject(fn()=>experiment::options(['skilllabels'=>['XX999999'=>'unknown']],$p,[1=>$spec]));
vl_reject(fn()=>experiment::options(['skilllabels'=>['LA000001'=>17]],$p,[1=>$spec]));
$request=decision::request($p,$state,$spec,'Expand (x+1)^2', [['feedback'=>['Review the middle term']]], ['LA000001'=>'Cross term in binomial expansion']);
vl_assert($request['state']['skill_descriptions']['LA000001']==='Cross term in binomial expansion','skill meaning supplied without grading labels');
vl_assert($request['model']===decision::MODEL,'pinned model');
vl_assert(array_keys($request['questions']['response']['criteria'])===['c0','c1','c2','c3'],'opaque candidate IDs');
$raw=json_encode($request);
vl_assert(!str_contains($raw,'missing_cross_term') && !str_contains($raw,'misconception') && !str_contains($raw,'"kind"'), 'no grading labels exposed');
vl_assert(!str_contains($raw,'questionid') && !str_contains($raw,'userid'),'no Moodle identifiers');
$response=['model'=>decision::MODEL,'answers'=>['response'=>['type'=>'choice','choice'=>'c1',
    'confidence'=>0.8,'probabilities'=>['c0'=>0.1,'c1'=>0.8,'c2'=>0.05,'c3'=>0.05]]],
    'usage'=>['input_tokens'=>123,'output_tokens'=>12]];
$parsed=decision::parse($response,$spec);
vl_assert($parsed['choice']['id']==='bug','provider choice maps to literal candidate');
vl_assert($parsed['audit']['answer']['confidence']===0.8,'confidence recorded separately from mastery');
foreach (['type'=>'score','choice'=>'c99','confidence'=>2,'probabilities'=>['c0'=>1],
    'probabilities_bad'=>['c0'=>0.1,'c1'=>0.1,'c2'=>0.1,'c3'=>0.1],
    'probabilities_negative'=>['c0'=>-0.1,'c1'=>1,'c2'=>0.05,'c3'=>0.05],
    'probabilities_top'=>['c0'=>0.9,'c1'=>0.1,'c2'=>0,'c3'=>0]] as $k=>$v) {
    $bad=$response;$bad['answers']['response'][str_starts_with($k,'probabilities')?'probabilities':$k]=$v;
    vl_reject(fn()=>decision::parse($bad,$spec));
}
foreach (['model'=>'jev-latest','usage'=>['input_tokens'=>-1,'output_tokens'=>0],'answers'=>[]] as $k=>$v) {
    $bad=$response;$bad[$k]=$v;vl_reject(fn()=>decision::parse($bad,$spec));
}
vl_reject(fn()=>decision::request($p,$state,$spec,str_repeat('x',65537),[]));
echo "PASS $count total policy, response, Q-matrix and decision assertions\n";
