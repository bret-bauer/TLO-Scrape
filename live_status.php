<?php
date_default_timezone_set("America/Chicago");
chdir('/www/rpf/html/tu');
include("dbopen_new.php");
$sql="SELECT * FROM jobs WHERE live=1 ORDER BY id DESC LIMIT 1";
$check=$db->query($sql) or trigger_error("$sql - Error: ".mysqli_error($db), E_USER_ERROR);
$job_info = $check->fetch_assoc();
if($job_info['live']) {
	echo "<center><font size=5>Working on ".$job_info['title'];
	$rd=$job_info['recs_done'];
	if($rd < 1) $rd=1;
	if($job_info['done_flag']) {
		echo "<br>".$job_info['recs_done']." of ".$job_info['recs_total']." processed.";
		echo "<br>Job Finished.";
		$sql="UPDATE jobs SET done_flag=0,live=0 WHERE id=".$job_info['id'];
		$db->query($sql) or trigger_error("$sql - Error: ".mysqli_error($db), E_USER_ERROR);
	}
	else
	{
		$tit1="thread 1 running"; $tit2="thread 2 running"; $tit3="thread 3 running";
		$tit4="thread 4 running"; $tit5="thread 5 running"; $tit6="thread 6 running";
		$tit7="thread 7 running"; $tit8="thread 8 running"; $tit9="thread 9 running";
		$tit10="thread 10 running";

		$image_1="green_ball.jpg"; $image_2="green_ball.jpg"; $image_3="green_ball.jpg";
		$image_4="green_ball.jpg"; $image_5="green_ball.jpg"; $image_6="green_ball.jpg";
		$image_7="green_ball.jpg"; $image_8="green_ball.jpg"; $image_9="green_ball.jpg";
		$image_10="green_ball.jpg";

		$bs=file_get_contents("bad_session_1.txt"); if($bs > 0) { $image_1="red_ball.jpg"; $tit1="reset thread 1"; }
		$bs=file_get_contents("bad_session_2.txt"); if($bs > 0) { $image_2="red_ball.jpg"; $tit2="reset thread 2"; }
		$bs=file_get_contents("bad_session_3.txt"); if($bs > 0) { $image_3="red_ball.jpg"; $tit3="reset thread 3"; }
		$bs=file_get_contents("bad_session_4.txt"); if($bs > 0) { $image_4="red_ball.jpg"; $tit1="reset thread 4"; }
		$bs=file_get_contents("bad_session_5.txt"); if($bs > 0) { $image_5="red_ball.jpg"; $tit2="reset thread 5"; }
		$bs=file_get_contents("bad_session_6.txt"); if($bs > 0) { $image_6="red_ball.jpg"; $tit6="reset thread 6"; }
		$bs=file_get_contents("bad_session_7.txt"); if($bs > 0) { $image_7="red_ball.jpg"; $tit7="reset thread 7"; }
		$bs=file_get_contents("bad_session_8.txt"); if($bs > 0) { $image_8="red_ball.jpg"; $tit8="reset thread 8"; }
		$bs=file_get_contents("bad_session_9.txt"); if($bs > 0) { $image_9="red_ball.jpg"; $tit9="reset thread 9"; }
		$bs=file_get_contents("bad_session_10.txt"); if($bs > 0) { $image_10="red_ball.jpg"; $tit10="reset thread 10"; }

		$tr=$job_info['recs_total'];
		if($tr) {$per=$rd/$tr; $per=intval($per*100); } else { $per=0; }
		echo "<br>Processing record $rd of ".$job_info['recs_total']." - $per% complete</font><br>";
		echo "<a href='home.php?reset=1'><img src='$image_1' height=14 title='$tit1'></a>";
		echo "<a href='home.php?reset=2'><img src='$image_2' height=14 title='$tit2'></a>";
		echo"<a href='home.php?reset=3'><img src='$image_3' height=14 title='$tit3'></a>";
		echo "<a href='home.php?reset=4'><img src='$image_4' height=14 title='$tit4'></a>";
		echo "<a href='home.php?reset=5'><img src='$image_5' height=14 title='$tit5'></a>";
		echo"<a href='home.php?reset=6'><img src='$image_6' height=14 title='$tit6'></a>";
		echo "<a href='home.php?reset=7'><img src='$image_7' height=14 title='$tit7'></a>";
		echo "<a href='home.php?reset=8'><img src='$image_8' height=14 title='$tit8'></a>";
		echo"<a href='home.php?reset=9'><img src='$image_9' height=14 title='$tit9'></a>";
		echo "<a href='home.php?reset=10'><img src='$image_10' height=14 title='$tit10'></a>";

		echo " Started at ".substr(fix_timestamp($job_info['started']),-7)." - Time now ".date("g:ia");
	}
}
else
{
	echo "<center><h1>No Job Currently Running.</h1>";
}
$db->close();

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

?>