<?php
// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('THIS_SCRIPT', 'boardstats');

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');

$navbits = array('boardstats.php' . $vbulletin->session->vars['sessionurl_q'] => 'BoardSpy Stats');
$navbits[""] = "BoardSpy Stats";
$navbits = construct_navbits($navbits);
eval('$navbar = "' . fetch_template('navbar') . '";');

eval('print_output("' . fetch_template('boardstats_main') . '");');		

?>