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
echo 'connected</br>';


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
echo '</br>All Set</br></br>';

if(isset($_REQUEST['delete_user']))
{
	$user_id=$_REQUEST['user_id'];
	$sql = "SELECT * FROM user where user_id='$user_id'";
	$result = mysqli_query($conn, $sql);
	if (mysqli_num_rows($result) == 1) {
		$zk->deleteUser($user_id);   //Give the user_id no
		$sql2="delete from user where user_id='$user_id' ";
		mysqli_query($conn,$sql2);
		$sql3="delete from user_template where uid='$user_id' ";
		mysqli_query($conn,$sql3);
	}
	echo 'Deleted Successfully.';
}

?>
<html>
</br></br>
<form action="deleting_user.php" method="post">
	Delete a user by selecting user ID: </br>
	<?php
		$users = $zk->getUser();
		foreach($users as $key=>$user)
		{
			$user_id=$user[0];
			$name=$user[1];
	
	?>
	<input type="radio" name="user_id" value="<?php echo $user_id; ?>"> <?php echo $user_id; ?> <?php echo '('.$name.')'; ?></br>
	<?php 
		} 
	?>
	</br>
	<input type="submit" name="delete_user" value="Delete">
</form>


<?php



$zk->enableDevice();
$zk->disconnect();
?>