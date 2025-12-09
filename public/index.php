<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Call the router
require_once __DIR__ . '/../app/Routes/web.php';
