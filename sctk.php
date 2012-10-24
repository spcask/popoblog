<?php
class SCTK
{
    public static function rm($name) {
        if (! file_exists($name)) {
            return FALSE;
        }

        if (is_file($name)) {
            return unlink($name);
        } else if (is_dir($name)) {
            $objects = scandir($name);
            foreach ($objects as $object) {
                if ($object != '.' && $object != '..') {
                    $object = "{$name}$object";
                    self::rm($object);
                }
            }
            rmdir($name);
        }
    }

    public static function getHostURL()
    {
        return "http://{$_SERVER['HTTP_HOST']}/";
    }

    public static function getURL()
    {
        return "http://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
    }

    public static function url($u, $text = '')
    {
        if ($u[0] == '/') {
            $u = "http://{$_SERVER['HTTP_HOST']}{$u}";
        }

        if ($text == '')
            $text = $u;

        return "<a href=\"$u\">$text</a>";
    }

    public static function header($status_code)
    {
        header("{$_SERVER['SERVER_PROTOCOL']} $status_code");
    }

    public static function redirect($url, $status_code)
    {
        header("Location: $url", TRUE, $status_code); 
    }


    public static function get($arr, $key, $default = '')
    {
        return isset($arr[$key]) ? $arr[$key] : $default;
    }

    /**
     * Converts the specified word to its plural form if the specified
     * count is greater than 1.
     *
     * This method simply adds an 's' as a prefix to the word if the
     * specified count exceeds 1. If the count does not exceed 1, the
     * word is returned unchanged.
     *
     * @param string $word    Word to be converted to plural form if
     *                        the $count > 1.
     * @param integer $count  Count to be used to decide if the word is
     *                        plural or not.
     * @return string         Plural form of the specified $word if
     *                        $count > 1; $word otherwise.
     */
    public static function plural($word, $count = 2)
    {
        if ($count <= 1)
            return $word;
        else
            return $word . 's';
    }

    public static function fileToHTML($filePath)
    {
        return htmlspecialchars(
            trim(file_get_contents($filePath)), ENT_COMPAT, 'UTF-8'
        );
    }
}
?>
