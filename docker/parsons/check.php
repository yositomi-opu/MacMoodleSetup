<?php
// Synthetic fixture only. No existing site is used by this workflow.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
define('CLI_SCRIPT',true);
require('/var/www/html/config.php');
require_once($CFG->libdir.'/clilib.php');
require_once($CFG->dirroot.'/question/format/xml/format.php');
require_once($CFG->dirroot.'/question/type/stack/stack/questiontest.php');
[$options,$unknown]=cli_get_params(['allow-test-data'=>false]);
if ($unknown || !$options['allow-test-data']) { cli_error('Disposable sites only: --allow-test-data'); }
\core\session\manager::set_user(get_admin());
$labid=\local_virtuallearner\service::demo('Parsons authoring synthetic verification');
$lab=$DB->get_record('local_vlearner_lab',['id'=>$labid],'*',MUST_EXIST);
$scenario=json_decode($lab->scenariojson,true);
$cat=$DB->get_record_sql('SELECT qc.* FROM {question_categories} qc JOIN {question_bank_entries} qbe ON qbe.questioncategoryid=qc.id JOIN {question_versions} qv ON qv.questionbankentryid=qbe.id WHERE qv.questionid=:qid',['qid'=>$scenario[1]['questionid']],MUST_EXIST);
$context=\context::instance_by_id($cat->contextid);
foreach (['orders','dag'] as $mode) {
 $format=new qformat_xml();$format->setCategory($cat);$format->setCourse(get_course($lab->courseid));$format->setContexts([$context]);
 $format->setFilename(__DIR__.'/'.$mode.'.xml');$format->setRealfilename('parsons-'.$mode.'.xml');
 $format->setCatfromfile(false);$format->setContextfromfile(false);$format->setStoponerror(true);$format->set_display_progress(false);
 if (!$format->importpreprocess()||!$format->importprocess()||!$format->importpostprocess()||count($format->questionids)!==1) {cli_error('Parsons XML import failed');}
 $qid=(int)reset($format->questionids);
 $cases=[['s1','s2','s3','s4'],['s1','s3','s2','s4'],['s2','s1','s3','s4'],['s1','s2','s4'],['s1','s2','s3','s4','unused'],['s1','s2','s3','s3','s4']];
 foreach ($cases as $i=>$order) {
  $question=question_bank::load_question($qid);$question->seed=1;
  $quba=question_engine::make_questions_usage_by_activity('qtype_stack',$context);$quba->set_preferred_behaviour('adaptive');
  $slot=$quba->add_question($question,1);$quba->start_question($slot,1);
  $question=$quba->get_question($slot);
  if (!empty($question->runtimeerrors)) {cli_error('Question runtime errors: '.json_encode($question->runtimeerrors));}
  $expr='parsons_answer(proof('.implode(',',array_map(fn($id)=>'"'.$id.'"',$order)).'),proof_steps)';
  $response=stack_question_test::compute_response($question,['ans1'=>$expr]);
  $result=$question->get_prt_result('prt1',$response,true);
  if ($result->get_errors()) {cli_error('PRT errors: '.json_encode($result->get_errors()));}
  $actual=$result->is_evaluated()?(float)$result->get_score():0.0;$expected=$i<2?1.0:0.0;
  if (abs($actual-$expected)>0.00001) {cli_error($mode.' case '.$i.' score '.$actual.' expected '.$expected.' response '.json_encode($response));}
  echo 'PASS '.$mode.' case '.$i.' fraction='.$actual.PHP_EOL;
 }
}
echo "PASS real Moodle import and STACK Parsons grading: exact order, alternate order, wrong order, missing, extra and duplicate steps.\n";
