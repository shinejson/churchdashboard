<?php
/**
 * KingsChat Authorization Redirect
 * Redirects user to KingsChat OAuth authorization page
 */

require_once 'config.php';

// Get authorization URL
$authUrl = getKingsChatAuthUrl();

// Redirect to KingsChat
header('Location: ' . $authUrl);
exit();
