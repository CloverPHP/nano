<?php

namespace Clover\Nano\Core;

use DateTime;
use DateTimeZone;
use Exception;

/**
 * Class Common
 * @package Clover\Nano\Core
 */
final class Common
{
    private static $timestamp;
    private static $timezone = '';

    /**
     * Check is Utf8
     * @param $string
     * @return false|int
     */
    public static function isUtf8($string)
    {
        return preg_match('%(?:'
            . '[\xC2-\xDF][\x80-\xBF]'                // non-overlong 2-byte
            . '|\xE0[\xA0-\xBF][\x80-\xBF]'           // excluding overlongs
            . '|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}'    // straight 3-byte
            . '|\xED[\x80-\x9F][\x80-\xBF]'           // excluding surrogates
            . '|\xF0[\x90-\xBF][\x80-\xBF]{2}'        // planes 1-3
            . '|[\xF1-\xF3][\x80-\xBF]{3}'            // planes 4-15
            . '|\xF4[\x80-\x8F][\x80-\xBF]{2}'        // plane 16
            . ')+%xs', $string);
    }

    /**
     * @param $content
     * @param int $minSize
     * @return string
     */
    public static function compressContent($content, $minSize = 13000)
    {
        if (strlen($content) >= $minSize)
            return 'base64://' . base64_encode(@gzdeflate($content, 9));
        else
            return $content;
    }

    /**
     * @param $compressed
     * @return string
     */
    public static function unCompressContent($compressed)
    {
        if (substr($compressed, 0, 9) === 'base64://') {
            $compressed = base64_decode(substr($compressed, 9));

            $errLvl = error_reporting(0);
            $content = @gzinflate($compressed);
            error_reporting($errLvl);
            return $content;
        }
        return $compressed;
    }

    /**
     * Weighted Randomizer, using probability array pick a item from item array
     * @param $items
     * @param $weightedArray
     * @return bool|mixed
     */
    public static function weightRndItem($items, $weightedArray)
    {
        if (!is_array($items) || !is_array($weightedArray) && count($items) !== count($weightedArray))
            return false;
        $sum = (int)array_sum($weightedArray);
        if ($sum === 0)
            return $items[array_rand($items)];
        $randomNumber = static::randomInt(1, $sum);
        foreach ($weightedArray as $key => $value) {
            $randomNumber -= $value;
            if ($randomNumber <= 0 && isset($items[$key]))
                return $items[$key];
        }
        return false;
    }

    /**
     * Shuffle for associative arrays, preserves key=>value pairs
     * @param $array
     * @return int
     */
    public static function shuffleAssoc(&$array)
    {
        $keys = array_keys($array);
        shuffle($keys);
        $new = [];
        foreach ($keys as $key)
            $new[$key] = $array[$key];
        $array = $new;
        return true;
    }

    /**
     * @param $min
     * @param $max
     * @return int
     */
    public static function randomInt($min, $max)
    {
        try {
            if (function_exists('random_int'))
                return random_int($min, $max);
            else
                return mt_rand($min, $max);
        } catch (Exception $e) {
            return mt_rand($min, $max);
        }
    }

    /**
     * Weighted Randomizer,using probability to sort then items and return the array
     * @param array $items Item Array to sort via weighted randomizer
     * @param array $weightedArray Weighted Probability
     * @param bool $unique Return unique the items value.
     * @return array|bool
     */
    public static function weightRndArray(array $items, array $weightedArray =
    [], $unique = true)
    {
        $rndAry = [];
        if ($unique === true) {
            $weightAry = [];
            $Base = (double)abs(!empty($weightedArray) ? (double)min($weightedArray) : 0);
            foreach ($items as $value)
                $weightAry[$value] = $Base + (isset($weightedArray[$value]) ? (double)$weightedArray[$value] :
                        0);
            foreach ($weightAry as $key => $value) {
                $rKey = self::weightRndKey($weightAry);
                unset($weightAry[$rKey]);
                array_push($rndAry, $rKey);
            }
        } else {
            if (count($items) !== count($weightedArray))
                return false;
            else {
                foreach ($weightedArray as $key => $value) {
                    $rKey = self::weightRndKey($weightedArray);
                    unset($weightedArray[$rKey]);
                    array_push($rndAry, $items[$rKey]);
                }
            }
        }
        return $rndAry;
    }

    /**
     * Weighted Randomizer, using the assoc probability to pick a key
     * @param $weightedArray
     * @return bool|int|mixed|string
     */
    public static function weightRndKey($weightedArray)
    {
        if (!is_array($weightedArray))
            return false;
        $sum = (int)array_sum($weightedArray);
        if ($sum === 0)
            return array_rand($weightedArray);
        $randomNumber = static::randomInt(1, $sum);
        foreach ($weightedArray as $key => $value) {
            $randomNumber -= $value;
            if ($randomNumber <= 0)
                return $key;
        }
        return false;
    }

    /**
     * @param double $rate
     * @return boolean
     */
    public static function getRateWeight($rate)
    {
        $rate = round($rate, 7);
        if ((double)$rate > 1)
            return true;
        $decimalLenght = strlen(substr(strrchr((string)$rate, "."), 1));
        $power = pow(10, empty($decimalLenght) ? 1 : (int)$decimalLenght);
        $rate *= $power;
        return (boolean)self::weightRndKey([$power - $rate, $rate]);
    }

    /**
     * @param int|double $odd
     * @param int $commission
     * @param int $fator
     * @return boolean
     */
    public static function getOddWeight($odd, $commission = 0, $fator = 1)
    {
        $commission = !empty($commission) ? $commission / 100 : 0;
        $rate = empty($odd) ? 0 : round((1 / $odd) * (1 - $commission) * $fator, 7);
        return self::getRateWeight($rate);
    }

    /**
     * @param array $oddItems
     * @param int $defaultCommission
     * @param int $defaultFactor
     * @param $probabilityOnly
     * @return int|string|array
     */
    public static function getOddsWeightedKey(array $oddItems, $defaultCommission = 0, $defaultFactor = 1, $probabilityOnly = false)
    {
        $decimalLengths = [];
        $rates = [];
        foreach ($oddItems as $key => $item) {
            $commission = isset($item['commission']) ? (double)$item['commission'] : (double)$defaultCommission;
            $commission = !empty($commission) ? $commission / 100 : 0;
            $factor = (double)(isset($item['factor']) ? $item['factor'] : $defaultFactor);
            $oddNow = (double)(isset($item['odd']) ? $item['odd'] : $item);
            $rate = empty($oddNow) ? 1 : round((1 / $oddNow) * (1 - $commission) * $factor, 7);
            $decimalLengths[] = strlen(substr(strrchr((string)$rate, "."), 1));
            $rates[$key] = $rate;
        }
        $power = pow(10, max($decimalLengths));
        $probability = [];
        foreach ($rates as $key => $rate)
            $probability[$key] = $rate * $power;
        return $probabilityOnly ? $probability : self::weightRndKey($probability);
    }

    /**
     * Check array is Assoc
     * @param array $arr
     * @return bool
     */
    public static function isAssocArray(array $arr)
    {
        if ([] === $arr) return false;
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    /**
     * Convert from string csv to array, all array value will only number.
     * @param string $string String to convert
     * @param string $dataType Number Data Type (int | double)
     * @return array
     */
    public static function strListNumeric($string, $dataType = 'double')
    {
        $list = explode(",", str_replace(" ", "", $string));
        foreach ($list as $key => $val)
            if ($dataType === 'int')
                $list[$key] = (int)trim($val);
            else
                $list[$key] = (double)trim($val);
        return $list;
    }

    /**
     * Convert from string to array, all array value is string, and also decoration is upper string
     * @param $string
     * @param string $decoration
     * @param string $delimiter
     * @return array
     */
    public static function strListString($string, $decoration = 'upper', $delimiter = ',')
    {
        if (is_array($string))
            $list = $string;
        else
            $list = explode($delimiter, $string);
        switch ($decoration) {
            case 'upper':
                foreach ($list as &$val)
                    $val = strtoupper(trim($val));
                break;

            case 'lower':
                foreach ($list as &$val)
                    $val = strtolower(trim($val));
                break;

            default:
                foreach ($list as &$val)
                    $val = trim($val);
                break;
        }
        return $list;
    }

    /**
     * Convert from string to array, all array value is string, and also decoration is upper string
     * @param $glue
     * @param $string
     * @return string
     */
    public static function strPop($glue, $string)
    {
        if (!empty($glue) && !empty($string)) {
            $array = explode($glue, $string);
            array_pop($array);
            $string = implode($glue, $array);
        }
        return $string;
    }

    /**
     * Validate Array Data with the given validate pattern
     * @param $patterns
     * @param $data
     * @return bool|array
     */
    public static function validator($patterns, $data)
    {
        $data = (array)$data;
        if (!is_array($patterns))
            $patterns = [$patterns];
        $needed = [];
        foreach ($patterns as $key => $validate) {
            if (is_int($key)) {
                $key = $validate;
                $validate = 'must';
            }
            $value = isset($data[$key]) ? $data[$key] : null;
            if (!empty($validate) && !self::validateValue($value, $validate))
                $needed[$key] = ['validate' => $validate, 'given' => $value];
        }
        return $needed;
    }


    /**
     * Validate 1 Value
     * @param mixed $value
     * @param array|string $validates
     * @return bool
     */
    final public static function validateValue($value, $validates)
    {
        $pass = true;
        $optional = false;
        if (!is_array($validates))
            $validates = !empty($validates) ? [$validates] : [];
        foreach ($validates as $key => $validate) {
            $extend = '';
            if (!is_int($key)) {
                $type = $key;
                $extend = $validate;
            } else {
                if (strpos($validate, ":") === false)
                    $type = $validate;
                else
                    list($type, $extend) = explode(":", $validate, 2);
            }
            $type = strtolower($type);
            switch ($type) {

                case 'in':
                    if (!is_array($extend))
                        $extend = !empty($extend) ? [$extend] : [];
                    $pass = static::inArray($value, $extend, true);
                    break;

                case 'like':
                    if (!is_array($extend))
                        $extend = !empty($extend) ? [$extend] : [];
                    $pass = static::inArray($value, $extend);
                    break;

                case 'date':
                    $subfix = '';
                    $extend = (string)$extend;
                    $value = (string)$value;
                    if ($extend === 'time') $subfix = '\s\d{2}:\d{2}:\d{2}';
                    $pass = preg_match("/^((19[7-9]\d|2\d{3})-\d{2}-\d{2}){$subfix}$/", $value, $match) && strtotime($value) > 0;
                    break;

                case 'match':
                    if ($extend != null)
                        $pass = $value === $extend;
                    break;

                case 'array':
                    $pass = is_array($value);
                    break;

                case 'array+':
                    $pass = is_array($value) && !empty($value);
                    break;

                case 'boolean':
                    $pass = (boolean)filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    break;

                case 'number':
                    $pass = is_numeric($value);
                    break;

                case 'number+':
                    $pass = is_numeric($value) && !empty($value);
                    break;

                case 'alphabet':
                    switch ($extend) {

                        case 'upper':
                            $pass = ctype_upper($value);
                            break;

                        case 'lower':
                            $pass = ctype_lower($value);
                            break;

                        default:
                            $pass = ctype_alpha($value);
                    }
                    break;

                case 'alphanumber':
                    $pass = ctype_alnum($value);
                    break;

                case 'ip':
                    switch ($extend) {
                        case 'v4':
                            $pass = (boolean)filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
                            break;

                        case 'v6':
                            $pass = (boolean)filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
                            break;

                        default:
                            $pass = (boolean)filter_var($value, FILTER_VALIDATE_IP);
                    }
                    break;

                case 'email':
                    $pass = (boolean)filter_var($value, FILTER_VALIDATE_EMAIL);
                    break;
                case 'url':
                    $pass = (boolean)filter_var($value, FILTER_VALIDATE_URL);
                    break;
                case 'must':
                    $pass = $value === null ? false : true;
                    break;

                case 'min':
                    $extend = (int)$extend;
                    if (!empty($extend)) {
                        if (is_array($value) || is_object($value))
                            $pass = count($value) >= $extend ? true : false;
                        else
                            $pass = strlen($value) >= $extend ? true : false;
                    }
                    break;

                case 'mbmin':
                    $extend = (int)$extend;
                    if (!empty($extend)) {
                        if (is_array($value) || is_object($value))
                            $pass = count($value) >= $extend ? true : false;
                        else
                            $pass = mb_strlen($value) >= $extend ? true : false;
                    }
                    break;

                case 'max':
                    $extend = (int)$extend;
                    if (!empty($extend)) {
                        if (is_array($value) || is_object($value))
                            $pass = count($value) <= $extend ? true : false;
                        else
                            $pass = strlen($value) <= $extend ? true : false;
                    }
                    break;

                case 'mbmax':
                    $extend = (int)$extend;
                    if (!empty($extend)) {
                        if (is_array($value) || is_object($value))
                            $pass = count($value) <= $extend ? true : false;
                        else
                            $pass = mb_strlen($value) <= $extend ? true : false;
                    }
                    break;

                case 'len':
                    $extend = (int)$extend;
                    if (!empty($extend)) {
                        if (is_array($value) || is_object($value))
                            $pass = count($value) === $extend ? true : false;
                        else
                            $pass = strlen($value) === $extend ? true : false;
                    }
                    break;

                case 'mblen':
                    $extend = (int)$extend;
                    if (!empty($extend)) {
                        if (is_array($value) || is_object($value))
                            $pass = count($value) === $extend ? true : false;
                        else
                            $pass = mb_strlen($value) === $extend ? true : false;
                    }
                    break;

                case 'username':
                    $pass = (boolean)preg_match("/^[a-zA-Z][\w!@#$%^&*()+=]*$/", $value);
                    break;
                case 'printable':
                    $pass = (boolean)preg_match("/^[[:print:]]+$/u", $value);
                    break;
                case 'content':
                    $extend = (string)$extend;
                    $extend = preg_match("/^\s+$/", $extend) ? $extend : '';
                    $pass = (boolean)static::safeUtf8($value, $extend);
                    break;

                case 'amount':
                    $pass = (boolean)preg_match('/^\d{1,10}(\.\d{0,2})?$/', $value);
                    break;

                case 'optional':
                    if ($value === NULL)
                        $optional = true;
                    continue 2;

                case 'regexp':
                case 'regex':
                    $pass = (boolean)preg_match($extend, $value);
                    break;

                default:
                    if (is_array($validate))
                        $pass = in_array($value, $validate);
                    else
                        $pass = (boolean)preg_match($extend, $value);
            }
            if (!$pass)
                return $pass || $optional;
        }
        return true;
    }

    /**
     * Convert File size to File Size Unit.
     * @param $needle
     * @param $haystack
     * @param bool $strict
     * @return string File Size with Unit
     */
    public static function inArray($needle, array $haystack, $strict = false)
    {
        if ($strict)
            return in_array($needle, $haystack, $strict);
        else {
            foreach ($haystack as $item) {
                if ($needle == $item)
                    return true;
            }
            return false;
        }
    }

    /**
     * Check is Utf8
     * @param $string
     * @param $space
     * @return false|int
     */
    public static function safeUtf8($string, $space = '\x20')
    {
        return preg_match('/^(?:'
            . '[\x21-\x7E' . $space . ']'
            . '|[\xC2-\xDF][\x80-\xBF]'                // non-overlong 2-byte
            . '|\xE0[\xA0-\xBF][\x80-\xBF]'           // excluding overlongs
            . '|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}'    // straight 3-byte
            . '|\xED[\x80-\x9F][\x80-\xBF]'           // excluding surrogates
            . '|\xF0[\x90-\xBF][\x80-\xBF]{2}'        // planes 1-3
            . '|[\xF1-\xF3][\x80-\xBF]{3}'            // planes 4-15
            . '|\xF4[\x80-\x8F][\x80-\xBF]{2}'        // plane 16
            . ')+$/xs', $string);
    }

    /**
     * Convert File size to File Size Unit.
     * @param int $bytes Number of Bytes
     * @return string File Size with Unit
     */
    public static function fileSize2Unit($bytes)
    {
        if ($bytes >= 1099511627776)
            return number_format($bytes / 1073741824, 2) . ' TB';
        elseif ($bytes >= 1073741824)
            return number_format($bytes / 1073741824, 2) . ' GB';
        elseif ($bytes >= 1048576)
            return number_format($bytes / 1048576, 2) . ' MB';
        elseif ($bytes >= 1024)
            return number_format($bytes / 1024, 2) . ' KB';
        elseif ($bytes > 1)
            return $bytes . ' bytes';
        elseif ($bytes == 1)
            return $bytes . ' byte';
        else
            return '0 bytes';
    }

    /**
     * Same as array_search, but will search entire array return those found keys.
     * @param $needle
     * @param $arrayHaystack
     * @return array
     */
    public static function arraySearchAll($needle, $arrayHaystack)
    {
        $Array = [];
        foreach ($arrayHaystack as $key => $value)
            if ($arrayHaystack[$key] === $needle)
                $Array[] = $key;
        return (count($Array) > 1 ? $Array : $Array[0]);
    }

    /**
     * Randomly generate given length ascii safe string
     * @param int $length
     * @param string $replaceCodeBase
     * @return string
     */
    public static function genCode($length = 8, $replaceCodeBase = '')
    {
        $result = '';
        $codeBase = is_string($replaceCodeBase) && !empty($replaceCodeBase) ? $replaceCodeBase :
            '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $max = strlen($codeBase) - 1;
        if ($max < 1)
            return '';
        for ($i = 0; $i < $length; ++$i)
            $result .= $codeBase[static::randomInt(0, $max)];
        return $result;
    }

    /**
     * Generate UUID of version 4
     * @return string
     */
    public static function genUuidV4()
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    /**
     * @param $content
     * @param array $data
     * @param null $default
     * @param string $pattern
     * @return string
     */
    public static function strTemplateApply($content, array $data, $default = null, $pattern = '%([\w]+)[^%]*%')
    {
        $matches = [];
        if (!is_array($data))
            $data = [];
        if ($default !== null && !is_string($default))
            $default = json_encode($default);
        if (preg_match_all("/({$pattern})/", $content, $matches, PREG_PATTERN_ORDER)) {
            $search = [];
            $replace = [];
            foreach ($matches[0] as $index => $match) {
                $keyword = $matches[2][$index];
                if (isset($data[$keyword])) {
                    if (!is_string($data[$keyword]))
                        $data[$keyword] = json_encode($data[$keyword]);
                    $search[] = $match;
                    $replace[] = $data[$keyword];
                } elseif ($default !== null) {
                    $search[] = $match;
                    $replace[] = $default;
                }
            }
            $content = str_replace($search, $replace, $content);
        }
        return $content;
    }

    /**
     * Read from file and decompress and deserialize
     * @param $fileName
     * @param bool $compress
     * @return mixed
     */
    public static function fileToData($fileName, $compress = true)
    {
        $Content = static::fileContents($fileName);
        if (!empty($Content)) {
            if ($compress)
                return json_decode(gzuncompress($Content), true);
            else
                return json_decode($Content, true);
        } else
            return $Content;
    }

    /**
     * Read file to data or write data to file
     * @param $fileName
     * @param null $data
     * @return int|string|null
     */
    public static function fileContents($fileName, $data = null)
    {
        if (!$data && file_exists($fileName))
            return file_get_contents($fileName);
        elseif ($data)
            return file_put_contents($fileName, $data);
        else
            return null;
    }

    /**
     * Serialize and compress data and write to file
     * @param $fileName
     * @param bool $data
     * @param bool $compress
     * @return mixed
     */
    public static function dataToFile($fileName, $data, $compress = true)
    {
        if ($compress)
            $data = @gzcompress(@json_encode($data), 9);
        else
            $data = json_encode($data);
        return static::fileContents($fileName, $data);
    }

    /**
     * Same as array_rand but is return value or array if more then 1.
     * @param $array
     * @param $picked
     * @return array
     */
    public static function arrayRandom($array, $picked = 1)
    {
        shuffle($array);
        $picked = (int)$picked;
        $picked = $picked > count($array) ? count($array) : $picked;
        $result = [];
        for ($i = 0; $i < $picked; $i++)
            $result[] = $array[$i];
        return $picked === 1 ? $result[0] : $result;
    }

    /**
     * Same as arrayRandom but is return value or assoc array if more then 1.
     * @param $array
     * @param $picked
     * @return array
     */
    public static function arrayRandomAssoc($array, $picked = 1)
    {
        $total = count($array);
        if ($picked > $total)
            $picked = $total;
        $keys = array_keys($array);
        shuffle($keys);
        $result = [];
        for ($i = 0; $i < $picked; $i++)
            $result[$keys[$i]] = $array[$keys[$i]];
        return $picked === 1 ? reset($result) : $result;
    }

    /**
     * Array walk calculation, require
     * @param $array
     * @param $number
     * @param string $operate
     * @return array
     */
    public static function arrayCalcUp($array, $number, $operate = '*')
    {
        if (!is_array($array) || empty($array))
            return $array;

        $number = (double)$number;

        foreach ($array as $key => $value) {
            switch (gettype($value)) {

                case 'array':
                    $array[$key] = static::arrayCalcUp($value, $number, $operate);
                    break;

                case 'integer':
                case 'double':
                case 'float':
                    switch ($operate) {
                        case '+':
                            $array[$key] += $number;
                            break;
                        case '-':
                            $array[$key] -= $number;
                            break;
                        case '*':
                            $array[$key] *= $number;
                            break;
                        case '/':
                            $array[$key] /= $number;
                            break;
                        case '\\':
                            $array[$key] = round($array[$key] / $number);
                            break;
                        case '^':
                            $array[$key] = pow($array[$key], $number);
                            break;
                        case '%':
                            $array[$key] %= $number;
                            break;
                        case '`':
                            $array[$key] = pow($array[$key], 1 / $number);
                            break;
                        case '<<':
                            $array[$key] <<= $number;
                            break;
                        case '>>':
                            $array[$key] >>= $number;
                            break;
                    }
                    break;
            }
        }
        return $array;
    }

    /**
     * Array Number Add Up
     * @param array $data
     * @param array $append
     * @param array $expectedKey
     * @return array|float
     */
    final static public function arrayNumAddUp($data, $append, $expectedKey = [])
    {
        if (!is_array($expectedKey))
            $expectedKey = !empty($expectedKey) ? [$expectedKey] : [];
        if (is_array($append)) {
            if (!is_array($data))
                $data = [];
            foreach ($append as $label => $value) {
                if (!in_array($label, $expectedKey, true)) {
                    if (is_array($value)) {
                        if (!isset($data[$label]))
                            $data[$label] = [];
                        $data[$label] = static::arrayNumAddUp($data[$label], $value, $expectedKey);
                    } else {
                        if (!isset($data[$label]))
                            $data[$label] = (double)$value;
                        else {
                            $data[$label] = (double)$data[$label];
                            $data[$label] += $value;
                        }
                    }
                }
            }
        } else {
            $data = (double)$data;
            $data += $append;
        }
        return $data;
    }

    /**
     * Array Number SubDown
     * @param $data
     * @param $subtrahend
     * @param bool $negative
     * @param bool $removeEmpty
     * @param array $expectedKey
     * @return array
     */
    final static public function arrayNumCutDown($data, $subtrahend, $negative = false, $removeEmpty = true, $expectedKey = [])
    {
        if (!is_array($expectedKey))
            $expectedKey = !empty($expectedKey) ? [$expectedKey] : [];
        if (is_array($subtrahend)) {
            if (!is_array($data))
                $data = [];
            foreach ($subtrahend as $label => $value) {
                if (!in_array($label, $expectedKey, true)) {
                    if (is_array($value)) {
                        if (!isset($data[$label]))
                            $data[$label] = [];

                        $data[$label] = static::arrayNumCutDown($data[$label], $value, $negative, $removeEmpty, $expectedKey);
                        if ($removeEmpty === true && empty($data[$label]))
                            unset($data[$label]);
                    } else {
                        if (!isset($data[$label]))
                            $data[$label] = -1 * (double)$value;
                        else
                            $data[$label] += -1 * (double)$value;

                        if ($negative === false && $data[$label] < 0)
                            $data[$label] = 0;

                        if ($removeEmpty === true && $data[$label] === 0)
                            unset($data[$label]);
                    }
                }
            }
        } else {
            $data = -1 * (double)$data;
            $data += -1 * (double)$subtrahend;
            if ($negative === false && $data < 0)
                $data = 0;
        }
        return $data;
    }

    /**
     * Mosaic to string
     * @param $string
     * @param int $start
     * @param int $end
     * @param int $length
     * @param string $toReplace
     * @return mixed|string
     */
    public static function strMosaic($string, $start = 0, $end = 6, $length = 0, $toReplace = 'x')
    {
        $string = (string)$string;
        $subLength = strlen(substr($string, $start, $end));
        $strLength = $length !== 0 ? $length - (strlen($string) - $subLength) : $subLength;
        $strLength = $strLength <= 0 ? 1 : $strLength;
        $string = substr_replace($string, str_repeat($toReplace, $strLength), $start, $end);
        return $string;
    }

    /**
     * @param $processId
     * @return array|bool
     */
    public static function processCPUTime($processId)
    {
        $processId = (int)$processId;
        if (!$processId)
            return false;
        $path = "/proc/{$processId}/stat";
        if (!file_exists($path))
            return false;
        $info = @file_get_contents($path);
        if (!$info)
            return false;
        $data = explode(" ", $info);
        if (!isset($data[13]) || !isset($data[14]) || !isset($data[15]) || !isset($data[16]))
            return false;
        return [
            'utime' => (int)$data[13],
            'stime' => (int)$data[14],
            #'cutime' => (int) $data[15],
            #'cstime' => (int) $data[16]
        ];
    }

    /**
     * @param $array
     */
    public static function arrayValNum(&$array)
    {
        array_walk($array, function (&$value) {
            if (is_numeric($value)) $value = (double)$value;
        }
        );
    }

    /**
     * Get Specify datetime by timezone
     * @param $timeZone
     * @param string $format
     * @param null $time
     * @return string
     * @throws Exception
     */
    public static function specifyTimeZone($timeZone, $format = 'Y-m-d H:i:s', $time = null)
    {
        $dateTime = new DateTime(is_string($time) ? $time : 'now', new DateTimeZone($timeZone));
        if (is_integer($time))
            $dateTime->setTimestamp($time);
        return $dateTime->format($format);
    }

    /**
     * Get current Timezone
     * @return string
     */
    public static function timezone()
    {
        return static::$timezone;
    }

    /**
     * Get Initial Date or current Date
     * @return string
     */
    public static function date()
    {
        return date("Y-m-d");
    }

    /**
     * Get Initial Time or current Time
     * @return string
     */
    public static function time()
    {
        return date("H:i:s");
    }

    /**
     * Get Initial Timestamp or current Timestamp
     * @param bool $current
     * @return double
     */
    public static function timestamp($current = false)
    {
        return $current === true ? round(microtime(true),3) : static::$timestamp;
    }

    /**
     * Delay the timestamp
     * @param int $second
     */
    public static function delay($second = 0)
    {
        sleep($second);
        static::initial(static::$timezone);
    }

    /**
     * Initialize Date Time
     * @param $timezone
     */
    public static function initial($timezone)
    {
        if (empty(static::$timestamp))
            static::$timestamp = microtime(true);

        if (!empty($timezone) && is_string($timezone) && in_array($timezone, timezone_identifiers_list(), true)) {
            date_default_timezone_set($timezone);
            static::$timezone = $timezone;
        }
    }

    /**
     * Convert Second to Time
     * @param int $seconds
     * @return float
     */
    public static function secondToTime($seconds = 0)
    {
        $hours = floor($seconds / 3600);
        $minutes = floor($seconds / 60 % 60);
        $secs = floor($seconds % 60);
        return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
    }

    /**
     * Convert Time to Second
     * @param string $time H:i:s format
     * @return float|int
     */
    public static function timeToSecond($time)
    {
        $hours = 0;
        $minutes = 0;
        $seconds = 0;
        $time = preg_replace("/^([\d]{1,2}):([\d]{2})$/", "00:$1:$2", $time);
        sscanf($time, "%d:%d:%d", $hours, $minutes, $seconds);
        return $hours * 3600 + $minutes * 60 + $seconds;
    }

    /**
     * Sort Multi Dimension Array
     * @param array $array Data to sort
     * @param mixed $orderColumns Order Method, key is field, value is sort method
     * @param bool $maintainKey maintain index association
     * @param int|array $defaultSortFlag
     */
    final static public function multiArraySort(&$array, $orderColumns, $maintainKey = false, $defaultSortFlag = SORT_ASC)
    {
        $args = [];
        if (is_string($orderColumns))
            $orderColumns = [$orderColumns];
        if (!is_array($orderColumns))
            return;
        foreach ($orderColumns as $field => $orderFlags) {
            if (is_string($orderFlags)) {
                $field = $orderFlags;
                $orderFlags = $defaultSortFlag;
            }
            if (!is_array($orderFlags))
                $orderFlags = [$orderFlags];
            $orderBy = [];
            foreach ($orderFlags as $orderMethod)
                if (in_array($orderMethod, [SORT_ASC, SORT_DESC, SORT_REGULAR, SORT_NUMERIC,
                    SORT_STRING, SORT_LOCALE_STRING, SORT_NATURAL, SORT_FLAG_CASE,], true))
                    $orderBy[] = $orderMethod;
            if (!empty($orderBy)) {
                $sortCol = [];
                foreach ($array as $key => $row) {
                    if (isset($row[$field]))
                        $sortCol[$key] = $row[$field];
                    else
                        continue(2);
                }
                $args[] = $sortCol;
                foreach ($orderBy as $flag)
                    $args[] = $flag;
            }
        }
        if ($maintainKey) {
            $keys = array_keys($array);
            $args[] = &$array;
            $args[] = &$keys;
            call_user_func_array('array_multisort', $args);
            $array = array_combine($keys, $array);
        } else {
            $args[] = &$array;
            call_user_func_array('array_multisort', $args);
        }
    }

    /**
     * Get numbers of decimal without round up or down.
     * @param $number
     * @param int $decimal
     * @return float
     */
    public static function leaveDecimal($number, $decimal = 2)
    {
        $decimal = (int)$decimal + 1;
        $string = strval($number);
        $point = strpos($string, ".");
        $number = $point !== false ? (double)substr($string, 0, $point + $decimal) : $number;
        return round($number, $decimal);
    }

    /**
     * @param $credit
     * @param $divNum
     * @param int $decimal
     * @return mixed
     */
    public static function calDiv($credit, $divNum, $decimal = 2)
    {
        $decimal = (int)$decimal;
        $base = pow(10, $decimal);
        $module = (($credit * $base) % $divNum) / $base;
        return $credit - $module;
    }

    /**
     * sorting
     * @param $array
     * @param int $sort_flags
     * @return bool
     */
    public static function kSortRecursive(&$array, $sort_flags = SORT_REGULAR)
    {
        if (!is_array($array)) return false;
        ksort($array, $sort_flags);
        foreach ($array as &$arr) {
            self::kSortRecursive($arr, $sort_flags);
        }
        return true;
    }

    /**
     * @param $Cards
     * @param $Number
     * @return array
     */
    public static function arrayUnique($Cards, $Number)
    {
        $array1 = self::arrayCombination($Cards, $Number);
        $result = [];
        foreach ($array1 as $key => $arrayA)
            foreach ($arrayA as $key2 => $arrayB)
                if ($key !== $key2 && count(array_intersect($arrayA, $arrayB)) == 0)
                    $result[] = [$arrayA, $arrayB];
        return $result;
    }

    /**
     * Split the array to any available combination array, e.g. $Array = (1,2,3), $Choose = 2  return = (1,2),(1,3),(2,3)
     * @param array $array Array to split
     * @param int $choose Number to split
     * @return array Split Result
     */
    public static function arrayCombination(array $array, $choose)
    {
        $composer = function (&$combination, &$composed, $start, $_choose, $arr, $n) use (&$composer) {
            if ($_choose == 0)
                array_push($combination, $composed);
            else
                for ($i = $start; $i <= $n - $_choose; ++$i) {
                    array_push($composed, $arr[$i]);
                    if ($_choose - 1 == 0)
                        array_push($combination, $composed);
                    else
                        $composer($combination, $composed, $i + 1, $_choose - 1, $arr, $n);
                    array_pop($composed);
                }
        };

        $n = count($array);
        $combination = [];
        $composed = [];
        $composer($combination, $composed, 0, $choose, $array, $n);
        return $combination;
    }

    /**
     * Get Current Date -1 Days if current time < $offset.
     * @param $offset
     * @param string $dateTime
     * @return bool|string
     */
    public static function offsetDate($offset, $dateTime = '')
    {
        if (empty($dateTime))
            $dateTime = static::datetime();

        $timeStamp = strtotime($dateTime);
        $date = date("Y-m-d", $timeStamp);
        $offset = $date . $offset;
        $offsetTimeStamp = strtotime($offset);

        if ($timeStamp >= $offsetTimeStamp)
            return date("Y-m-d", time());
        else
            return date("Y-m-d", strtotime($dateTime . " -1 Days"));
    }

    /**
     * Get Initial DateTime or current DateTime
     * @return string
     */
    public static function datetime()
    {
        return date("Y-m-d H:i:s");
    }

    /**
     * Array Add Up
     * @param array $data
     * @param array $append
     * @return array|float
     */
    final static public function addAppend($data, $append)
    {
        switch (gettype($append)) {
            case 'integer':
            case 'double':
                $data = (double)$data;
                $data += (double)$append;
                break;
            case 'string':
                $data = (string)$data;
                $data .= $append;
                break;
            default:
                $data = $append;
        }
        return $data;
    }
}
