<?php
date_default_timezone_set("America/Chicago");
session_start();
if($_SESSION['admin'] != "yes") die("access denied");
$mess="";
include("dbopen_new.php");

if(isset($_POST['n1'])) {
	file_put_contents("speed.txt",trim($_POST['n1'])."-".trim($_POST['n2']));
	$mess="<font color='green' size=4>Speed Settings Saved</font>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title>Transunion TLO Scrape Admin</title>
<meta charset="utf-8">
<link rel="stylesheet" href="admin.css">
</head>
<body>
<div id="container">
<?php include("menu.php"); ?>
<center>
<br>
<h3>TU TLO Scrape Speed</h3>
<?php
$speed=explode("-",file_get_contents("speed.txt"));
?>
<form method="post">
<table width="600">
<tr><td colspan=2>
There are 10 sessions running during the TLO scrape.  You are setting the speed for each session.  Please enter a range for the number of files per hour each session will pull.  The original settings were "between 36 and 54" files per hour.
<br><br>
The login speed is randomized to take take between 13 and 23 seconds to appear human.
<br><br>
Scrape will only run between 7am and 6pm Monday through Friday.
</tr>
<tr><td colspan=2>&nbsp;</td></tr>
<tr>
<td>Pull between <input type="text" name="n1" size="3" value="<?php echo $speed[0];?>"> and <input type="text" name="n2" size="3" value="<?php echo $speed[1];?>"> per hour for each session</td>
</tr>
<tr><td colspan="2">&nbsp;</td></tr>
<tr><td colspan="2" align="center"><input type="submit" value=" Save " style="padding: 10px;"></td></tr>
<?php
if($mess) {
	echo "<tr><td colspan=2 align='center'><br>$mess</td></tr>";
}
?>
</form>
</table>

</div>

</body>
</html>
