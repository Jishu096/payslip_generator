<?php
require_once __DIR__ . '/../../app/Helpers/SessionManager.php';

// Initialize session properly
SessionManager::start();

// Destroy securely
SessionManager::destroy();

// Redirect to login page
header("Location: login.php");
exit;
?>

