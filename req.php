<?php

session_start();

require('Grabber.php');

$grabber = new grabber\Grabber();

// Some logic if we'll ever need it

$grabber->process();