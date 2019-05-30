<?php
date_default_timezone_set("America/Chicago");
chdir('/www/rpf/html/tu');
set_time_limit(585);   // 9:45 minutes
$thread=0;
if(isset($_GET['thread'])) $thread=$_GET['thread'];
if(isset($argv[1])) $thread=$argv[1];
$bad_sess_file="bad_session_".$thread.".txt";
include("sendmail.php");
function AddLog($mess) {
	global $thread;
	file_put_contents("cron_log.txt",date("Y-m-d g:i:sa")." ($thread) - ".$mess."\r\n",FILE_APPEND);
	echo "($thread) $mess".chr(13).chr(10);	
	// echo $mess."<br>";
}
function JobLog($job,$mess) {
	global $thread;
	file_put_contents("JOB_$job/job_log.txt",$mess."\r\n",FILE_APPEND);
	echo "($thread) $mess".chr(13).chr(10);	
	// echo $mess."<br>";
}

function fix_timestamp($str) {
if($str=="") return("");
$ans=substr($str,5,5)."-".substr($str,0,4);
$ampm="am";
$hr=(int) substr($str,11,2);
$min=substr($str,14,2);
if($hr > 11) {$ampm="pm";}
if($hr > 12) {$hr=$hr-12; }
if($hr==0) { $hr="12"; $ampm="am"; }
$ans.=" ".$hr.":".$min.$ampm;
if(strlen($ans) < 5) $ans="";
return($ans);
}

$doit=0;
if(date("w") > 0 AND date("w") < 6) $doit++;   // only send reminders Mon - Fri
if(date("H") > "06" AND date("H") < "19") $doit++;  // only send between 7a-6p
if($doit < 2) {
	echo "Not between 7am and 6pm  on Mon-Fri - aborting.";
	sleep(10);
	die();
}

$debug=true;
AddLog("Begin Scraping Transunion TLO");

include("dbopen_new.php");
// load accounts and passwords
$sql="SELECT * FROM accounts WHERE id=1";
$check=$db->query($sql) or trigger_error("$sql - Error: ".mysqli_error($db), E_USER_ERROR);
$acct_info = $check->fetch_assoc();

// get current user for this thread
$cur_acct="";
if($thread==1) $cur_acct=$acct_info['acct_1'];
if($thread==2) $cur_acct=$acct_info['acct_2'];
if($thread==3) $cur_acct=$acct_info['acct_3'];
if($thread==4) $cur_acct=$acct_info['acct_4'];
if($thread==5) $cur_acct=$acct_info['acct_5'];
if($thread==6) $cur_acct=$acct_info['acct_6'];
if($thread==7) $cur_acct=$acct_info['acct_7'];
if($thread==8) $cur_acct=$acct_info['acct_8'];
if($thread==9) $cur_acct=$acct_info['acct_9'];
if($thread==10) $cur_acct=$acct_info['acct_10'];

if(isset($_GET['balance'])) {
	echo "<br>Running thread balance of remaining debtors...<br>";
	$sql="SELECT id FROM ssn WHERE status=0";
	$check=$db->query($sql) or trigger_error("$sql - Error: ".mysqli_error($db), E_USER_ERROR);
	$tc=1;
	$ab=0;
	while($info = $check->fetch_assoc()) {
		$did=$info['id'];
		$sql="UPDATE ssn SET thread=$tc WHERE id=$did";
		$db->query($sql) or trigger_error("$sql - Error: ".mysqli_error($db), E_USER_ERROR);
		$tc++;
		if($tc > 10) $tc=1;
		$ab++;
	}
	echo "$ab balanced - done.";
	die();
}

// get list of live jobs
$sql="SELECT * FROM jobs WHERE live=1 ORDER BY id DESC LIMIT 1";
$check=$db->query($sql) or trigger_error("$sql - Error: ".mysqli_error($db), E_USER_ERROR);

if(! $check->num_rows) {
	$db->close();
	AddLog("No jobs running.".chr(13).chr(10));
	die();
}
$job_info = $check->fetch_assoc();
$job_id=$job_info['id'];
$job_email=$job_info['email'];

// check to see if previous sessions have failed
if(file_exists($bad_sess_file)) { $bs=file_get_contents($bad_sess_file); } else {$bs=0; }
// if more than 2 bad sessions as operator to pass CAPTCHA test manually
if($bs > 2) {
	$sql="SELECT * FROM ssn WHERE job_id=$job_id AND status=0 AND thread=$thread LIMIT 1";
	$check=$db->query($sql) or trigger_error("$sql - Error: ".mysqli_error($db), E_USER_ERROR);
	$info = $check->fetch_assoc();
	$db->close();
	$html="The TU TLO scrape job JOB_$job_id thread $thread - $cur_acct has been stopped due to too many search failures. ";
	$html.="This can be caused by CAPTCHA blocking the account.  To fix, log in manaully at website, run a search then log out.";
	$html.="Now re-start the scrape job.";
	if($info['last_name']) {
		$html.="<br><br>".$info['ssn']." - ".$info['first_name']." ".$info['last_name']." - ".$info['address']." ".$info['city']." ".$info['state']." ".$info['zip'];
	}
	SendMail("TLO Operator", $job_email, "Bad session has stopped TLO JOB_$job_id in thread $thread", $html);
	die();
}

// check to see if  any records for this thread to process
$sql="SELECT * FROM ssn WHERE job_id=$job_id AND status=0 AND thread=$thread LIMIT 1";
$check=$db->query($sql) or trigger_error("$sql - Error: ".mysqli_error($db), E_USER_ERROR);
if(! $check->num_rows) {
	$db->close();
	AddLog("No records to process for this thread. Job aborted.");
	die();
}

AddLog("Running job $job_id");

//create job folder if not there
$mydir="c:/www/rpf/html/tu/JOB_".$job_id."/";
if (! is_dir($mydir) ) mkdir($mydir,0777,TRUE);

// let operator know job has started
if(! file_exists("JOB_$job_id/job_log.txt")) {
	JobLog($job_id,"JOB_$job_id TLO Scrape started ".date("Y-m-d g:i:sa"));
	SendMail("TLO Operator", $job_email, "TU TLO JOB_$job_id just started", "job started ".date("Y-m-d g:i:sa"));
}

// start up delay - let's make it look like a human is running the searches
$delay=rand(4,13);
sleep($delay);

// go to TLO website landing page
$z=array();
$z['cookiefile']="c:/www/rpf/html/tu/cookie_$thread.txt";
$z['refer']="https://google.com";
$list_data=fetch("https://tloxp.tlo.com",$z);
if($debug) AddLog("At landing page.");
$nonce=GrabIt($list_data,'id="nonce" value="','"',300,true);
$delay=rand(2,3);
sleep($delay);

// get login credentials for this session
$z['refer']="https://tloxp.tlo.com";
if($thread==1) $z['post_fields']="email=".$acct_info['acct_1']."&password=".$acct_info['pass_1'];
if($thread==2) $z['post_fields']="email=".$acct_info['acct_2']."&password=".$acct_info['pass_2'];
if($thread==3) $z['post_fields']="email=".$acct_info['acct_3']."&password=".$acct_info['pass_3'];
if($thread==4) $z['post_fields']="email=".$acct_info['acct_4']."&password=".$acct_info['pass_4'];
if($thread==5) $z['post_fields']="email=".$acct_info['acct_5']."&password=".$acct_info['pass_5'];
if($thread==6) $z['post_fields']="email=".$acct_info['acct_6']."&password=".$acct_info['pass_6'];
if($thread==7) $z['post_fields']="email=".$acct_info['acct_7']."&password=".$acct_info['pass_7'];
if($thread==8) $z['post_fields']="email=".$acct_info['acct_8']."&password=".$acct_info['pass_8'];
if($thread==9) $z['post_fields']="email=".$acct_info['acct_9']."&password=".$acct_info['pass_9'];
if($thread==10) $z['post_fields']="email=".$acct_info['acct_10']."&password=".$acct_info['pass_10'];
$z['post_fields'].="&nonce=".$nonce;
$z['post']=3; 

sleep(9);
// attempt login
$list_data=fetch("https://tloxp.tlo.com/login.php",$z);
if($debug) AddLog("Login attempted...");
$delay=rand(2,4);
sleep($delay);

// send permissible purpose stuff
$z['refer']="Referer: https://tloxp.tlo.com/login.php";
$z['post_fields']="glb_use=4&dppa_use=2";
$z['post']=2; 
$list_data=fetch("https://tloxp.tlo.com/glbdppa.php",$z);
if($debug) AddLog("Sent PPC stuff.");
$delay=rand(2,3);
sleep($delay);

// now look at page to see if we are logged in
if( stristr($list_data,$acct_info['acct_1']."</span>") or stristr($list_data,$acct_info['acct_2']."</span>") or stristr($list_data,$acct_info['acct_3']."</span>") or
	stristr($list_data,$acct_info['acct_4']."</span>") or stristr($list_data,$acct_info['acct_5']."</span>") or stristr($list_data,$acct_info['acct_6']."</span>") or
	stristr($list_data,$acct_info['acct_7']."</span>") or stristr($list_data,$acct_info['acct_8']."</span>") or stristr($list_data,$acct_info['acct_9']."</span>") or
	stristr($list_data,$acct_info['acct_10']."</span>") )
{
	if($debug)  AddLog("Login successful.  Account: ".$acct_info['acct_'.$thread]);
}
else
{
	if($debug) AddLog("Login failed.  Job aborted.");
	SendMail("TLO Operator", $job_email, "TU TLO JOB_$job_id thread $thread failed login", "TU TLO login failed ".date("Y-m-d g:i:sa"));
	die();
}

// start loop here of SSN's to process

$speed=explode("-",file_get_contents("speed.txt"));
$n1=round($speed[0]/6);
if(! $n1) $n1=1;
$n2=round($speed[1]/6);
if(! $n2) $n2=1;
$how_many=rand($n1,$n2);
if($debug) AddLog("Processing $how_many this session.");

$slumber=round(420 / $how_many);   //  delay between pulls

// $how_many=rand(6,9);

$sql="SELECT * FROM ssn WHERE job_id=$job_id AND status=0 AND thread=$thread LIMIT $how_many";
$check=$db->query($sql) or trigger_error("$sql - Error: ".mysqli_error($db), E_USER_ERROR);
while($info = $check->fetch_assoc()) {
// $ref="DD_1";  $ssn="666665555";   // daisy duck
// $ref="FF_1";  $ssn="666223333";   // fred flintstone

$file_name=ValChars($info['debt_id'])."_".ValChars($info['party_id'])."_".ValChars($info['first_name'])."_".ValChars($info['last_name']);

// create CSV export file if not there
$ex_header="debt_id,party_id, ssn, first_name, last_name,phone_1,phone_2,phone_3,phone_4,phone_5".chr(13).chr(10);
if(! file_exists($mydir."job_".$job_id.".csv")) file_put_contents($mydir."job_".$job_id.".csv",$ex_header);

$ref=$info['debt_id'];
$ssn=$info['ssn'];

// test file
// $ref="FF_1";  $ssn="666223333";   // fred flintstone
// $info['first_name']="Fred"; $info['last_name']="Flintstone"; $info['ssn']=$ssn;

if($debug) AddLog("Working on ".$info['first_name']." ".$info['last_name']." SSN $ssn Debt ID $ref");
sleep(1);

// go to advanced search page
$z['refer']="Referer: https://tloxp.tlo.com/index.php";
unset($z['post_fields']);
unset($z['post']);
$list_data=fetch("https://tloxp.tlo.com/search.php?type=PersonSearch&mode=advanced",$z);

if($debug) AddLog("At advanced search page.");
$delay=rand(3,5);
sleep($delay);

// grab nonce to keep connection current 
$nonce=GrabIt($list_data,'TLOxp.nonce = "','"',200,true);
if($debug) AddLog("NONCE=$nonce");

if(strlen($nonce) < 5) {
	// logout
	if($debug) AddLog("NONCE  missing - job aborted.");
	$z=array();
	$z['cookiefile']="c:/www/rpf/html/tu/cookie_$thread.txt";
	$z['refer']="Referer: https://tloxp.tlo.com/search.php";
	$list_data=fetch("https://tloxp.tlo.com/logout.php",$z);
	if($debug) AddLog("Logged out.".chr(13).chr(10));
	$html.="NONCE missing in thread $thread - job aborted.";
	SendMail("TLO Operator", $job_email, "TLO JOB missing NONCE", $html);
	die();
}

// run search of SSN
$z['refer']="Referer: https://tloxp.tlo.com/search.php?type=PersonSearch&mode=advanced";
if($ssn) {
	$z['post_fields']="action=search&nonce=".$nonce."&StartRecord=1&type=PersonSearch&mode=advanced&nameinputs=&LastName=&FirstName=&MiddleName=&Address=&CityStateZip=&Phone=&DateFirstSeen=&DateLastSeen=&Radius=&DOB=&StartAgeRange=&EndAgeRange=&SSN=".$ssn."&DlDomainEmailIp=&Token=&layout=locate&reference=".$ref."&recordsPerPage=10";
	if($debug) AddLog("SSN search run on ".$info['first_name']." ".$info['last_name']);
	$type_html="SSN search run on ".$info['first_name']." ".$info['last_name']." - ".$info['ssn'];

}
else
{
	$fname=$info['first_name'];
	$lname=$info['last_name'];
	$addr=$info['address'];
	if(strlen($info['zip']) < 5) {
		$citystzip=$info['city'].", ".$info['state'];
	}
	else
	{
		$citystzip=$info['city'].", ".$info['state']." ".$info['zip'];
	}
	// $citystzip=$info['zip'];
	$z['post_fields']="action=search&nonce=".$nonce."&StartRecord=1&type=PersonSearch&mode=advanced&nameinputs=&LastName=".$lname."&FirstName=".$fname."&MiddleName=&Address=".$addr."&CityStateZip=".$citystatezip."&Phone=&DateFirstSeen=&DateLastSeen=&Radius=&DOB=&StartAgeRange=&EndAgeRange=&SSN=&DlDomainEmailIp=&Token=&layout=locate&reference=".$ref."&recordsPerPage=10";
	if($debug) AddLog("Name and Address search run on ".$info['first_name']." ".$info['last_name']." - ".$addr." ".$citystzip);
	$type_html="Name and Address search run on ".$info['first_name']." ".$info['last_name']." - ".$addr." ".$citystzip;
}
$tf=substr_count($z['post_fields'], '&');
$z['post']=$tf+1;
$list_data=fetch("https://tloxp.tlo.com/search.php",$z);
$delay=rand(3,5);
sleep($delay);

// check to see if results found
$good_hit=0;
if(stristr($list_data,"results found") OR stristr($list_data,"result found")) $good_hit=1;

if(stristr($list_data,"We're sorry, there were no results")) $good_hit=0;  // NO HIT

if($good_hit) {
	if($debug) AddLog("Initial hit on consumer - checking for token and title.");

	$token=GrabIt($list_data,"#collectionsreporttoken').val('","')",200,true);
	$title=GrabIt($list_data,"#collectionsreporttitle').val('","')",200,true);
	if($debug) AddLog("Reported token=".$token." $crlf title=".$title);
		
	if($token AND $title) {
		$good_hit=2;
	}
	else
	{
		// check for CAPTCHA challenge - if there notify operator and have them process manaully
		$captcha=0;
		if(stristr($list_data,"Please fill in captcha to Continue")) {
			$captcha=8;  // CAPTCHA block
			$cap_mess="CAPTCHA has stopped thread $thread - $cur_acct from working.  Please follow these steps:<br><br>(1) Log in to TU TLO as user $cur_acct";
			$cap_mess.=" and perform a search on the debtor below.<br>";
			$cap_mess.="Be sure to complete the CAPTCHA challenge and finish the search.  When you see search results you may log out.";
			$cap_mess.="<br><br>(2) Click the red ball icon on the TLO scrape page to reset the paused thread.<br><br>".$type_html;
			SendMail("TLO Operator", $job_email, "CAPTCHA has stopped TLO JOB_$job_id in thread $thread", $cap_mess);
		}
		// logout
		if($debug) AddLog("Session gone bad - job aborted.");
		$z=array();
		$z['cookiefile']="c:/www/rpf/html/tu/cookie_$thread.txt";
		$z['refer']="Referer: https://tloxp.tlo.com/search.php";
		$list_data=fetch("https://tloxp.tlo.com/logout.php",$z);
		if($debug) AddLog("Logged out.".chr(13).chr(10));
		// bump bad session counter
		if(file_exists($bad_sess_file)) { $bs=file_get_contents($bad_sess_file); } else {$bs=0; }
		$bs=$bs+1 + $captcha;
		file_put_contents($bad_sess_file,$bs);
		die();
	}
}
if($good_hit==2) {

	file_put_contents($bad_sess_file,"0");

	// grab NONCE from page
	$nonce=GrabIt($list_data,'name="nonce" value="','"',200,true);

	if($debug) AddLog("HIT on debtor search$crlf token=".$token." $crlf title=".$title);
	
	// do session ping
	unset($z['post_fields']);
	unset($z['post']);
	$list_data=fetch("https://tloxp.tlo.com/jsonUser.php?action=ping",$z);
	$delay=rand(1,2);
	sleep($delay);

	// locate report
	/*
	type=Locate Report&title=DAISY DUCK&url=search.php?action=search&type=CollectionsReport&Token=4F92-GD33&nonce=MTYyMjU5NTUzMDU2OTY4NWY4NDg3NzQwLjYwOTgzMDQy&ShowAddresses=1&ShowEmailAddresses=1&ShowPhones=1&ShowBusinessPhones=1&ShowRelatives1stDegree=1&ShowRelatives2ndDegree=1&ShowRelatives3rdDegree=1&ShowLikelyAssociates=1&ShowPossibleAssociates=1&ShowNeighbors=1&ShowBusinessAssociations=1&ShowCorporateFilings=1&ShowCurrentMotorVehicles=1&ShowPastMotorVehicles=1&ShowCurrentPropertyDeeds=1&ShowPastPropertyDeeds=1&ShowLiens=1&ShowJudgments=1&ShowBankruptcies=1&ShowEmployers=1&noTables=1
	*/
	/*
	POST /loading.php  type=Locate Report&title=LARA ELLEN EPPS&url=search.php?action=search&type=CollectionsReport&Token=LVT3-W936&nonce=MTY4NDU4MTIyNDU2OTk3MDVkZTg3NzMxLjIzMTMzNzc4&ShowAddresses=1&ShowEmailAddresses=1&ShowPhones=1&ShowBusinessPhones=1&ShowRelatives1stDegree=1&ShowRelatives2ndDegree=1&ShowRelatives3rdDegree=1&ShowLikelyAssociates=1&ShowPossibleAssociates=1&ShowNeighbors=1&ShowBusinessAssociations=1&ShowCorporateFilings=1&ShowCurrentMotorVehicles=1&ShowPastMotorVehicles=1&ShowCurrentPropertyDeeds=1&ShowPastPropertyDeeds=1&ShowLiens=1&ShowJudgments=1&ShowBankruptcies=1&ShowEmployers=1&noTables=1
	*/
	$z['refer']="Referer: https://tloxp.tlo.com/search.php";
	$z['post_fields']="type=Locate Report&title=".$title."&url=search.php?action=search&type=CollectionsReport&Token=".$token."&nonce=".$nonce."&ShowAddresses=1&ShowEmailAddresses=1&ShowPhones=1&ShowBusinessPhones=1&ShowRelatives1stDegree=1&ShowRelatives2ndDegree=1&ShowRelatives3rdDegree=1&ShowLikelyAssociates=1&ShowPossibleAssociates=1&ShowNeighbors=1&ShowBusinessAssociations=1&ShowCorporateFilings=1&ShowCurrentMotorVehicles=1&ShowPastMotorVehicles=1&ShowCurrentPropertyDeeds=1&ShowPastPropertyDeeds=1&ShowLiens=1&ShowJudgments=1&ShowBankruptcies=1&ShowEmployers=1&noTables=1";
	$tf=substr_count($z['post_fields'], '&');
	$z['post']=$tf+1;
	$list_data=fetch("https://tloxp.tlo.com/loading.php",$z);
	$delay=rand(5,9);
	sleep($delay);
	if($debug) AddLog("Locate report - step 1.");
	
	// grab NONCE from page
	// $nonce=GrabIt($list_data,'name="nonce" value="','"',200,true);
	
	$z['post_fields']="action=search&type=CollectionsReport&Token=".$token."&nonce=".$nonce."&ShowAddresses=1&ShowEmailAddresses=1&ShowPhones=1&ShowBusinessPhones=1&ShowRelatives1stDegree=1&ShowRelatives2ndDegree=1&ShowRelatives3rdDegree=1&ShowLikelyAssociates=1&ShowPossibleAssociates=1&ShowNeighbors=1&ShowBusinessAssociations=1&ShowCorporateFilings=1&ShowCurrentMotorVehicles=1&ShowPastMotorVehicles=1&ShowCurrentPropertyDeeds=1&ShowPastPropertyDeeds=1&ShowLiens=1&ShowJudgments=1&ShowBankruptcies=1&ShowEmployers=1&noTables=1";
	$tf=substr_count($z['post_fields'], '&');
	$z['post']=$tf+1;
	$list_data=fetch("https://tloxp.tlo.com/search.php",$z);
	$delay=rand(3,7);
	sleep($delay);
	if($debug) AddLog("Locate report - step 2."); 
	
	// grab NONCE from page to use in search
	$nonce=GrabIt($list_data,'name="nonce" value="','"',200,true);

	// get file download link
	$pf=GrabIt($list_data,'_target = "search.php?','";',2000,true);
	/*
	action=search&type=CollectionsReport&Token=4F92-GD33&nonce=NzQ3NjM1MTY1Njk2YTgzMGJkODFhMS43ODgyMDA3Nw%3D%3D&noTables=1&sd%5B%5D=sbV65fdUNyNG7RbIJSkEmZZrPC0vx1e6YMpke3ZYkqSat6r4YtVbMlaUFD%2FRo%2FZCVW1j1WNwQJ5CueOnsWVSwnnEj9JIC7MBdWRGVtCgyRh9JMKHFa62tCBfzQ3L37PnhNvDOMjlOkImAzPSHCr411SBK6T3VkIBY1wApC0e4L7GcfBsMnWGy6Zh0X2mhd%2BHB5dm2u9xmqChHkrmR8nzMepGbmqj5%2B76QU9LVFrcbx1NMUAiNP8zM72KRZTn6wdcY5II3d3WOR%2F5q0FUNCeyr2hamMxqhdPeKyh33ChiIiD0ndo7v3FcwdEOPSkPdDevs3NUNVDSnM0xMAxXvFNsmzGRmtpAjc99%2FgBZNFU1%2FZbFT4WZUtjMhLdqYBL50u0z3XUwmsuwm7Oup%2BhtpFw2e%2BUO9bmZBtHMcCewi94QOouTfGxtVRsY1EiDf39RbLZDTigOqzgL5R5wJhExGXKCZ5iTvfxFF5KXuysBuAvj%2BAOwZbC5OOuDl%2FAHMcGI9mcKs3UtZuW%2Bv0uarBQr6qHtoQPE%2FTHcjQkwrDdq04A3Fn4wGsatryl41Jwu8fPrziQslfCAciGkJK4NNSBHZ3h0RoHhBCY8SgkcaAF8U8Rv%2BmnHhs5FhP6Bgi532KuOqeYHMeHugHODfL6WdQ%2FcJgwLWB7qMf9HWz3YWn3342BqwYQuGW34X06OsnTLtt0YHHX4gz%2F6kvWxKPNnigF1TYP5dG7tgIdt%2BTQA%2FUP6lTHi98U70kWgP2yH4MMGWiGfrsPu%2B4MFtaO4GG5uAz%2F9lYfGqmAAgGxTGl744%2BVnOSH4xUdVaL3Z6tzzM9XEh93rQ%2BBj7b4Jbg2goKRE5nFMyEfLMrt5n1P3%2Frl6NwWYgWh0wmn0PgD4r2HDagAmDqwnZjsUYDhKuWuVKtdBwBB2yv3Gg%2FY9e2NK0yfj4PKVzuYuQACbAsswodoL0dUIYHUbRv9fQnkNT%2FmGczgqy0VK29YeBvadjfbpBny4BHJYEP%2B%2FoAy74FgoRZjDDHmR8OH3nd1i&format=pdf&includeTOC=1&enableLinks=1&enableIcons=1&includeHeader=1&includeFooter=1&fontsize=normal&orientation=default&download=1
	*/

	// grab PDF file
	$pf_full=$pf."&format=pdf&includeTOC=1&enableLinks=1&enableIcons=1&includeHeader=1&includeFooter=1&fontsize=normal&orientation=default&download=1";
	$z['refer']="Referer: https://tloxp.tlo.com/search.php";
	$z['post_fields']=$pf_full;
	$tf=substr_count($z['post_fields'], '&');
	$z['post']=$tf+1;
	$list_data=fetch("https://tloxp.tlo.com/search.php",$z);
	file_put_contents($mydir.$file_name.".pdf",$list_data);
	$size=strlen($list_data);
	if($debug) AddLog("Saved PDF file.  Size: ".ToK($size));
	$delay=rand(4,6);
	sleep($delay);

	// grab TXT file
	$pf_full=$pf."&format=txt&includeTOC=1&enableLinks=1&enableIcons=1&includeHeader=1&includeFooter=1&fontsize=normal&orientation=default&download=1";
	$z['refer']="Referer: https://tloxp.tlo.com/search.php";
	$z['post_fields']=$pf_full;
	$tf=substr_count($z['post_fields'], '&');
	$z['post']=$tf+1;
	$list_data=fetch("https://tloxp.tlo.com/search.php",$z);
	$size=strlen($list_data);
	$temp = str_replace("\n", "\r\n", $list_data);
	file_put_contents($mydir.$file_name.".txt",$temp);
	if($debug) AddLog("Saved TXT file.  Size: ".ToK($size));
	sleep(2);
	//  extract data from TXT file and save in export CSV
	Parse($mydir.$file_name.".txt",$mydir."job_".$job_id.".csv",$info);
		
	//  mark record as done and update job
	$sql="UPDATE ssn SET status=1 WHERE id=".$info['id'];
	$db->query($sql) or trigger_error("$sql - Error: ".mysqli_error($db), E_USER_ERROR);
}
else
{
	// SSN was a no hit
	AddLog("NO HIT on ".$info['first_name']." ".$info['last_name']."  Debt ID: ".$info['debt_id']."  Party ID: ".$info['party_id']);
	JobLog($job_id,"NO HIT on ".$info['first_name']." ".$info['last_name']."  Debt ID: ".$info['debt_id']."  Party ID: ".$info['party_id']); 
	// no record found
	$sql="UPDATE ssn SET status=2 WHERE id=".$info['id'];
	$db->query($sql) or trigger_error("$sql - Error: ".mysqli_error($db), E_USER_ERROR);
	// write out empty files
	$nr=file_get_contents("no_record_found.pdf");
	file_put_contents($mydir.$file_name.".pdf",$nr);
	file_put_contents($mydir.$file_name.".txt","NO RECORD FOUND");	
}

// bump counter of debtors processed
$sql="SELECT recs_done FROM jobs WHERE id=$job_id";
$bc=$db->query($sql) or trigger_error("$sql - Error: ".mysqli_error($db), E_USER_ERROR);
$b_info = $bc->fetch_assoc();
$tmp=$b_info['recs_done'];
$tmp++;
$sql="UPDATE jobs SET recs_done=$tmp WHERE id=$job_id";
$db->query($sql) or trigger_error("$sql - Error: ".mysqli_error($db), E_USER_ERROR);

// $delay=rand(2,9);

if($debug) AddLog("Pausing $slumber seconds.".chr(13).chr(10));
sleep($slumber);

}  // end loop here of SSN's to process

// check to see if entire job is complete
$sql="SELECT * FROM ssn WHERE job_id=$job_id AND status=0 LIMIT 1";
$check=$db->query($sql) or trigger_error("$sql - Error: ".mysqli_error($db), E_USER_ERROR);
if(! $check->num_rows) {
	$sql="UPDATE jobs SET done_flag=1, completed='".date("Y-m-d H:i:s")."' WHERE id=$job_id";
	$db->query($sql) or trigger_error("$sql - Error: ".mysqli_error($db), E_USER_ERROR);
	if($debug) AddLog("Job $job_id is finished.  No more records to process.");
	JobLog($job_id,"JOB_$job_id TLO Scrape completed ".date("Y-m-d g:i:sa"));
	$temp=file_get_contents("JOB_$job_id/job_log.txt");
	$html=nl2br($temp);
	// get total records
	$sql="SELECT id FROM ssn WHERE job_id=$job_id";
	$tchk=$db->query($sql) or trigger_error("$sql - Error: ".mysqli_error($db), E_USER_ERROR);
	$tot_recs=$tchk->num_rows;
	// get NO-HITS
	$sql="SELECT id FROM ssn WHERE job_id=$job_id AND status=2";
	$tchk=$db->query($sql) or trigger_error("$sql - Error: ".mysqli_error($db), E_USER_ERROR);
	$tot_nohits=$tchk->num_rows;
	$tot_good=$tot_recs  - $tot_nohits;
	$html.="<br>$tot_recs total records processed.  $tot_good found and $tot_nohits NO HITS";
	if($job_info['email'])	SendMail("", $job_info['email'],"TU TLO JOB_$job_id just finished", $html);
}

$db->close();

// logout
$z=array();
$z['cookiefile']="c:/www/rpf/html/tu/cookie_$thread.txt";
$z['refer']="Referer: https://tloxp.tlo.com/search.php";
$list_data=fetch("https://tloxp.tlo.com/logout.php",$z);
if($debug) AddLog("Logged out.".chr(13).chr(10));

die();

function Parse($file_source,$file_csv,$info) {
	$csv="";  $look=false;
	$phones=array();
	$file = new SplFileObject($file_source);
	while (!$file->eof()) {
		$line=$file->fgets();
		$line=str_replace (array("\r\n", "\n", "\r"), '', $line);
		if(stristr($line,"Best Numbers to call for subject:")) $look=true;
		if(stristr($line,"Commercial Numbers found at subject's addresses:")) $look=false;
		if($look) {
			if(substr($line,0,1)=="(" AND substr($line,4,1)==")") {
				$phn=substr($line,0,14);
				$phn=preg_replace('/[^0-9]/', '', $phn);
				$per=substr($line,-5);
				$per=preg_replace('/[^0-9]/', '', $per);
				if($per > 69) $phones[]=substr("000".$per,-3).$phn;
			}
		}
	}
	if($phones) {
		// sort phone numbers by % likely descending
		asort($phones);
		$phones=array_reverse($phones);
		$csv='"'.$info['debt_id'].'","'.$info['party_id'].'","***-**-'.substr($info['ssn'],-4).'","'.$info['first_name'].'","'.$info['last_name'].'",';
		if(isset($phones[0])) { $csv.='"'.substr($phones[0],-10).'",'; } else { $csv.='"",'; }
		if(isset($phones[1])) { $csv.='"'.substr($phones[1],-10).'",'; } else { $csv.='"",'; }		
		if(isset($phones[2])) { $csv.='"'.substr($phones[2],-10).'",'; } else { $csv.='"",'; }
		if(isset($phones[3])) { $csv.='"'.substr($phones[3],-10).'",'; } else { $csv.='"",'; }
		if(isset($phones[4])) { $csv.='"'.substr($phones[4],-10).'"'; } else { $csv.='""'; }
	}
	$file = null;  // release SplFileObject
	if($csv) file_put_contents($file_csv,$csv.chr(13).chr(10),FILE_APPEND);
	return;
}

function ToK($number) {
    if($number >= 1024) {
       return intval($number/1024) . "k"; 
    }
    else {
        return $number;
    }
}

function ValChars($stf)
{
$valid_chars="00123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ-_. ".CHR(34);
$ans="";
$lgth=strlen($stf);
for ($cnt = 0; $cnt < $lgth; $cnt++) {
	$char = substr($stf, $cnt, 1);
	if($char=="0") {
		$ans.="0";
	}
	else
	{
		if(strpos($valid_chars,$char) != false) { $ans.=$char; }
	}
}
return $ans;
}

function fetch( $url, $z=null ) {
	$timeout=10;
        $ch =  curl_init();

        $useragent = isset($z['useragent']) ? $z['useragent'] : 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:10.0.2) Gecko/20100101 Firefox/10.0.2';

        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_AUTOREFERER, true );
        curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
	if( isset($z['post']) ) curl_setopt( $ch, CURLOPT_POST, $z['post'] );
        if( isset($z['post_fields']) )   curl_setopt( $ch, CURLOPT_POSTFIELDS, $z['post_fields'] );
        if( isset($z['refer']) )   curl_setopt( $ch, CURLOPT_REFERER, $z['refer'] );
        curl_setopt( $ch, CURLOPT_USERAGENT, $useragent );
        curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, ( isset($z['timeout']) ? $z['timeout'] : 10 ) );
        curl_setopt( $ch, CURLOPT_COOKIEJAR,  $z['cookiefile'] );
        curl_setopt( $ch, CURLOPT_COOKIEFILE, $z['cookiefile'] );
	curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER ,false); 
	curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, $timeout );
	curl_setopt( $ch, CURLOPT_TIMEOUT, $timeout );
	curl_setopt( $ch, CURLOPT_ENCODING, "" );
	curl_setopt( $ch, CURLOPT_MAXREDIRS, 10 );
		    
	//curl_setopt($ch, CURLOPT_VERBOSE, true);
	//$verbose = fopen('php://temp', 'rw+');
	//curl_setopt($ch, CURLOPT_STDERR, $verbose); 
	    
	$result = curl_exec( $ch );
	// $response = curl_getinfo( $ch );
	    
	//rewind($verbose);
	//$verboseLog = stream_get_contents($verbose);
	//echo "Verbose information:\n<pre>", htmlspecialchars($verboseLog), "</pre>\n"; 
        curl_close( $ch );
			
        return $result;
    }
   
function GrabIt($data,$key,$stopper,$max=100,$raw=false) {
$ans="";
$pos=strpos($data,$key);
if($pos > 0) {
	$snip=substr($data,$pos+strlen($key),$max);
	$end=strpos($snip,$stopper);
	$ans=substr($snip,0,$end);
	$ans=trim( preg_replace( '/\s+/', ' ', $ans ) );  // remove crlf
	if(! $raw) {
		$ans=strip_tags($ans);
		$ans=str_replace(chr(34).">","",$ans);
	}
	// $ans = preg_replace('/[^a-zA-Z0-9\s\-=+\|!@#$%^&*()`~\[\]{};:\'",<.>\/?]/', '', $ans);
}
return($ans);
}   

?>