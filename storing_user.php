<?php


//DB Connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "zk";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
if (!mysqli_set_charset($conn, "utf8")) {
    printf("Error loading character set utf8: %s\n", mysqli_error($conn));
    exit();
} else {
    printf("Current character set: %s\n", mysqli_character_set_name($conn));
}
echo 'connected';


//-----------------------------
//The socket functions described here are part of an extension to PHP which must be enabled at compile time by giving the --enable-sockets option to configure.
//Add extension=php_sockets.dll in php.ini and remove ; from extension=sockets statement
include "zklibrary.php";
//Library Loaded
$zk = new ZKLibrary('192.168.0.103', 4370, 'TCP');
//Requesting for connection
$zk->connect();
//Connected
$zk->disableDevice();
//disabling device
echo '</br>All Set</br>';

$users = $zk->getUser();
foreach($users as $key=>$user)
{
	$uid=$key;
	$user_id=$user[0];
	$name=$user[1];
	$role=$user[2];
	$password=$user[3];
	echo $user_id.' --- ';
	$sql = "SELECT * FROM user where uid='$uid' and user_id='$user_id' and name='$name' and role='$role' and password='$password' ";
	$result = mysqli_query($conn, $sql);
	if (mysqli_num_rows($result) == 0) {  //new id
			
			$sql2="insert into user values('$uid','$user_id','$name','$role','$password')";
			mysqli_query($conn,$sql2);
	}
}
echo '</br>User stored</br>';

//Getting all the users template(fingerprint) data
foreach($users as $key=>$user)
{
	$user_id=$user[0];
	$finger_id=6; //by default
	$sql = "SELECT * FROM user where user_id='$user_id' ";
	$result = mysqli_query($conn, $sql);
	if (mysqli_num_rows($result) == 1) {  //ID exists in user table
		
		$sql2 = "SELECT * FROM user_template where uid='$user_id' and fno='$finger_id' ";
		$result2 = mysqli_query($conn, $sql2);
		if (mysqli_num_rows($result2) == 0) {  //New Finger ID
			$f = $zk->getUserTemplate($user_id,$finger_id); //user id and finger print no(by defaut 6)
			$fp_length=$f[0];
			$valid=$f[3];
			$template=$f[4];
			
			echo $fp_length.' ----  '.$user_id.' ---- '.$finger_id.' --- '.$valid.'</br>';
			
			
			$stmt = $conn->prepare("INSERT INTO user_template (fp_length,uid,fno,valid,template) VALUES('$fp_length','$user_id','$finger_id','$valid',?)");
			$null = NULL;
			$stmt->bind_param("b", $null);
			$stmt->send_long_data(0, $template);
			$stmt->execute();
			
		}
	}
}
echo '</br>Fingerprint stored</br>';

$zk->enableDevice();
$zk->disconnect();
?>