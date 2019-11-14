<?php
namespace Laraspace\Space;

use File;
use ZipArchive;
use Artisan;
use GuzzleHttp\Exception\RequestException;
use Laraspace\Space\SiteApi;
use Laraspace\Events\UpdateFinished;
use Laraspace\Setting;

class Updater
{
    use SiteApi;

    public static function update($installed, $version)
    {
        $data = null;
        $path = null;

        $url = '/download/'.$version;

        $response = static::getRemote($url, ['timeout' => 100, 'track_redirects' => true]);

        // Exception
        if ($response instanceof RequestException) {
            return [
                'success' => false,
                'errors' => 'Download Exception',
                'data' => [
                    'path' => $path
                ]
            ];
        }

        if ($response && ($response->getStatusCode() == 200)) {
            $data = $response->getBody()->getContents();
        }

        // Create temp directory
        $path = 'temp-' . md5(mt_rand());
        $path2 = 'temp2-' . md5(mt_rand());
        $temp_path = storage_path('app/temp') . '/' . $path;
        $temp_path2 = storage_path('app/temp') . '/' . $path2;

        if (!File::isDirectory($temp_path)) {
            File::makeDirectory($temp_path);
            File::makeDirectory($temp_path2);
        }

        $file = $temp_path . '/upload.zip';

        // Add content to the Zip file
        $uploaded = is_int(file_put_contents($file, $data)) ? true : false;

        if (!$uploaded) {
            return false;
        }

        // Unzip the file
        $zip = new ZipArchive();

        if ($zip->open($file)) {
            $zip->extractTo($temp_path2);
        }

        $zip->close();

        // Delete zip file
        File::delete($file);

        if (!File::copyDirectory($temp_path2.'/crater', base_path())) {
            return false;
        }

        // Delete temp directory
        File::deleteDirectory($temp_path);
        File::deleteDirectory($temp_path2);

        try {
            event(new UpdateFinished($installed, $version));

            return [
                'success' => true,
                'errors' => false,
                'data' => []
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'errors' => 'Update error',
                'data' => []
            ];
        }
    }

    public static function checkForUpdate()
    {
        $data = null;
        $url = '/check/latest/download/'.Setting::getSetting('version');

        $response = static::getRemote($url, ['timeout' => 100, 'track_redirects' => true]);

        if ($response && ($response->getStatusCode() == 200)) {
            $data = $response->getBody()->getContents();
        }

        return json_decode($data);
    }
}