<?php
session_start();
$_SESSION['user_id'] = 2;
$_SESSION['user_role'] = 'employer';
require 'employer/dashboard.php';
