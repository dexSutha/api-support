<?php

namespace App\Traits;

use App\Models\JobVacancyStatus;
use Exception;

/** 
 * @var mixed $value;
 * @var mixed $key;
 * @var array $translation
 * @static $cache = [];
 * @static $instances = [];
 * @method public function __construct($value);
 * @method public function __wakeup();
 * @static public static function from($value): self;
 * @method public function getValue();
 * @method public function getKey();
 * @method public function __toString();
 * @method public function equals($variable = null): bool;
 * @static public static function keys();
 * @static public static function values();
 * @static public static function toArray();
 * @static public static function isValid($value);
 * @static public static function assertValidValue($value): void;
 * @static public static function isValidKey($key);
 * @static public static function search($value);
 * @static public static function __callStatic($name, $arguments);
 * @method private static function assertValidValueReturningKey($value): string
 * @method public function jsonSerialize();
*/

trait EnumHelper
{
    use CareerSupportHelper;
    
    public static function getTranslationByKey(string $key, string $lang = 'id')
    {
        $lang = self::getLocale();
        if(is_numeric($key)){
            $filter = array_filter(self::toArray(),function($val) use($key) {return $val === (int)$key;});
            if(count($filter)===1) $key = key($filter);
        }
        $str = '-';
        if (isset(self::$translation)) {
            if (isset(self::$translation[$lang])) {
                if (isset(self::$translation[$lang][$key])) {
                    $str = self::$translation[$lang][$key];
                }
            }
        }

        return $str;
    }

    public static function searchKeyContains(string $search)
    {
        $search = strtoupper($search);
        return array_filter(self::keys(),function($key)use($search){return str_contains($key, $search);});
    }

    public static function getValueByKey(string $key)
    {
        return self::toArray()[$key];
    }
}
