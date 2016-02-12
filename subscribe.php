<?php
$pageUR1  = preg_replace("/\/(.+)/", "", $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"]);
$curdomain  = str_replace("www.", "", $pageUR1);

if(strpos($_SERVER['HTTP_REFERER'], $curdomain)) {
	error_reporting (E_ALL ^ E_NOTICE);
	$post = (!empty($_POST))? true : false;

	if($post) {
		$news_user = get_option('newsletter_user');
		$news_pass = get_option('newsletter_pass');
		$apikey = get_option('newsletter_apikey');

		require_once("GAPI.class.php");
		$news_con = new GAPI($news_user, $news_pass);

		$news_fname = NULL;
		if($_POST['id_first_name']) {
			$news_fname = stripslashes($_POST['id_first_name']);
		}

		$news_lname = NULL;
		if($_POST['id_last_name']) {
			$news_lname = stripslashes($_POST['id_last_name']);
		}

		$news_confirm = False;
		if($_POST['confirm'] == "on") {
			$news_confirm = True;
		}

		$response = array(
			'status' => 'ok',
			'message' => null
		);

		if ($news_con->subscription_add($_POST['id_email'], $_POST['newsletter'], utf8_encode($news_fname), utf8_encode($news_lname), $news_confirm, $apikey)) {
			if($news_confirm == True && get_option('newsletter_msg_confirm')) {
				$response['message'] = get_option('newsletter_msg_confirm');
			} else {
				$response['message'] = get_option('newsletter_msg_success');
			}
		} else {
			$response['status'] = 'error';

			if($news_con->errorCode == "505" && get_option('newsletter_msg_505')) {
				$response['message'] = get_option('newsletter_msg_505');
			} elseif($news_con->errorCode == "512" && get_option('newsletter_msg_512')) {
				$response['message'] = get_option('newsletter_msg_512');
			} else {
				$response['message'] = $news_con->show_errors();
			}
		}

		header("HTTP/1.0 " . $news_con->statusCode);
		header('Content-Type: application/json');
        echo json_encode($response, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
		wp_die();
	}
}
?>
