<?php
/*
Author: 2MB Solutions
Author URI: http://2mb.solutions/
Description: The premier anti-spam plugin from 2mb. Block comment bots, registration bots, and spam of all sorts.
Plugin Name: 2MB All-in-One Anti-spam
Plugin URI: TODO
Version: 0.0.1 alpha
License: Gpl v2 or later
*/
require_once('random.php');
require_once('akismet.fuspam.php');

add_action('init', 'twomb_antispam_init', 1);
function twomb_antispam_init() {
    if(!is_user_logged_in()) {
        add_action('comment_form_after_fields', 'twomb_antispam_add_comment_div', 9999);
        add_action('register_form', 'twomb_antispam_add_registration_div', 9999);
        add_filter('pre_comment_approved', 'twomb_antispam_process_comment', 99, 2);
        add_filter('registration_errors', 'twomb_antispam_register_validate', 10, 3);
    }
    if(!session_id()) {
        session_start();
    }
    add_action('wp_logout', 'twomb_antispam_end_session');
    add_action('wp_login', 'twomb_antispam_end_session');
    if(!isset($_SESSION['2mb_antispam_id'])) {
        $_SESSION['2mb_antispam_id'] = unique_id();
    }
}

function twomb_antispam_end_session() {
    session_destroy();
}

function twomb_antispam_add_comment_div() {
    ?>
    <p>
    <div id="2mb_antispam_id_div">
    <noscript>
    It seems you have javascript disabled in your browser. In order to post a comment, please add the following key in the field below: <strong><?php echo(str_split($_SESSION['2mb_antispam_id'], 64)[1]); ?></strong>
    <br>
<input type="text" name="<?php echo(str_split($_SESSION['2mb_antispam_id'], 64)[0]);?>">
    </noscript>
    </div>
    </p>
    <p>
    <div id="2mb_antispam_checkbox">
    <noscript>
    <input type="checkbox" name="checkbox_<?php echo(str_split($_SESSION['2mb_antispam_id'], 64)[0]); ?>" checked="checked"> Uncheck this box if you're a human. No spammers allowed!
    </noscript>
    </div>
    <div id="2mb_antispam_nonce">
    <?php
    wp_nonce_field('2mb_antispam_comment_nonce', 'comment_nonce');
    ?>
</div>
<noscript>
<input type="hidden" name="2mb_noscript_<?php echo(str_split($_SESSION['2mb_antispam_id'], 64)[0]);?>" value="yes">
</noscript>
    </p>
    <script type="text/javascript">
    function do_twomb_antispam() {
        document.getElementById("2mb_antispam_id_div").innerHTML = "<input type=\"hidden\" name=\"<?php echo(str_split($_SESSION["2mb_antispam_id"], 64)[0]); ?>\" value=\"<?php echo(str_split($_SESSION["2mb_antispam_id"], 64)[1]); ?>\">";
        document.getElementById("2mb_antispam_checkbox").innerHTML = "<input type=\"checkbox\" name=\"checkbox_<?php echo(str_split($_SESSION["2mb_antispam_id"], 64)[0]); ?>\"> Check this box if you're a human. No spammers allowed!";
    }
    window.onload = function() {
        do_twomb_antispam();
    }
    </script>
    <?php
}

function twomb_antispam_add_registration_div() {
    ?>
    <p>
    <div id="2mb_antispam_id_div">
    <noscript>
    It seems you have javascript disabled in your browser. In order to register, please add the following key in the field below: <strong><?php echo(str_split($_SESSION['2mb_antispam_id'], 64)[1]); ?></strong>
    <br>
<input type="text" name="<?php echo(str_split($_SESSION['2mb_antispam_id'], 64)[0]);?>">
    </noscript>
    </div>
    </p>
    <p>
    <div id="2mb_antispam_checkbox">
    <noscript>
    <input type="checkbox" name="checkbox_<?php echo(str_split($_SESSION['2mb_antispam_id'], 64)[0]); ?>" checked="checked"> Uncheck this box if you're a human. No spammers allowed!
    </noscript>
    </div>
    <div id="2mb_antispam_nonce">
    <?php
    wp_nonce_field('2mb_antispam_register_nonce', 'register_nonce');
    ?>
</div>
<noscript>
<input type="hidden" name="2mb_noscript_<?php echo(str_split($_SESSION['2mb_antispam_id'], 64)[0]);?>" value="yes">
</noscript>
    </p>
    <script type="text/javascript">
    function do_twomb_antispam() {
        document.getElementById("2mb_antispam_id_div").innerHTML = "<input type=\"hidden\" name=\"<?php echo(str_split($_SESSION["2mb_antispam_id"], 64)[0]); ?>\" value=\"<?php echo(str_split($_SESSION["2mb_antispam_id"], 64)[1]); ?>\">";
        document.getElementById("2mb_antispam_checkbox").innerHTML = "<input type=\"checkbox\" name=\"checkbox_<?php echo(str_split($_SESSION["2mb_antispam_id"], 64)[0]); ?>\"> Check this box if you're a human. No spammers allowed!";
    }
    window.onload = function() {
        do_twomb_antispam();
    }
    </script>
    <?php
}

function twomb_antispam_process_comment($approved, $comment_data) {
    $count_of_bots = 0;
    $count_of_spam = 0;
    $count_of_good = 0;
    if(!is_user_logged_in()) {
        if(!isset($_POST['comment_nonce']) || !wp_verify_nonce($_POST['comment_nonce'], '2mb_antispam_comment_nonce'))
//            wp_die('The comment nonce was not found. This is probably a bug.');
            $count_of_bots++;
        else if(isset($_POST['2mb_noscript_'.str_split($_SESSION['2mb_antispam_id'], 64)[0]]) && $_POST['2mb_noscript_'.str_split($_SESSION['2mb_antispam_id'], 64)[0]] == 'yes' && isset($_POST['checkbox_'.str_split($_SESSION['2mb_antispam_id'], 64)[0]]))
//            wp_die('You need to uncheck the checkbox.');
            $count_of_bots++;
        else if(isset($_POST['2mb_noscript_'.str_split($_SESSION['2mb_antispam_id'], 64)[0]]) && $_POST['2mb_noscript_'.str_split($_SESSION['2mb_antispam_id'], 64)[0]] == 'yes' && (!isset($_POST[str_split($_SESSION['2mb_antispam_id'], 64)[0]]) || $_POST[str_split($_SESSION['2mb_antispam_id'], 64)[0]] != str_split($_SESSION['2mb_antispam_id'], 64)[1]))
//            wp_die('You need to provide the validation key.');
            $count_of_bots++;
        else if(!isset($_POST['2mb_noscript_'.str_split($_SESSION['2mb_antispam_id'], 64)[0]]) && !isset($_POST['checkbox_'.str_split($_SESSION['2mb_antispam_id'], 64)[0]]))
//            wp_die('You need to check the checkbox.');
            $count_of_bots++;
        else if(!isset($_POST['2mb_noscript_'.str_split($_SESSION['2mb_antispam_id'], 64)[0]]) && (!isset($_POST[str_split($_SESSION['2mb_antispam_id'], 64)[0]]) || $_POST[str_split($_SESSION['2mb_antispam_id'], 64)[0]] != str_split($_SESSION['2mb_antispam_id'], 64)[1]))
//            wp_die('No bots allowed!');
            $count_of_bots++;
        $reason = "";
/*        if(twomb_antispam_blogspam($comment_data['comment_author'], $comment_data['comment_author_email'], $comment_data['comment_author_url'], $comment_data['comment_content'], $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], $reason) == 1)
//            wp_die('Your comment was shown to be spam by the blogspam.net API. Please try again or contact the site owner for help. The reason was: '.$reason);
            $count_of_spam++;
        else
            $count_of_good++;*/
        $comment_info = array(
            'comment_author'    => $comment_data['comment_author'],
            'comment_author_email'     => $comment_data['comment_author_email'],
            'comment_author_url'   => $comment_data['comment_author_url'],
            'comment_content'      => $comment_data['comment_content'],
            'permalink' => get_permalink($comment_data['comment_post_ID']),
            'blog' => get_site_url(),
                'user_ip' => $_SERVER['REMOTE_ADDR']
        );
//        $akismet = new Akismet(get_site_url(), '78475501f876', $comment);
        if(fuspam($comment_info, 'verify-key', '78475501f876') == "valid") {
             $result = fuspam($comment_info, 'check-spam', '78475501f876');
            if($result == "true")
                $count_of_spam++;
else if($result == "false")
                $count_of_good++;
        }
        if($count_of_bots >= 1)
            return 'spam';
        else if($count_of_spam >= 1)
            return 0;
        else
            return $approved;
        }
}

function twomb_antispam_register_validate($errors, $user, $email) {
    if(!is_user_logged_in()) {
        if(!isset($_POST['register_nonce']) || !wp_verify_nonce($_POST['register_nonce'], '2mb_antispam_register_nonce'))
            $errors->add('nonce_error', 'No nonce was provided. This is probably a bug.');
        if(isset($_POST['2mb_noscript_'.str_split($_SESSION['2mb_antispam_id'], 64)[0]]) && $_POST['2mb_noscript_'.str_split($_SESSION['2mb_antispam_id'], 64)[0]] == 'yes' && isset($_POST['checkbox_'.str_split($_SESSION['2mb_antispam_id'], 64)[0]]))
            $errors->add('nonjavascript checkbox error', 'The checkbox you need to uncheck was found checked. Please go back and try again.');
        if(isset($_POST['2mb_noscript_'.str_split($_SESSION['2mb_antispam_id'], 64)[0]]) && $_POST['2mb_noscript_'.str_split($_SESSION['2mb_antispam_id'], 64)[0]] == 'yes' && (!isset($_POST[str_split($_SESSION['2mb_antispam_id'], 64)[0]]) || $_POST[str_split($_SESSION['2mb_antispam_id'], 64)[0]] != str_split($_SESSION['2mb_antispam_id'], 64)[1]))
            $errors->add('key error', 'The key you needed to provide was either not provided or was invalid. Please go back and try again.');
        if(!isset($_POST['2mb_noscript_'.str_split($_SESSION['2mb_antispam_id'], 64)[0]]) && !isset($_POST['checkbox_'.str_split($_SESSION['2mb_antispam_id'], 64)[0]]))
            $errors->add('checkbox error', 'You need to check the checkbox to register. Please go back and try again.');
        if(!isset($_POST['2mb_noscript_'.str_split($_SESSION['2mb_antispam_id'], 64)[0]]) && (!isset($_POST[str_split($_SESSION['2mb_antispam_id'], 64)[0]]) || $_POST[str_split($_SESSION['2mb_antispam_id'], 64)[0]] != str_split($_SESSION['2mb_antispam_id'], 64)[1]))
            $errors->add('key error', 'The key you needed to provide was either not found or was invalid. Please go back and try again.');
        return $errors;
    }
}

function twomb_antispam_blogspam( $author, $email,
	 $url, $comment,
	 $user_ip, $user_agent, &$reason)
{
    $server_name = "http://test.blogspam.net:9999/";
    $server_options = ""; 
  $updated = iconv('UTF-8', 'UTF-8//IGNORE', $comment);
  if ( $updated )
  {
      $comment = $updated;
  }
  $struct = array(
                   'ip'      => $user_ip,
                   'name'    => $author,
                   'mail'    => $email,
                   'comment' => $comment,
                   'site'    => get_bloginfo('url'),
		   'options' => $server_options,
                   'version' => "2mb all-in-one antispam 0.0.1alpha on wordpress " . get_bloginfo('version')
                   );
  $result = wp_remote_post( $server_name, array( 'body' => json_encode(  $struct ) ) );
  if ( ! is_wp_error( $result ) )
  {
      $obj = json_decode( $result['body'], true );
      if ( $obj['result'] == "SPAM" )
      {
/*
          add_filter('pre_comment_approved',
                     create_function('$a', 'return \'spam\';'), 99);
*/
          $reason = $obj['reason'];
          return 1;
       }
   }
  return 0;
}
?>