/* Run only in the fresh CI MoodleSTACKSetup 5.3 environment. Synthetic data only. */
const {chromium}=require('playwright');
const fs=require('node:fs');const cp=require('node:child_process');
(async()=>{
 const env=Object.fromEntries(fs.readFileSync('.env53','utf8').trim().split(/\r?\n/).map(l=>l.split('=')));
 const base='http://localhost:'+env.MOODLE_PORT;
 const cron=()=>cp.execFileSync('docker',['compose','--env-file','.env53','-f','compose53.yaml','exec','-T','--user','www-data','web','php','admin/cli/cron.php'],{stdio:'pipe',timeout:120000});
 const browser=await chromium.launch({headless:true,args:['--no-sandbox']});
 try{
  const page=await browser.newPage();const errors=[];page.on('pageerror',e=>errors.push(String(e)));
  await page.goto(base+'/login/index.php');await page.locator('#username').fill('admin');await page.locator('#password').fill(env.ADMIN_PASSWORD);await page.locator('#loginbtn').click();
  await page.waitForURL(u=>!u.pathname.includes('/login/'));
  await page.goto(base+'/local/virtuallearner/index.php');
  await page.locator('input[name=name]').fill('Browser profile');await page.getByRole('button',{name:'Save as a new profile',exact:true}).click();
  await page.waitForURL(base+'/local/virtuallearner/index.php');
  await page.getByRole('button',{name:'Create a sample lab course',exact:true}).click();
  await page.waitForURL(u=>u.searchParams.has('labid'));
  const select=page.locator('select[name=profileid]');const option=await select.locator('option').filter({hasText:'Browser profile'}).getAttribute('value');await select.selectOption(option);
  await page.locator('input[name=automatic]').uncheck();await page.locator('input[name=rounds]').fill('2');
  await page.getByRole('button',{name:'Start an experiment',exact:true}).click();await page.waitForURL(u=>u.searchParams.has('runid'));
  cron();await page.reload();await page.getByRole('heading',{name:/Paused/}).waitFor();
  const json=await page.request.get(base+'/local/virtuallearner/export.php?runid='+new URL(page.url()).searchParams.get('runid')+'&format=json');
  const data=await json.json();if(data.events.length!==1||data.events[0].observation.results['1'].fraction!==0)throw Error('Expected first actual wrong response');
  const download=page.waitForEvent('download');await page.getByRole('link',{name:'Download results CSV',exact:true}).click();
  const dl=await download;const file=await dl.path();const csv=fs.readFileSync(file,'utf8');if(!csv.includes('missing_cross_term'))throw Error('CSV missing actual observation');
  await page.getByRole('button',{name:'Queue next step / retry failed step',exact:true}).click();cron();await page.reload();
  await page.getByRole('heading',{name:/Completed/}).waitFor();
  const done=await(await page.request.get(base+'/local/virtuallearner/export.php?runid='+data.runid+'&format=json')).json();
  if(done.events.length!==2||done.events[1].observation.results['1'].fraction!==1)throw Error('Expected learning and correct second response');
  const anonymous=await browser.newPage();await anonymous.goto(base+'/local/virtuallearner/export.php?runid='+data.runid+'&format=json');
  if(!anonymous.url().includes('/login/'))throw Error('Unauthenticated export was not blocked');
  if(errors.length)throw Error(errors.join('\n'));console.log('PASS real browser: profile, sample course, manual step/pause/resume, STACK wrong→correct, CSV/JSON, anonymous access guard');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});
