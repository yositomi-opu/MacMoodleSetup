<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
// Standalone, no Moodle/DB/AI dependency. php .../tests/policy_test.php
require_once(__DIR__ . '/../classes/policy.php');
require_once(__DIR__ . '/../classes/response.php');
use local_virtuallearner\policy;
$count = 0;
function vl_assert($condition, string $label): void {
    global $count;
    if (!$condition) { throw new RuntimeException($label); }
    $count++;
}
function vl_reject(callable $call): void {
    try { $call(); } catch (Throwable $e) { vl_assert(true, 'rejected'); return; }
    throw new RuntimeException('Expected rejection');
}
$p = policy::defaults();
$spec = ['questionid' => 1, 'variant' => 1, 'skills' => ['LA000001'], 'responses' => [
    ['id' => 'yes', 'kind' => 'correct', 'answers' => ['ans1' => 'x^2+2*x+1']],
    ['id' => 'bug', 'kind' => 'misconception', 'skills' => ['LA000001'], 'answers' => ['ans1' => 'x^2+1']],
    ['id' => 'slip', 'kind' => 'slip', 'answers' => ['ans1' => 'x^2+3*x+1']],
    ['id' => 'invalid', 'kind' => 'invalid', 'answers' => ['ans1' => '']],
]];
vl_assert(policy::profile($p) === $p, 'valid profile');
vl_assert(policy::scenario([1 => $spec], $p)[1] === $spec, 'valid scenario');
$state = policy::initial($p, 1);
vl_assert($state === ['LA000001' => false], 'initial knowledge');
vl_assert(policy::choose($p,$state,$spec,1,1,1)['id'] === 'bug', 'misconception');
$learned = policy::learn($p,$state,['LA000001'],true,true,1,1,1);
vl_assert($learned === ['LA000001'=>true], 'feedback learning');
vl_assert(policy::choose($p,$learned,$spec,1,2,1)['id'] === 'yes', 'correct after learning');
vl_assert(policy::learn($p,$state,['LA000001'],false,true,1,1,1) === $state, 'no hidden feedback learning');
vl_assert(policy::learn($p,$state,['LA000001'],true,false,1,1,1) === $state, 'no learning from invalid input');
$modified=$p;$modified['inputerror']=1;
vl_assert(policy::choose($modified,$learned,$spec,1,1,1)['id']==='invalid','input error independent of knowledge');
$modified=$p;$modified['slip']=1;
vl_assert(policy::choose($modified,$learned,$spec,1,1,1)['id']==='slip','slip despite mastery');
$modified=$p;$modified['guess']=1;
vl_assert(policy::choose($modified,$state,$spec,1,1,1)['id']==='yes','guess without mastery');
$modified=$p;$modified['feedbacklearn']=0;
vl_assert(policy::learn($modified,$state,['LA000001'],true,true,1,1,1)===$state,'no learning baseline');
vl_assert(policy::draw(17,'a')===policy::draw(17,'a'),'repeatable seed');
vl_assert(policy::draw(17,'a')!==policy::draw(17,'b'),'independent streams');
foreach ([-1, 2, '0.5', NAN, INF] as $bad) {
    $modified=$p;$modified['slip']=$bad;vl_reject(fn()=>policy::profile($modified));
}
$modified=$p;unset($modified['persistence']);vl_reject(fn()=>policy::profile($modified));
$modified=$p;$modified['skills']=['bad'=>0];vl_reject(fn()=>policy::profile($modified));
$modified=$spec;$modified['responses'][1]['id']='yes';vl_reject(fn()=>policy::scenario([1=>$modified],$p));
$modified=$spec;array_splice($modified['responses'],1,1);vl_reject(fn()=>policy::scenario([1=>$modified],$p));
$modified=$spec;$modified['responses'][0]['answers']['ans1']=123;vl_reject(fn()=>policy::scenario([1=>$modified],$p));
vl_reject(fn()=>policy::decode('{broken'));
vl_reject(fn()=>policy::decode(str_repeat('x',65537)));
vl_reject(fn()=>policy::decode('false'));
$rawinput = new class {
    public function get_expected_data(): array { return ['ans1' => 'raw', 'ans1_val' => 'raw']; }
};
$plaininput = new class {
    public function get_expected_data(): array { return ['ansD' => 'raw']; }
};
$matrixinput = new class {
    public function get_expected_data(): array { return ['ans1_sub_0_0' => 'raw']; }
};
$literal = 'matrix([A,b],[C,d]) /* {@x@} */';
vl_assert(\local_virtuallearner\response::prepare(['ans1'=>$rawinput], ['ans1'=>$literal]) ===
    ['ans1'=>$literal,'ans1_val'=>$literal], 'preserve input bytes and case');
vl_assert(\local_virtuallearner\response::prepare(['ans1'=>$rawinput,'ansD'=>$plaininput], ['ansD'=>'2']) ===
    ['ans1'=>'','ans1_val'=>'','ansD'=>'2'], 'multiple inputs and explicit blank');
vl_reject(fn()=>\local_virtuallearner\response::prepare(['ans1'=>$matrixinput], ['ans1'=>'matrix([1])']));
vl_reject(fn()=>\local_virtuallearner\response::prepare(['ans1'=>$rawinput], ['other'=>'x']));
vl_reject(fn()=>\local_virtuallearner\response::prepare(['ans1'=>$rawinput], ['ans1'=>2]));
echo "PASS $count policy and response assertions\n";
