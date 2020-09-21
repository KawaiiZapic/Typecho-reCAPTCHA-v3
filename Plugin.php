<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * reCAPTCHA v3 Login Protect for Typecho
 *
 * @package GrCv3Protect
 * @author Zapic
 * @version 0.0.1
 * @link https://github.com/KawaiiZapic/Typecho-Login-reCAPTCHA-v3
 */
class GrCv3Protect_Plugin implements Typecho_Plugin_Interface {
    public static $config;
    public static $mirror = [
        "google" => "https://www.google.com",
        "recaptcha" => "https://recaptcha.net"
    ];

    public static function activate() {
        Typecho_Plugin::factory('admin/footer.php')->end = array(__CLASS__, 'ScriptLoader');
        Typecho_Plugin::factory('Widget_User')->login = array(__CLASS__, 'loginAction');
    }

    public static function deactivate() {}

    public static function personalConfig(Typecho_Widget_Helper_Form $form) {}

    public static function config(Typecho_Widget_Helper_Form $form) {
        $key = new Typecho_Widget_Helper_Form_Element_Text('key', NULL, '', _t('在<a href="https://www.google.com/recaptcha/admin">Google reCAPTCHA</a> 添加站点以获取 Site Key & Secret key<br><br>Site Key'));
        $secret = new Typecho_Widget_Helper_Form_Element_Text('secret', NULL, '', 'Secret Key');
        $jsMirror = new Typecho_Widget_Helper_Form_Element_Radio('jsMirror', array('1' => _t("recaptcha.net(国内可用)"), '0' => _t("Google.com")), '1', 'reCAPTCHA 资源加载地址');
        $serverMirror = new Typecho_Widget_Helper_Form_Element_Radio('serverMirror', array('1' => _t("recaptcha.net(国内可用)"), '0' => _t("Google.com")), '1', 'reCAPTCHA 验证地址');
        $form->addInput($key);
        $form->addInput($secret);
        $form->addInput($jsMirror);
        $form->addInput($serverMirror);
    }

    public static function ScriptLoader() {
        if (!Typecho_Widget::widget('Widget_User')->hasLogin() && __TYPECHO_ADMIN__) {
            $options = Helper::options();
            self::$config = $options->plugin('GrCv3Protect');
            if (empty(self::$config->key) || empty(self::$config->secret)) {
                return;
            }
            $url = self::$mirror[self::$config->jsMirror == 1 ? "recaptcha" : "google"];
            $key = self::$config->key;
            echo '
<script>
const GrCKey = "' . $key . '" ;
function onSubmit() {
    document.querySelector("form").submit();
}

jQuery(function () {
    jQuery(".submit button.primary").addClass("g-recaptcha").attr("data-sitekey", GrCKey).attr("data-callback", "onSubmit");
});
</script>
<script src="' . Typecho_Common::url("recaptcha/api.js?render{$key}", $url) . '"></script>';
        }
    }

    public static function loginAction($name, $password, $temporarily = false, $expire = 0) {
        $user = Typecho_Widget::widget('Widget_User');
        $options = Helper::options();
        self::$config = $options->plugin('GrCv3Protect');
        if (!empty(self::$config->key) && !empty(self::$config->secret)) {
            $res = $user->request->from('g-recaptcha-response');
            $url = self::$mirror[self::$config->serverMirror == 1 ? "recaptcha" : "google"];
            if (empty($res) || empty($res['g-recaptcha-response']) || self::Verify($url, self::$config->secret, $res['g-recaptcha-response']) !== true) {
                $user->widget('Widget_Notice')->set(_t('无法验证 reCAPTCHA,请重试.'), 'error');
                $user->response->goBack();
            }
        }
        Typecho_Plugin::deactivate('GrCv3Protect');
        return $user->login($name, $password, $temporarily, $expire);
    }

    public static function Verify($url, $secret, $res) {
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
        return @json_decode($data, true)['success'] === true;
    }
}
