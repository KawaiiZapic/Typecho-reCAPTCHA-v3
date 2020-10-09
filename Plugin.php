<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * reCAPTCHA v3 Login/Comments Protect for Typecho
 *
 * @package GrCv3Protect
 * @author Zapic
 * @version 0.0.2
 * @link https://github.com/KawaiiZapic/Typecho-reCAPTCHA-v3
 */

class GrCv3Protect_Plugin implements Typecho_Plugin_Interface {
    public static $mirror = [
        "google" => "https://www.google.com",
        "recaptcha" => "https://recaptcha.net"
    ];

    public static function activate() {
        Typecho_Plugin::factory('admin/footer.php')->end = array(__CLASS__, 'LoginScriptLoader');
        Typecho_Plugin::factory('Widget_Archive')->header = array(__CLASS__, 'ArchiveScriptLoader');
        Typecho_Plugin::factory('Widget_User')->login = array(__CLASS__, 'loginAction');
        Typecho_Plugin::factory('Widget_Feedback')->comment = array(__CLASS__, 'commentAction');
    }

    public static function deactivate() {}

    public static function personalConfig(Typecho_Widget_Helper_Form $form) {}

    public static function config(Typecho_Widget_Helper_Form $form) {
        $key = new Typecho_Widget_Helper_Form_Element_Text('key', NULL, '', 'Site Key');
        $secret = new Typecho_Widget_Helper_Form_Element_Text('secret', NULL, '', 'Secret Key');
        $Score = new Typecho_Widget_Helper_Form_Element_Checkbox('Protect', ['login' => "登录",'comment' => "评论"], "login", '对以下行为启用reCAPTCHA验证');
        $Protect = new Typecho_Widget_Helper_Form_Element_Text('score', NULL, '0.5', 'reCAPTCHA 验证分数阈值');
        $jsMirror = new Typecho_Widget_Helper_Form_Element_Radio('jsMirror', ['1' => "recaptcha.net(国内可用)", '0' => "Google.com"], '1', 'reCAPTCHA 资源加载地址');
        $serverMirror = new Typecho_Widget_Helper_Form_Element_Radio('serverMirror', ['1' => "recaptcha.net(国内可用)", '0' => "Google.com"], '1', 'reCAPTCHA 验证地址');
        echo '<b>在<a href="https://www.google.com/recaptcha/admin">Google reCAPTCHA</a> 添加站点以获取 Site Key & Secret key</b><br><br>若启用评论验证,请在主题评论表单内添加相应代码:<pre>&lt;?php '.__CLASS__.'::OutputCode(); ?></pre>';
        $form->addInput($key);
        $form->addInput($secret);
        $form->addInput($Score);
        $form->addInput($Protect);
        $form->addInput($jsMirror);
        $form->addInput($serverMirror);
        
    }

    public static function LoginScriptLoader() {
        $user = Typecho_Widget::widget('Widget_User');
        if ($user->hasLogin() && $user->pass('administrator', true)) {
            return;
        }
        $config = Helper::options()->plugin('GrCv3Protect');
        if (!in_array("login",$config->Protect) || empty($config->key) || empty($config->secret)) {
            return;
        }
        $url = Typecho_Common::url("recaptcha/api.js",self::$mirror[$config->jsMirror == 1 ? "recaptcha" : "google"]);
        $key = $config->key;
        echo '<script src="' . $url . '"></script>
        <script>
            const GrCKey = "' . $key . '" ;
            function onSubmit() {
                document.querySelector("form").submit();
            }
            jQuery(function () {
                jQuery(".submit button.primary").addClass("g-recaptcha").attr("data-sitekey", GrCKey).attr("data-callback", "onSubmit");
            });
        </script>';
    }

    public static function ArchiveScriptLoader(){
        $user = Typecho_Widget::widget('Widget_User');
        if ($user->hasLogin() && $user->pass('administrator', true)) {
            return;
        }
        $config = Helper::options()->plugin('GrCv3Protect');
        if (!in_array("comment",$config->Protect) || empty($config->key) || empty($config->secret)) {
            return;
        }
        $url = Typecho_Common::url("recaptcha/api.js?render={$config->key}",self::$mirror[$config->jsMirror == 1 ? "recaptcha" : "google"]);
        echo "<script src='{$url}'></script>
        <style>.grecaptcha-badge{opacity: 0; pointer-events: none;}</style>";
    }

    public static function OutputCode() {
        $user = Typecho_Widget::widget('Widget_User');
        if ($user->hasLogin() && $user->pass('administrator', true)) {
            return;
        }
        $config = Helper::options()->plugin('GrCv3Protect');
        if (!in_array("comment",$config->Protect) || empty($config->key) || empty($config->secret)) {
            return;
        }
        $key = $config->key;
        echo '<input type="hidden" name="g-recaptcha-response"></input>
        <script>
            const GrKey = "'. $key . '";
            grecaptcha.ready(function() {
                const callback = function(){
                    grecaptcha.execute(GrKey, {action: "social"}).then(function(token) {
                        document.querySelectorAll("input[name=\"g-recaptcha-response\"]").forEach(function(v){
                            v.value = token;
                        });
                    });
                };
                setInterval(callback,90000);
                callback();
            });
        </script>
        <style>div.grecaptcha-badge{opacity: 1; pointer-events: all;}</style>';
    }

    public static function loginAction($name, $password, $temporarily = false, $expire = 0) {
        $user = Typecho_Widget::widget('Widget_User');
        $config = Helper::options()->plugin('GrCv3Protect');
        if (in_array("login",$config->Protect) && !empty($config->key) && !empty($config->secret)) {
            $res = $user->request->from('g-recaptcha-response');
            $url = self::$mirror[$config->serverMirror == 1 ? "recaptcha" : "google"];
            $score = floatval($config->score);
            if (empty($res) || empty($res['g-recaptcha-response']) || self::Verify($url, $config->secret, $res['g-recaptcha-response'], $score) !== true) {
                $user->widget('Widget_Notice')->set('无法验证 reCAPTCHA,请重试.', 'error');
                $user->response->goBack();
            }
        }
        Typecho_Plugin::deactivate('GrCv3Protect');
        return $user->login($name, $password, $temporarily, $expire);
    }

    public static function commentAction($comments, $obj) {
        $user = $obj->widget('Widget_User');
        if ($user->hasLogin() && $user->pass('administrator', true)) {
            return $comments;
        }
        $config = Helper::options()->plugin('GrCv3Protect');
        if(!in_array("comment",$config->Protect) || empty($config->key) || empty($config->secret)){
            return $comments;
        }
        $url = self::$mirror[$config->serverMirror == 1 ? "recaptcha" : "google"];
        $res = $user->request->from('g-recaptcha-response');
        $score = floatval($config->score);
        if (!empty($res) && !empty($res['g-recaptcha-response']) && self::Verify($url,$config->secret,$res['g-recaptcha-response'], $score)) {
            return $comments;
        } else {
            throw new Typecho_Widget_Exception('无法验证 reCAPTCHA,请尝试刷新页面.');
        }
    }
    public static function Verify($url, $secret, $res,$score = 0.5) {
        $url = Typecho_Common::url('recaptcha/api/siteverify', $url);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_POSTFIELDS => http_build_query(['secret' => $secret, 'response' => $res])
        ]);
        @$data = curl_exec($ch);
        @$data = @json_decode($data, true);
        return ($data['success'] === true && $data['score'] > $score);
    }
}
