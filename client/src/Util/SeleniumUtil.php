<?php

namespace Genesos\Operation\Util;

use PHPWebDriver_WebDriver;
use PHPWebDriver_WebDriverSession;

class SeleniumUtil
{
    public static function boot($browser = 'firefox', $args = []): PHPWebDriver_WebDriverSession
    {
        $wd_host = 'http://localhost:4444/wd/hub';
        $web_driver = new PHPWebDriver_WebDriver($wd_host);

        $download_dir = $args['browser.download.dir'];
        $download_dir = str_replace('\\', '\\\\\\', $download_dir);

        $firefox_profile = __DIR__ . '/../../../firefox_profile';
        $firefox_profile_contents = file_get_contents($firefox_profile . '/user.js');
        $replace = "browser.download.dir', '" . $download_dir . "');";
        $firefox_profile_contents = preg_replace('/browser\.download\.dir.+/', $replace, $firefox_profile_contents);
        file_put_contents($firefox_profile . '/user.js', $firefox_profile_contents);

        $profile = new HotfixFirefoxProfile($firefox_profile);
        return $web_driver->session($browser, ['marionette' => true], [], $profile);
    }
}
