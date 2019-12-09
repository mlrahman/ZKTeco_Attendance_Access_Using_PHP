<?php

include "zklibrary.php";
echo 'Library Loaded</br>';
$zk = new ZKLibrary('192.168.0.103', 4370, 'TCP');
echo 'Requesting for connection</br>';
$zk->connect();
echo 'Connected</br>';
$zk->disableDevice();
echo 'disabling device</br>';

//$zk->deleteUser(1);

//Set new user or update
//super admin 14
//normal user 0
//$zk->setUser(2,2,'Ahmed Ali','0000',0);
//echo 'Setting user with new data';

$zk->setUser(2,2,'Karim Ali','0000',0);
echo 'Setting user with new data';

$zk->enableDevice();
$zk->disconnect();
?>