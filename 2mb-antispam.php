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
        add_action('admin_menu', 'twomb_antispam_init_admin_menu');
    }
    if(!session_id()) {
        session_start();
    }
    add_action('wp_logout', 'twomb_antispam_end_session');
    add_action('wp_login', 'twomb_antispam_end_session');
    if(!isset($_SESSION['2mb_antispam_id'])) {
        $_SESSION['2mb_antispam_id'] = unique_id();
    }
    add_action('admin_init', 'twomb_antispam_init_settings');
   add_action('admin_menu', 'twomb_antispam_init_admin_menu');
    register_activation_hook(__FILE__, 'twomb_antispam_activate');
}

function twomb_antispam_end_session() {
    session_destroy();
}

function twomb_antispam_add_comment_div() {
    if(get_option("2mb-antispam-do-id-comments") == 1) {
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
<?php
}
    if(get_option("2mb-antispam-do-checkbox-comments") == 1) {
?>
    <p>
    <div id="2mb_antispam_checkbox">
    <noscript>
    <input type="checkbox" name="checkbox_<?php echo(str_split($_SESSION['2mb_antispam_id'], 64)[0]); ?>" checked="checked"> Uncheck this box if you're a human. No spammers allowed!
    </noscript>
    </div>

<?php
}
?>
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
<?php
    if(get_option('2mb-antispam-do-id-comments') == 1) {
?>
        document.getElementById("2mb_antispam_id_div").innerHTML = "<input type=\"hidden\" name=\"<?php echo(str_split($_SESSION["2mb_antispam_id"], 64)[0]); ?>\" value=\"<?php echo(str_split($_SESSION["2mb_antispam_id"], 64)[1]); ?>\">";
<?php
    }
    if(get_option('2mb-antispam-do-checkbox-comments') == 1) {
?>
        document.getElementById("2mb_antispam_checkbox").innerHTML = "<input type=\"checkbox\" name=\"checkbox_<?php echo(str_split($_SESSION["2mb_antispam_id"], 64)[0]); ?>\"> Check this box if you're a human. No spammers allowed!";
<?php
    }
?>
    }
    window.onload = function() {
        do_twomb_antispam();
    }
    </script>
    <?php
}

function twomb_antispam_add_registration_div() {
    if(get_option('2mb-antispam-do-id-registration') == 1) {
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

<?php
    }
    if(get_option('2mb-antispam-do-checkbox-registration') == 1) {
?>
    <p>
    <div id="2mb_antispam_checkbox">
    <noscript>
    <input type="checkbox" name="checkbox_<?php echo(str_split($_SESSION['2mb_antispam_id'], 64)[0]); ?>" checked="checked"> Uncheck this box if you're a human. No spammers allowed!
    </noscript>
    </div>
    <?php
    }
    ?>
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
<?php
    if(get_option('2mb-antispam-do-id-registration') == 1) {
?>
        document.getElementById("2mb_antispam_id_div").innerHTML = "<input type=\"hidden\" name=\"<?php echo(str_split($_SESSION["2mb_antispam_id"], 64)[0]); ?>\" value=\"<?php echo(str_split($_SESSION["2mb_antispam_id"], 64)[1]); ?>\">";
    <?php
    }
    if(get_option('2mb-antispam-do-checkbox-registration') == 1) {
?>
        document.getElementById("2mb_antispam_checkbox").innerHTML = "<input type=\"checkbox\" name=\"checkbox_<?php echo(str_split($_SESSION["2mb_antispam_id"], 64)[0]); ?>\"> Check this box if you're a human. No spammers allowed!";
<?php
}
?>
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
    if(!is_user_logged_in()) {
        if(!isset($_POST['comment_nonce']) || !wp_verify_nonce($_POST['comment_nonce'], '2mb_antispam_comment_nonce'))
//            wp_die('The comment nonce was not found. This is probably a bug.');
            $count_of_bots++;
        if(get_option('2mb-antispam-do-checkbox-comments') == 1 && (isset($_POST['2mb_noscript_'.str_split($_SESSION['2mb_antispam_id'], 64)[0]]) && $_POST['2mb_noscript_'.str_split($_SESSION['2mb_antispam_id'], 64)[0]] == 'yes' && isset($_POST['checkbox_'.str_split($_SESSION['2mb_antispam_id'], 64)[0]])))
//            wp_die('You need to uncheck the checkbox.');
            $count_of_bots++;
        if(get_option('2mb-antispam-do-id-comments') == 1 && (isset($_POST['2mb_noscript_'.str_split($_SESSION['2mb_antispam_id'], 64)[0]]) && $_POST['2mb_noscript_'.str_split($_SESSION['2mb_antispam_id'], 64)[0]] == 'yes' && (!isset($_POST[str_split($_SESSION['2mb_antispam_id'], 64)[0]]) || $_POST[str_split($_SESSION['2mb_antispam_id'], 64)[0]] != str_split($_SESSION['2mb_antispam_id'], 64)[1])))
//            wp_die('You need to provide the validation key.');
            $count_of_bots++;
        if(get_option('2mb-antispam-do-checkbox-comments') == 1 && (!isset($_POST['2mb_noscript_'.str_split($_SESSION['2mb_antispam_id'], 64)[0]]) && !isset($_POST['checkbox_'.str_split($_SESSION['2mb_antispam_id'], 64)[0]])))
//            wp_die('You need to check the checkbox.');
            $count_of_bots++;
        if(get_option('2mb-antispam-do-id-comments') == 1 && (!isset($_POST['2mb_noscript_'.str_split($_SESSION['2mb_antispam_id'], 64)[0]]) && (!isset($_POST[str_split($_SESSION['2mb_antispam_id'], 64)[0]]) || $_POST[str_split($_SESSION['2mb_antispam_id'], 64)[0]] != str_split($_SESSION['2mb_antispam_id'], 64)[1])))
//            wp_die('No bots allowed!');
            $count_of_bots++;
        $reason = "";
        if(get_option('2mb-antispam-do-blogspamnet') == 1 && twomb_antispam_blogspam($comment_data['comment_author'], $comment_data['comment_author_email'], $comment_data['comment_author_url'], $comment_data['comment_content'], $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], $reason) == 1)
//            wp_die('Your comment was shown to be spam by the blogspam.net API. Please try again or contact the site owner for help. The reason was: '.$reason);
            $count_of_spam++;
        $comment_info = array(
            'comment_author'    => $comment_data['comment_author'],
            'comment_author_email'     => $comment_data['comment_author_email'],
            'comment_author_url'   => $comment_data['comment_author_url'],
            'comment_content'      => $comment_data['comment_content'],
            'permalink' => get_permalink($comment_data['comment_post_ID']),
            'blog' => get_site_url(),
                'user_ip' => $_SERVER['REMOTE_ADDR']
        );
        if(get_option('2mb-antispam-do-akismet') == 1 && get_option('2mb-antispam-akismet-key') != '' && fuspam($comment_info, 'verify-key', get_option('2mb-antispam-akismet-key')) == "valid") {
             $result = fuspam($comment_info, 'check-spam', get_option('2mb-antispam-akismet-key'));
            if($result == "true")
                $count_of_spam++;
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
        if(get_option('2mb-antispam-do-checkbox-registration') == 1 && (isset($_POST['2mb_noscript_'.str_split($_SESSION['2mb_antispam_id'], 64)[0]]) && $_POST['2mb_noscript_'.str_split($_SESSION['2mb_antispam_id'], 64)[0]] == 'yes' && isset($_POST['checkbox_'.str_split($_SESSION['2mb_antispam_id'], 64)[0]])))
            $errors->add('nonjavascript checkbox error', 'The checkbox you need to uncheck was found checked. Please go back and try again.');
        if(get_option('2mb-antispam-do-id-registration') == 1 && (isset($_POST['2mb_noscript_'.str_split($_SESSION['2mb_antispam_id'], 64)[0]]) && $_POST['2mb_noscript_'.str_split($_SESSION['2mb_antispam_id'], 64)[0]] == 'yes' && (!isset($_POST[str_split($_SESSION['2mb_antispam_id'], 64)[0]]) || _POST[str_split($_SESSION['2mb_antispam_id'], 64)[0]] != str_split($_SESSION['2mb_antispam_id'], 64)[1])))
            $errors->add('key error', 'The key you needed to provide was either not provided or was invalid. Please go back and try again.');
        if(get_option('2mb-antispam-do-checkbox-registration') == 1 && (!isset($_POST['2mb_noscript_'.str_split($_SESSION['2mb_antispam_id'], 64)[0]]) && !isset($_POST['checkbox_'.str_split($_SESSION['2mb_antispam_id'], 64)[0]])))
            $errors->add('checkbox error', 'You need to check the checkbox to register. Please go back and try again.');
        if(get_option('2mb-antispam-do-id-registration') == 1 && (!isset($_POST['2mb_noscript_'.str_split($_SESSION['2mb_antispam_id'], 64)[0]]) && (!isset($_POST[str_split($_SESSION['2mb_antispam_id'], 64)[0]]) || $_POST[str_split($_SESSION['2mb_antispam_id'], 64)[0]] != str_split($_SESSION['2mb_antispam_id'], 64)[1])))
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

function twomb_antispam_init_admin_menu() {
    add_options_page('Antispam Options', '2MB Antispam Options', 'manage_options', 'twomb-antispam-settings', 'twomb_antispam_options');
}

function twomb_antispam_options() {
    if(!current_user_can( 'manage_options')) {
        wp_die( 'You do not have rights to access this page.');
    }
    ?>
    <div class="wrap">
    <h2>Wait just a second.</h2>
    <p>
    Do you like this plugin? Does it make your life just a little bit easier -- we hope! If it does, please consider donating to help our plugin effort along. Any amount helps. We'll love you forever ;-)
    <br>
    <form name="input" target="_blank" action="https://www.paypal.com/cgi-bin/webscr" method="post">
    <input type="hidden" name="add" value="1">
    <input type="hidden" name="cmd" value="_xclick">
    <input type="hidden" name="business" value="ai5hf@hotmail.com">
    <input type="hidden" name="item_name" value="Support 2MB Solutions">
    Amount: $<input type="text" maxlength="200" style="width:50px;" name="amount" value="5.00"> USD<br />
    <input type="hidden" name="currency_code" value="USD">
    <input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_buynow_SM.gif" border="0" alt="PayPal - The safer, easier way to pay online!">
    </form>
    <br>
    Also please consider visiting our website to stay up to date on 2MB Solutions news, plugins, offers, and more. <a href="http://2mb.solutions/">Click here to visit</a>.
    </p>
<form method="post" action="options.php">
    <?php
    settings_fields('twomb-antispam-settings');
    do_settings_sections('twomb-antispam-settings');
    submit_button();
    ?>
    </form>
<?php
}

function twomb_antispam_init_settings() {
    add_settings_section('twomb-antispam-settings', 'antispam Options', 'twomb_antispam_print_section', 'twomb-antispam-settings');
    register_setting('twomb-antispam-settings', '2mb-antispam-do-checkbox-comments', 'twomb_antispam_do_checkbox_comments_sanitize');
    register_setting('twomb-antispam-settings', '2mb-antispam-do-id-comments', 'twomb_antispam_do_id_comments_sanitize');
//    register_setting('twomb-antispam-settings', '2mb-antispam-do-honeypot-comments', 'twomb_antispam_do_honeypot_comments_sanitize');//not yet implemented.
    register_setting('twomb-antispam-settings', '2mb-antispam-do-checkbox-registration', 'twomb_antispam_do_checkbox_registration_sanitize');
    register_setting('twomb-antispam-settings', '2mb-antispam-do-id-registration', 'twomb_antispam_do_id_registration_sanitize');
//    register_setting('twomb-antispam-settings', '2mb-antispam-do-honeypot-registration', 'twomb_antispam_do_honeypot_registration_sanitize');//Not yet implemented.
    register_setting('twomb-antispam-settings', '2mb-antispam-do-blogspamnet', 'twomb_antispam_do_blogspamnet_sanitize');
    register_setting('twomb-antispam-settings', '2mb-antispam-do-akismet', 'twomb_antispam_do_akismet_sanitize');
    register_setting('twomb-antispam-settings', '2mb-antispam-akismet-key', 'twomb_antispam_akismet_key_sanitize');
    register_setting('twomb-antispam-settings', '2mb-antispam-do-botscout-comments', 'twomb_antispam_do_botscout_comments_sanitize');
    register_setting('twomb-antispam-settings', '2mb-antispam-do-botscout-registration', 'twomb_antispam_do_botscout_registration_sanitize');
    register_setting('twomb-antispam-settings', '2mb-antispam-botscout-key', 'twomb_antispam_botscout_key_sanitize');
    add_settings_field('2mb-antispam-do-checkbox-comments', 'Should there be a bot-stopping checkbox on comment forms?', 'twomb_antispam_do_checkbox_comments_print', 'twomb-antispam-settings', 'twomb-antispam-settings');
    add_settings_field('2mb-antispam-id-comments', 'Should there be a bot-stopping random id on comment forms?', 'twomb_antispam_do_id_comments_print', 'twomb-antispam-settings', 'twomb-antispam-settings');
//    add_settings_field('2mb-antispam-do-honeypot-comments', 'Should there be a bot-stopping honeypot on comment forms?', 'twomb_antispam_do_honeypot_comments_print', 'twomb-antispam-settings', 'twomb-antispam-settings');//Not yet implemented.
    add_settings_field('2mb-antispam-do-checkbox-registration', 'Should there be a bot-stopping checkbox on the registration page?', 'twomb_antispam_do_checkbox_registration_print', 'twomb-antispam-settings', 'twomb-antispam-settings');
    add_settings_field('2mb-antispam-id-registration', 'Should there be a bot-stopping random id on the registration page?', 'twomb_antispam_do_id_registration_print', 'twomb-antispam-settings', 'twomb-antispam-settings');
//    add_settings_field('2mb-antispam-do-honeypot-registration', 'Should there be a bot-stopping honeypot on the registration page?', 'twomb_antispam_do_honeypot_registration_print', 'twomb-antispam-settings', 'twomb-antispam-settings');//Not yet implemented.
    add_settings_field('2mb-antispam-do-blogspamnet', 'Should comments be checked against the blogspam.net API?', 'twomb_antispam_do_blogspamnet_print', 'twomb-antispam-settings', 'twomb-antispam-settings');
    add_settings_field('2mb-antispam-do-akismet', 'Should comments be checked against the akismet antispam API?', 'twomb_antispam_do_akismet_print', 'twomb-antispam-settings', 'twomb-antispam-settings');
    add_settings_field('2mb-antispam-akismet-key', 'Enter your key for the akismet API here:', 'twomb_antispam_akismet_key_print', 'twomb-antispam-settings', 'twomb-antispam-settings');
    add_settings_field('2mb-antispam-do-botscout-comments', 'Should comments be checked against the botscout API?', 'twomb_antispam_do_botscout_comments_print', 'twomb-antispam-settings', 'twomb-antispam-settings');
    add_settings_field('2mb-antispam-do-botscout-registration', 'Should registrations be checked against the botscout API?', 'twomb_antispam_do_botscout_registration_print', 'twomb-antispam-settings', 'twomb-antispam-settings');
    add_settings_field('2mb-antispam-botscout-key', 'Enter your botscout API key here.', 'twomb_antispam_botscout_key_print', 'twomb-antispam-settings', 'twomb-antispam-settings');
}

function twomb_antispam_do_checkbox_comments_sanitize($data) {
    if($data == "true")
        return 1;
    else
        return 0;
}

function twomb_antispam_do_id_comments_sanitize($data) {
    if($data == "true")
        return 1;
    else
        return 0;
}

//Not yet implemented, left for future expansion.
function twomb_antispam_do_honeypot_comments_sanitize($data) {
    if($data == "true")
        return 1;
    else
        return 0;
}

function twomb_antispam_do_checkbox_registration_sanitize($data) {
    if($data == "true")
        return 1;
    else
        return 0;
}

function twomb_antispam_do_id_registration_sanitize($data) {
    if($data == "true")
        return 1;
    else
        return 0;
}

//Not yet implemented, left for future expansion.
function twomb_antispam_do_honeypot_registration_sanitize($data) {
    if($data == "true")
        return 1;
    else
        return 0;
}

function twomb_antispam_do_blogspamnet_sanitize($data) {
    if($data == "true")
        return 1;
    else
        return 0;
}

function twomb_antispam_do_akismet_sanitize($data) {
    if($data == "true")
        return 1;
    else
        return 0;
}

function twomb_antispam_akismet_key_sanitize($data) {
    return sanitize_text_field($data);
}

function twomb_antispam_do_botscout_comments_sanitize($data) {
    if($data == "true")
        return 1;
    else
        return 0;
}

function twomb_antispam_do_botscout_registration_sanitize($data) {
    if($data == "true")
        return 1;
    else
        return 0;
}

function twomb_antispam_botscout_key_sanitize($data) {
    return sanitize_text_field($data);
}

function twomb_antispam_do_checkbox_comments_print() {
    ?>
    <input name="2mb-antispam-do-checkbox-comments" id="2mb-antispam-do-checkbox-comments" type="checkbox" value="true"<?php echo ((get_option('2mb-antispam-do-checkbox-comments') == 1)?' checked="checked">':'>');
}

function twomb_antispam_do_id_comments_print() {
    ?>
    <input name="2mb-antispam-do-id-comments" id="2mb-antispam-do-id-comments" type="checkbox" value="true"<?php echo ((get_option('2mb-antispam-do-id-comments') == 1)?' checked="checked">':'>');
}

//Not yet implemented, Left for future expansion.
function twomb_antispam_do_honeypot_comments_print() {
    ?>
    <input name="2mb-antispam-do-honeypot-comments" id="2mb-antispam-do-honeypot-comments" type="checkbox" value="true"<?php echo ((get_option('2mb-antispam-do-honeypot-comments') == 1)?' checked="checked">':'>');
}

function twomb_antispam_do_checkbox_registration_print() {
    ?>
    <input name="2mb-antispam-do-checkbox-registration" id="2mb-antispam-do-checkbox-registration" type="checkbox" value="true"<?php echo ((get_option('2mb-antispam-do-checkbox-registration') == 1)?' checked="checked">':'>');
}

function twomb_antispam_do_id_registration_print() {
    ?>
    <input name="2mb-antispam-do-id-registration" id="2mb-antispam-do-id-registration" type="checkbox" value="true"<?php echo ((get_option('2mb-antispam-do-id-registration') == 1)?' checked="checked">':'>');
}

//Not yet implemented, left for future expansion.
function twomb_antispam_do_honeypot_registration_print() {
    ?>
    <input name="2mb-antispam-do-honeypot-registration" id="2mb-antispam-do-honeypot-registration" type="checkbox" value="true"<?php echo ((get_option('2mb-antispam-do-honeypot-registration') == 1)?' checked="checked">':'>');
}

function twomb_antispam_do_blogspamnet_print() {
    ?>
    <input name="2mb-antispam-do-blogspamnet" id="2mb-antispam-do-blogspamnet" type="checkbox" value="true"<?php echo ((get_option('2mb-antispam-do-blogspamnet') == 1)?' checked="checked">':'>');
}

function twomb_antispam_do_akismet_print() {
    ?>
    <input name="2mb-antispam-do-akismet" id="2mb-antispam-do-akismet" type="checkbox" value="true"<?php echo ((get_option('2mb-antispam-do-akismet') == 1)?' checked="checked">':'>');
}

function twomb_antispam_akismet_key_print() {
    ?>
    <input type="text" name="2mb-antispam-akismet-key" id="2mb-antispam-akismet-key" value=<?php echo (get_option('2mb-antispam-akismet-key'));?>>
    <?php
}

function twomb_antispam_do_botscout_comments_print() {
    ?>
    <input name="2mb-antispam-do-botscout-comments" id="2mb-antispam-do-botscout-comments" type="checkbox" value="true"<?php echo ((get_option('2mb-antispam-do-botscout-comments') == 1)?' checked="checked">':'>');
}

function twomb_antispam_do_botscout_registration_print() {
    ?>
    <input name="2mb-antispam-do-botscout-registration" id="2mb-antispam-do-botscout-registration" type="checkbox" value="true"<?php echo ((get_option('2mb-antispam-do-botscout-registration') == 1)?' checked="checked">':'>');
}

function twomb_antispam_botscout_key_print()
{
    ?>
    <input name="2mb-antispam-botscout-key" id="2mb-antispam-botscout-key" type="text" value="<?php echo get_option('2mb-antispam-botscout-key');?>">
<?php
}

function twomb_antispam_print_section() {
?>
Please enter your settings below, and click save to save your changes. Note that it may take some experimentation to find which settings work best on your site.
<?php
}

function twomb_antispam_activate() {
    add_option('2mb-antispam-do-checkbox-comments', 0);
    add_option('2mb-antispam-do-id-comments', 0);
//    add_option('2mb-antispam-do-honeypot-comments', 0);//Not yet implemented.
    add_option('2mb-antispam-do-checkbox-registration', 0);
    add_option('2mb-antispam-do-id-registration', 0);
//    add_option('2mb-antispam-do-honeypot-registration', 0);//Not yet implemented.
    add_option('2mb-antispam-do-blogspamnet', 0);
    add_option('2mb-antispam-do-akismet', 0);
    add_option('2mb-antispam-akismet-key', '');
    add_option('2mb-antispam-do-botscout-comments', 0);
    add_option('2mb-antispam-do-botscout-registration', 0);
    add_option('2mb-antispam-botscout-key', '');
}
?>