<?php
$dbName = 'clic404';
$conn = @new mysqli('localhost', 'root', '', $dbName);
if ($conn->connect_error) die("DB failed");

$res = $conn->query("SHOW TABLES");
echo "TABLES IN $dbName:\n";
while($row = $res->fetch_row()) echo $row[0] . "\n";

$res = $conn->query("SELECT * FROM settings WHERE name LIKE '%paypal%' OR name LIKE '%client%'");
if ($res) {
    echo "\nPAYPAL SETTINGS:\n";
    while($row = $res->fetch_assoc()) print_r($row);
}
$conn->close();
