<?php
/**
 * Root Index - Redirect to Public Directory
 * 
 * This file redirects all requests to the public directory
 * where the actual application entry point is located.
 * 
 * @version 1.0
 * @created January 10, 2026
 */

// Redirect to public directory
header('Location: public/index.php');
exit;
