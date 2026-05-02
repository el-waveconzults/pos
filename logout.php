<?php
require_once 'config/config.php';

// Clear cookies and destroy session
logoutUser();
redirect('login.php');
