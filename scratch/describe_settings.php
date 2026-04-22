<?php
$dbName = 'clic404';
$conn = @new mysqli('localhost', 'root', '', $dbName);
if ($conn->connect_error) die("DB failed");

$res = $conn->query("DESCRIBE settings");
if ($res) {
    while($row = $res->fetch_assoc()) print_r($row);
}
$conn->close();
