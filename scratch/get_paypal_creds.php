<?php
$dbName = 'clic404';
$conn = @new mysqli('localhost', 'root', '', $dbName);
if ($conn->connect_error) die("DB failed");

$res = $conn->query("SELECT * FROM settings WHERE `key` LIKE '%paypal%'");
if ($res) {
    while($row = $res->fetch_assoc()) {
        echo $row['key'] . ": " . $row['value'] . "\n";
    }
}
$conn->close();
