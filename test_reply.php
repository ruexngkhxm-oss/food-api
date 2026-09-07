<?php
require_once 'db.php';

// Test 1: Check notifications table count
$res = mysqli_query($conn, "SELECT COUNT(*) as count FROM notifications");
$row = mysqli_fetch_assoc($res);
echo "Current notifications count: " . $row['count'] . "\n";

// Test 2: Check comments table
$res2 = mysqli_query($conn, "SELECT COUNT(*) as count FROM recipe_reviews");
$row2 = mysqli_fetch_assoc($res2);
echo "Current recipe_reviews count: " . $row2['count'] . "\n";

mysqli_close($conn);
?>
