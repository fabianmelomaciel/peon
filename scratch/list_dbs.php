<?php
$conn = @new mysqli('localhost', 'root', '');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$res = $conn->query("SHOW DATABASES");
if ($res) {
    while($row = $res->fetch_row()) {
        echo $row[0] . PHP_EOL;
    }
}
$conn->close();
