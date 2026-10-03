<?php
session_name('ffadmin'); session_start(); session_destroy();
header('Location: login.php');
