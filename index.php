<?php
$ver = empty($_GET['v']) ? 'dev' : $_GET['v'];

switch ($ver)
{

	case 'dev':
	default:
		include "reveal.php";
		break;
}
