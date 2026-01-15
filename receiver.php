<?php
$routerID = $_GET['routerID'] ?? 'unknown';
$cpu = $_GET['cpu'] ?? '';
$blocked = $_POST['blocked'] ?? '';

file_put_contents("logs.txt", date("Y-m-d H:i:s") . " [$routerID] CPU:$cpu Blocked:$blocked\n", FILE_APPEND);
echo "OK";
?>
