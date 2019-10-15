<?php

namespace Genesos\Operation\Util;

use ZipArchive;

class HotfixFirefoxProfile extends \PHPWebDriver_WebDriverFirefoxProfile
{
    public function encoded()
    {
        $zip = new ZipArchive();

        $filename = $this->profile_dir . '.zip';
        if (($r = $zip->open($filename, ZipArchive::CREATE)) !== true) {
            throw new \Exception("Unable to create profile zip {$this->profile_dir}");
        }

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->profile_dir, $flags = \FilesystemIterator::KEY_AS_PATHNAME | \FilesystemIterator::CURRENT_AS_FILEINFO | \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $key => $value) {
            $zip->addFile($key, substr($key, strlen($this->profile_dir) + 1)) or die ("ERROR: Could not add file: $key");
        }

        $zip->close();

        // base64 the zip
        $contents = fread(fopen($filename, 'r+b'), filesize($filename));
        return base64_encode($contents);
    }
}
