<?php

	require_once 'jammu.conf';

	$jammu = new Jammu();

	// L'application renifle tes SMS et envoi les new sms au serveur

	if (isset($_POST['address'])) {
		$js = json_decode(file_get_contents('messages.json'), true);
		$js[] = $_POST;
		file_put_contents('messages.json' ,json_encode($js));
	}

	else if (isset($_GET['get2send'])) {
		// getting messages
		$js = file_get_contents('tosend.json');
		// emptying message to send list
		file_put_contents('tosend.json', '[]');
		// returning messages
		echo $js;
	}

	else {
		if (strtolower(php_sapi_name()) != 'cli') {
			echo json_encode($_POST);
		}
	}