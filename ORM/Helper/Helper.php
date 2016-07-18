<?php

namespace Adadgio\GraphBundle\ORM\Helper;

class Helper
{
    const SPACE = ' ';
    const COMMA = ', ';
    const COLON = ':';

    /**
     * Turns an array of labels into a labels string colon notation "A:B:C...".
     * Labels are never prefix with two dots.
     *
     * @param  array  Labels
     * @return string Labels notation shortcut.
     */
    public static function labelsToString(array $labels = array())
    {
        return static::trimColons(implode(static::COLON, $labels));
    }

    /**
     * Turn a label(s) string colons notation into an array of labels "A:B" into "array(A,B)".
     *
     * @param  string Labels colons notation.
     * @return array  Array of labels
     */
    public static function labelsToArray($labelsStr)
    {
        return explode(static::COLON, $labelsStr);
    }

    /**
     * Normalize labels into a string, because they could be passed as an array.
     *
     * @param  mixed  Labels ara array or string
     * @return string Normalized labels string "Label1:Label2..."
     */
    public static function normalizeLabelsToString($labelsMixed)
    {
        return is_array($labelsMixed) ? self::labelsToString($labelsMixed) : $labelsMixed;
    }

    /**
     * Normalize a string colons notation by removing the first colon.
     *
     * @param string
     * @return string
     */
    public static function trimColons($str)
    {
        return trim($str, static::COLON);
    }

    /**
     * Joins and array if string statements with commas.
     *
     * @param array
     * @return string
     */
    public static function joinWithCommas(array $strings = array())
    {
        return implode(', ', $strings);
    }

    /**
     * Create a cypher property/value pairs pattern inside brackets such as "{id:5, name:'test'}".
     *
     * @param  array  Values indexed by properties
     * @return string Property pattern, or null if no properties
     */
    public static function newPropertiesPattern(array $properties = array())
    {
        if (empty($properties)) { return null; }

        $list = array();
        foreach ($properties as $prop => $val) {
            $list[] = self::newPropertyValuePair($prop, $val);
        }

        return sprintf('{%s}', implode(', ', $list));
    }

    /**
     * Add an alias with "." dot in front of property name.
     *
     * @param  string Alias
     * @param  string Property name
     * @return string Aliased property
     */
    public static function addAlias($alias, $property)
    {
        return sprintf('%s.%s', $alias, $property);
    }

    /**
     *
     */
    public function isVar($str)
    {
        return (strpos('$', $str) === 0) ? true : false;
    }

    /**
     * "$a" > "a"
     */
    public function getVarLetter($str)
    {
        return (true === self::isVar($str)) ? str_replace('$', '', $str) : false;
    }

    /**
     *
     */
    public static function getDirections($dir)
    {
        if ($dir === '->') {
            return array('-', '->');
        } else if ($dir === '<-') {
            return array('<-', '-');
        } else {
            return array('-', '-');
        }
    }

    /**
     * Removes the "MATCH" keyword from a string and trims spaces
     *
     * @param string
     * @return string
     */
    public function removeMatchKeyword($str)
    {
        return trim(str_replace('MATCH', '', $str));
    }

    /**
     * Create a property/value pair notation string and escapes/quotes the value "prop: val".
     *
     * @param  string Property
     * @param  mixed Value
     * @return string
     */
    public static function newPropertyValuePair($property, $value)
    {
        if (is_numeric($value)) {
            $value = (int) $value;
        } else {
            $value = static::quote($value);
        }

        return sprintf('%s: %s', $property, $value);
    }

    /**
     * Create a property/value equality notation string and escapes/quotes the value "prop = val".
     *
     * @param  string Property
     * @param  mixed Value
     * @return string
     */
    public static function newPropertyValueEquality($property, $value)
    {
        if (is_numeric($value)) {
            $value = (int) $value;
        } else {
            $value = static::quote($value);
        }

        return sprintf('%s = %s', $property, $value);
    }

    /**
     * Escapes single quotes (double quotes are allowed) and quotes the whole string.
     *
     * @param  string Raw string
     * @return string Quoted and escaped string
     */
    public static function quote($str)
    {
        return "'".addslashes($str)."'";
    }

    /**
     * Implode a string with an option of the implode separator given by the
     * first item of the array value, the second one beeing the actual value.
     *
     * @param array Of 2 dimentions
     * @return string
     */
    public static function subvalImplode(array $array)
    {
        $string = '';
        foreach($array as $key => $value) {
            $string .= $value[0].$value[1];
        }

        return trim(trim(trim($string, ',')), ',');
    }

    /**
     * Add a return statement to a query string.
     *
     * @param string
     * @return string
     */
    public static function addReturnStatement($stmt)
    {
        return 'RETURN'.static::SPACE.$stmt;
    }
}
