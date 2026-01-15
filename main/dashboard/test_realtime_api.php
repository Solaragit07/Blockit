<?php
// Simple test for the real-time devices API
session_start();

// Mock a session for testing (you should have a real login)
$_SESSION['user_id'] = 1; // Temporary for testing

// Include the API file
include 'get_real_time_devices.php';
?>
