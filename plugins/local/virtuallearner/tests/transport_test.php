<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace local_virtuallearner {
    // Namespaced transport doubles: no external request or real credential is used.
    function curl_init($url) { $GLOBALS['vl_url']=$url; return new \stdClass(); }
    function curl_setopt_array($ch,$options) { $GLOBALS['vl_curl']=$options; }
    function curl_exec($ch) {
        $write=$GLOBALS['vl_curl'][CURLOPT_WRITEFUNCTION];
        $data=$GLOBALS['vl_body'];
        return $write($ch,$data)===strlen($data);
    }
    function curl_getinfo($ch,$key) { return $GLOBALS['vl_status']; }
    function curl_close($ch) {}
}
namespace {
    if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
    define('MOODLE_INTERNAL',true);
    // The host may lack ext-curl. Only these doubles use placeholder option values.
    foreach (['CURLOPT_POST','CURLOPT_FOLLOWLOCATION','CURLOPT_CONNECTTIMEOUT','CURLOPT_TIMEOUT',
            'CURLOPT_PROTOCOLS','CURLPROTO_HTTPS','CURLOPT_SSL_VERIFYPEER','CURLOPT_SSL_VERIFYHOST',
            'CURLOPT_HTTPHEADER','CURLOPT_POSTFIELDS','CURLOPT_WRITEFUNCTION','CURLINFO_HTTP_CODE'] as $i => $name) {
        if (!defined($name)) { define($name, 90000 + $i); }
    }
    require(__DIR__.'/decision_test.php');
    require_once(__DIR__.'/../classes/jev.php');
    $CFG=(object)['local_virtuallearner_jev_enabled'=>true,'local_virtuallearner_jev_key'=>'synthetic-test-secret'];
    $vl_status=200;$vl_body=json_encode($response);
    $result=\local_virtuallearner\jev::choose($request,$spec);
    vl_assert($vl_url==='https://api.typesafe.ai/v1/systemone','fixed official HTTPS endpoint');
    vl_assert($vl_curl[CURLOPT_FOLLOWLOCATION]===false,'no redirect credential forwarding');
    vl_assert($vl_curl[CURLOPT_SSL_VERIFYPEER]===true && $vl_curl[CURLOPT_SSL_VERIFYHOST]===2,'TLS verification');
    vl_assert($vl_curl[CURLOPT_TIMEOUT]===20,'bounded transport timeout');
    vl_assert(!str_contains(json_encode($result),'synthetic-test-secret'),'no credential in audit');
    foreach ([401,429,500] as $status) {
        $vl_status=$status;$vl_body='provider body with synthetic-test-secret';
        try { \local_virtuallearner\jev::choose($request,$spec); throw new \LogicException('Expected HTTP failure'); }
        catch (\RuntimeException $e) { vl_assert(!str_contains($e->getMessage(),'synthetic-test-secret'),'error body not persisted'); }
    }
    $vl_status=200;$vl_body=str_repeat('x',65537);
    vl_reject(fn()=>\local_virtuallearner\jev::choose($request,$spec));
    $vl_body='{broken';vl_reject(fn()=>\local_virtuallearner\jev::choose($request,$spec));
    $CFG->local_virtuallearner_jev_enabled=false;
    vl_reject(fn()=>\local_virtuallearner\jev::choose($request,$spec));
    echo "PASS $count total assertions, including simulated transport (not live Jev)\n";
}
