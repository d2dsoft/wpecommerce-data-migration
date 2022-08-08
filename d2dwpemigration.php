<?php

/**
 * D2dSoft
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL v3.0) that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL: https://d2d-soft.com/license/AFL.txt
 *
 * DISCLAIMER
 * Do not edit or add to this file if you wish to upgrade this extension/plugin/module to newer version in the future.
 *
 * @author     D2dSoft Developers <developer@d2d-soft.com>
 * @copyright  Copyright (c) 2021 D2dSoft (https://d2d-soft.com)
 * @license    https://d2d-soft.com/license/AFL.txt
 */

/*
Plugin Name: D2dSoft Wp-eCommerce Data Migration
Plugin URI: https://d2d-soft.com
Description: Wp-eCommerce Data Migration by D2dSoft will let you to import/export all your products, orders, customers, categories, reviews and other entities, preserving relations between them.
Author: D2dSoft
Version: 1.0.0
Author URI: https://d2d-soft.com
*/

defined('ABSPATH') or die();

class D2dWpeMigration
{
    const PACKAGE_URL = 'https://d2d-soft.com/download_package.php';

    protected $migrationApp;

    /* @var D2dWpeMigration */
    protected static $_instance = null;

    /* @TODO: INIT */

    public static function getInstance() {
        if (!self::$_instance) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function run(){
        add_action('init', array($this, 'startSession'), 1);
        add_action('admin_menu', array($this, 'initMenu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueueScripts'));
        add_action('admin_post_dwem_license_form', array($this, 'submitLicenseForm'));
        add_action('admin_post_dwem_setting_form', array($this, 'submitSettingForm'));
        add_action('wp_ajax_d2d_wpemigration', array($this, 'processAjax'));
    }

    /* @TODO: CONFIG */

    public function startSession() {
        if(!session_id()) {
            session_start();
        }
    }

    public function initMenu(){
        $has_main_menu = $this->menuPageExists();
        if(!$has_main_menu){
            add_menu_page('D2dSoft', 'D2dSoft', 'administrator', 'd2dsoft', '', self::url() . 'logo.png');
        }
        add_submenu_page('d2dsoft', 'WooCommerce Migration', 'Wp-eCommerce Migration', 'administrator', 'wpemigration', array($this, 'display'));
        remove_submenu_page('d2dsoft', 'd2dsoft');
    }

    public function enqueueScripts(){
        $plugin_url = self::url();
        wp_enqueue_style('dwm-select2', $plugin_url . 'assets/css/select2.min.css');
        wp_enqueue_style('dwm-style', $plugin_url . 'assets/css/style.css');
        wp_enqueue_style('dwm-custom', $plugin_url . 'assets/css/custom.css');
        wp_enqueue_script('jquery');
        wp_enqueue_script('dwm-bootbox', $plugin_url . 'assets/js/bootbox.min.js');
        wp_enqueue_script('dwm-select2', $plugin_url . 'assets/js/select2.min.js');
        wp_enqueue_script('dwm-form', $plugin_url . 'assets/js/jquery.form.min.js');
        wp_enqueue_script('dwm-validate', $plugin_url . 'assets/js/jquery.validate.min.js');
        wp_enqueue_script('dwm-extend', $plugin_url . 'assets/js/jquery.extend.js');
        wp_enqueue_script('dwm-migration', $plugin_url . 'assets/js/jquery.migration.js');
    }

    public function submitLicenseForm(){
        $license = $this->getArrayValue($_POST, 'license');
        if(!$license){
            $this->redirectPluginPage('license');
            return;
        }
        $install = $this->downloadAndExtraLibrary($license);
        if(!$install){
            $this->redirectPluginPage('license');
            return;
        }
        if(!$this->isInstallLibrary()){
            $this->redirectPluginPage('license');
            return;
        }
        $app = $this->getMigrationApp();
        $initTarget = $app->getInitTarget();
        $install_db = $initTarget->setupDatabase($license);
        if(!$install_db){
            $this->redirectPluginPage('license');
        }
        $this->redirectPluginPage();
        return;
    }

    public function submitSettingForm(){
        if(!$this->isInstallLibrary()){
            $this->redirectPluginPage('license');
            return;
        }
        $keys = array(
            'license', 'storage', 'taxes', 'manufacturers', 'customers', 'orders', 'reviews', 'delay', 'retry', 'src_prefix', 'target_prefix', 'other'
        );
        $app = $this->getMigrationApp();
        $target = $app->getInitTarget();
        foreach($keys as $key){
            $value = $this->getArrayValue($_POST, $key, '');
            $target->dbSaveSetting($key, $value);
        }
        $this->setMessage('success', 'Save successfully.');
        $this->redirectPluginPage('setting');
    }

    public function processAjax(){
        $action_type = $this->getArrayValue($_REQUEST, 'action_type', 'import');
        if($action_type == 'import'){
            $app = $this->getMigrationApp();
            $process = $this->getArrayValue($_REQUEST, 'process');
            if(!$process || !in_array($process, array(
                    D2dInit::PROCESS_SETUP,
                    D2dInit::PROCESS_CHANGE,
                    D2dInit::PROCESS_UPLOAD,
                    D2dInit::PROCESS_STORED,
                    D2dInit::PROCESS_STORAGE,
                    D2dInit::PROCESS_CONFIG,
                    D2dInit::PROCESS_CONFIRM,
                    D2dInit::PROCESS_PREPARE,
                    D2dInit::PROCESS_CLEAR,
                    D2dInit::PROCESS_IMPORT,
                    D2dInit::PROCESS_RESUME,
                    D2dInit::PROCESS_REFRESH,
                    D2dInit::PROCESS_FINISH))){
                $this->responseJson(array(
                    'status' => 'error',
                    'message' => 'Process Invalid.'
                ));
                return;
            }
            $response = $app->process($process);
            $this->responseJson($response);
        }
        if($action_type == 'download'){
            $app = $this->getMigrationApp();
            $app->process(D2dInit::PROCESS_DOWNLOAD);
            exit;
        }
        $this->responseJson(array(
            'status' => 'error',
            'message' => ''
        ));
        return;
    }

    public function display(){
        if(!$this->isInstallLibrary()){
            $folder = $this->getLibraryFolder();
            if(!is_writeable($folder)){
                $folder_name = 'd2dwpemigration' . $this->getLibraryLocation();
                $this->setMessage('error', 'Folder "' . $folder_name . '" must is a writable folder.');
            }
            if(!ini_get('allow_url_fopen')){
                $this->setMessage('error', 'The PHP "allow_url_fopen" must is enabled. Please follow <a href="https://www.a2hosting.com/kb/developer-corner/php/using-php.ini-directives/php-allow-url-fopen-directive" target="_blank">here</a> to enable the setting.');
            }
            $messages = $this->getMessage();
            $this->view('license.php', array(
                'messages' => $messages,
            ));
            return;
        }
        $page_type = $this->getArrayValue($_REQUEST, 'page_type');
        switch($page_type){
            case 'license':
                $folder = $this->getLibraryFolder();
                if(!is_writeable($folder)){
                    $folder_name = 'd2dwpemigration' . $this->getLibraryLocation();
                    $this->setMessage('error', 'Folder "' . $folder_name . '" must is a writable folder.');
                }
                if(!ini_get('allow_url_fopen')){
                    $this->setMessage('error', 'The PHP "allow_url_fopen" must is enabled. Please follow <a href="https://www.a2hosting.com/kb/developer-corner/php/using-php.ini-directives/php-allow-url-fopen-directive" target="_blank">here</a> to enable the setting.');
                }
                $messages = $this->getMessage();
                $this->view('license.php', array(
                    'messages' => $messages,
                ));
                break;
            case 'setting':
                $app = $this->getMigrationApp();
                $target = $app->getInitTarget();
                $settings = $target->dbSelectSettings();
                $messages = $this->getMessage();
                $this->view('setting.php', array(
                    'settings' => $settings,
                    'messages' => $messages,
                ));
                break;
            default:
                $app = $this->getMigrationApp();
                $target = $app->getInitTarget();
                $response = $app->process(D2dInit::PROCESS_INIT);
                $html = '';
                if($response['status'] == D2dCoreLibConfig::STATUS_SUCCESS){
                    $html = $response['html'];
                }
                $this->view('migration.php', array(
                    'html_content' => $html,
                    'js_config' => $target->getConfigJs()
                ));
                break;
        }
    }

    /* @TODO: PROCESS */

    protected function downloadAndExtraLibrary($license = '')
    {
        $url = self::PACKAGE_URL;
        $library_folder = $this->getLibraryFolder();
        if(!is_dir($library_folder))
            @mkdir($library_folder, 0777, true);
        $tmp_path = $library_folder . '/resources.zip';
        $data = array(
            'license' => $license
        );
        $fp = @fopen($tmp_path, 'wb');
        if(!$fp){
            return false;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:40.0) Gecko/20100101 Firefox/40.0');
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_FAILONERROR, TRUE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLINFO_HEADER_OUT, TRUE);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        $response = curl_exec($ch);
        if(curl_errno($ch)){
            return false;
        }
        curl_close($ch);
        @fclose($fp);
        if(!$response){
            return false;
        }

        $zip = new ZipArchive;
        if ($zip->open($tmp_path) === TRUE) {
            $zip->extractTo($library_folder);
            $zip->close();

            @unlink($tmp_path);
            return true;
        } else {
            return false;
        }
    }

    protected function getMigrationApp()
    {
        if($this->migrationApp){
            return $this->migrationApp;
        }
        $user_id = get_current_user_id();
        $library_folder = $this->getLibraryFolder();
        include_once $this->getInitLibrary();
        D2dInit::initEnv();
        $app = D2dInit::getAppInstance(D2dInit::APP_HTTP, D2dInit::TARGET_RAW, 'wpecommerce');
        $app->setRequest($_REQUEST);
        $config = array();
        $config['user_id'] = $user_id;
        $config['upload_dir'] = $library_folder . '/files';
        $config['upload_location'] = $library_folder . '/files';
        $config['log_dir'] = $library_folder . '/log';
        $app->setConfig($config);
        $this->migrationApp = $app;
        return $this->migrationApp;
    }

    /* @TODO: PLUGIN */

    public static function path(){
        return plugin_dir_path(__FILE__);
    }

    public static function url(){
        return plugin_dir_url(__FILE__);
    }

    public function menuPageExists($slug = 'd2dsoft')
    {
        global $menu;
        $exists = false;
        foreach ($menu as $order => $menu_item) {
            if ($slug == $menu_item[2]) {
                $exists = true;
                break;
            }
        }
        return $exists;
    }

    public function view($template, $binds = array()){
        $path = self::path() . 'views/' . $template;
        foreach($binds as $k => $v){
            $$k = $v;
        }
        if(file_exists($path)){
            include $path;
        }
    }

    public function redirectToPage($menu_slug, $params = array()){
        $url = $url = $this->getAdminPage($menu_slug, $params);
        wp_redirect($url);
        return;
    }

    public function redirectPluginPage($page_type = ''){
        $params = array();
        if($page_type){
            $params['page_type'] = $page_type;
        }
        $this->redirectToPage('wpemigration', $params);
        return;
    }

    public function getAdminPage($menu_slug, $params = array()){
        $url = 'admin.php?page=' . $menu_slug;
        if($params){
            $url .= '&' . http_build_query($params);
        }
        return admin_url($url);
    }

    public function getPluginPage($page_type = ''){
        $params = array();
        if($page_type){
            $params['page_type'] = $page_type;
        }
        return $this->getAdminPage('wpemigration', $params);
    }

    public function setMessage($type, $message){
        $messages = $_SESSION['migration_message'];
        if(!$messages)
            $messages = array();
        $messages[] = array(
            'type' => $type,
            'message' => $message
        );
        $_SESSION['migration_message'] = $messages;
        return $this;
    }

    public function getMessage(){
        $messages = $_SESSION['migration_message'];
        $_SESSION['migration_message'] = array();
        return $messages;
    }

    public function getSubmitFormAction(){
        return esc_url(admin_url('admin-post.php'));
    }

    /* @TODO: LIBRARY */

    public function getLibraryLocation(){
        return '/data';
    }

    public function getLibraryFolder(){
        $location = $this->getLibraryLocation();
        $folder = self::path() . $location;
        return $folder;
    }

    public function getInitLibrary(){
        $library_folder = $this->getLibraryFolder();
        return $library_folder . '/resources/init.php';
    }

    public function isInstallLibrary(){
        $init_file = $this->getInitLibrary();
        return file_exists($init_file);
    }

    /* @TODO: UTILS */

    public function responseJson($data){
        echo json_encode($data);
        exit;
    }

    public function getArrayValue($array, $key, $default = null){
        return isset($array[$key]) ? $array[$key] : $default;
    }
}

D2dWpeMigration::getInstance()->run();