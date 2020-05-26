<?php

namespace Module;

/**
 * Class Mime
 * @package Module
 */
class Mime
{
    static $exts = [
        'txt' => 'text/plan',
        'csv' => 'text/csv',
        'htm' => 'text/html',
        'html' => 'text/html',
        'css' => 'text/css',
        'js' => 'text/javascript',

        'ttf' => 'font/ttf',
        'otf' => 'font/otf',
        'eot' => 'font/vnd.ms-fontobject',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',

        'tif' => 'image/tiff',
        'tiff' => 'image/tiff',
        'svg' => 'image/svg+xml',
        'svgz' => 'image/svg+xml',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'jpeg' => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'gif' => 'image/gif',
        'bmp' => 'image/bmp',
        'ico' => 'image/vnd.microsoft.icon',

        'xml' => 'application/xml',
        'json' => 'application/json',
        'pdf' => 'application/pdf',
        'ogx' => 'application/ogg',
        'rtf' => 'application/rtf',

        'zip' => 'application/zip',
        'gz' => 'application/gzip',
        'bz' => 'application/x-bzip',
        'bz2' => 'application/x-bzip2',
        'rar' => 'application/vnd.rar',
        '7z' => 'application/x-7z-compressed',
        'tar' => 'application/x-tar',

        'avi' => 'video/x-msvideo',
        'mpeg' => 'video/mpeg',
        'ogv' => 'video/ogg',
        'webm' => 'video/webm',
        'mp4' => 'video/mp4',
        'flv' => 'video/x-flv',
        'mpg' => 'video/mpeg',
        'mov' => 'video/quicktime',

        'weba' => 'audio/webm',
        'wav' => 'audio/wav',
        'mp3' => 'audio/mpeg',
        'oga' => 'audio/ogg',

        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',


        //
        'swf' => 'application/x-shockwave-flash',
        'exe' => 'application/x-msdownload',
        'msi' => 'application/x-msdownload',
        'cab' => 'application/vnd.ms-cab-compressed',
        'xsl' => 'application/xsl+xml',
        'ogg' => 'application/ogg',
        'php' => 'text/x-php',
    ];

    final public function __construct()
    {
    }

    /**
     * @param $file
     * @return array|mixed|string
     */
    public function check(&$file)
    {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (extension_loaded('fileinfo') &&
            ($finfo = finfo_open(FILEINFO_MIME)) !== false) {
            if (($type = finfo_file($finfo, $file)) !== false) {
                $type = explode(' ', str_replace('; charset=', ';charset=', $type));
                $type = array_pop($type);
                $type = explode(';', $type);
                $type = trim(array_shift($type));
            }
            finfo_close($finfo);
            if ($type !== false && strlen($type) > 0)
                return $type;
        } elseif (isset(self::$exts[$ext]))
            return self::$exts[$ext];

        return 'application/octet-stream';
    }
}