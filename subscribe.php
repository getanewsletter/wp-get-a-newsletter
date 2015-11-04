<?php
$pageUR1  = preg_replace("/(.+)", "", $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"]);
$curdomain  = str_replace("www.", "", $pageUR1); 

if(strpos($_SERVER['HTTP_REFERER'],$curdomain))
{
	error_reporting (E_ALL ^ E_NOTICE);
	$post = (!empty($_POST))? true : false;

	if($post)
	{
		if (!function_exists('add_action')) 
		{
			require_once("../../../wp-config.php");
		}

		$news_user = get_option('newsletter_user');
		$news_pass = get_option('newsletter_pass');
		$apikey = get_option('newsletter_apikey');
		
		require_once("GAPI.class.php");
		$news_con = new GAPI($news_user, $news_pass);
		
		if($_POST['id_first_name']) $news_fname = stripslashes($_POST['id_first_name']);
		else $news_fname = NULL;
		if($_POST['id_last_name']) $news_lname = stripslashes($_POST['id_last_name']);
		else $news_lname = NULL;
		if($_POST['confirm'] == "on") $news_confirm = True;
		else $news_confirm = False;

		if ($news_con->subscription_add($_POST['id_email'], $_POST['newsletter'], utf8_encode($news_fname), utf8_encode($news_lname), $news_confirm, $apikey))
		{
			if($news_confirm == True && get_option('newsletter_msg_confirm')) echo '<span class="news-success">'.get_option('newsletter_msg_confirm').'</span>';
			else echo '<span class="news-success">'.get_option('newsletter_msg_success').'</span>';
		}
		else
		{
			if($news_con->errorCode == "505" && get_option('newsletter_msg_505')) echo '<span class="news-error">'.get_option('newsletter_msg_505').'</span>';
			elseif($news_con->errorCode == "512" && get_option('newsletter_msg_512')) echo '<span class="news-error">'.get_option('newsletter_msg_512').'</span>';
			else echo '<span class="news-error">'.$news_con->show_errors().'</span>';
		}
	}
}
?>
