<?php
namespace Bow\Support;

/**
 * Class Str
 *
 * @author Franck Dakia <dakiafranck@gmail.com>
 * @package Bow\Support
 */
class Str
{
    /**
     * upper case
     *
     * @param string $str
     * @return array|string
     */
    public static function upper($str)
    {
        if (is_string($str)) {
            $str = mb_strtoupper($str, "UTF-8");
        }

        return $str;
    }

    /**
     * lower case
     *
     * @param string $str
     * @return array|string
     */
    public static function lower($str)
    {
        if (is_string($str)) {
            $str = mb_strtolower($str, "UTF-8");
        }

        return $str;
    }

    /**
     * slice
     *
     * @param string $str
     * @param $start
     * @param null $end
     * @return string
     */
    public static function slice($str, $start, $end = null)
    {
        $sliceStr = "";

        if (is_string($str)) {
            if ($end === null) {
                $end = static::len($str);
            }

            if ($start < $end) {
                $sliceStr = mb_substr($str, $start, $end, "UTF-8");
            }
        }

        return $sliceStr;
    }

    /**
     * split
     *
     * @param string $pattern
     * @param string $str
     * @param null $limit
     *
     * @return array
     */
    public static function split($pattern, $str, $limit = null)
    {
        return mb_split($pattern, $str, $limit);
    }

    /**
     * match
     *
     * @param string $pattern
     * @param string $str
     * @param array $match
     *
     * @throws \ErrorException
     *
     * @return int
     */
    public static function match($pattern, $str, & $match = null)
    {
        if (static::slice($pattern, 0, 1) !== static::slice($pattern, static::len($pattern) - 1, 1)) {
            throw new \ErrorException("$pattern not valide");
        }

        return preg_match($pattern, $str, $match);
    }

    /**
     * @param $search
     * @param $str
     *
     * @return int
     */
    public static function pos($search, $str)
    {
        return mb_strpos($search, $str, null, "UTF-8");
    }

    /**
     * @param $search
     * @param $str
     *
     * @return bool
     */
    public static function contains($search, $str)
    {
        if ($search === $str) {
            return true;
        } else {
            if (-1 !== static::pos($search, $str)) {
                return true;
            }
        }

        return false;
    }

    /**
     * replace
     *
     * @param $pattern
     * @param $replaceBy
     * @param $str
     *
     * @return string
     */
    public static function replace($pattern, $replaceBy, $str)
    {
        return str_replace($pattern, $replaceBy, $str);
    }

    /**
     * capitalize
     *
     * @param $str
     *
     * @return string
     */
    public static function capitalize($str)
    {
        return ucwords($str);
    }

    /**
     * len, retourne la taille d'une chaine.
     *
     * @param $str
     *
     * @return int
     */
    public static function len($str)
    {
        return mb_strlen($str, "UTF-8");
    }

    /**
     * wordify
     *
     * @param $str
     * @param $sep
     *
     * @return array
     */
    public static function wordify($str, $sep = " ")
    {
        return static::split($sep, $str, static::count($sep, $str));
    }

    /**
     * repeat, réperte la chaine de caractère dans une nombre déterminé
     *
     * @param $str
     * @param $number
     *
     * @return string
     */
    public static function repeat($str, $number)
    {
        return str_repeat($str, $number);
    }

    /**
     * randomize
     *
     * @param int $size
     *
     * @return string
     */
    public static function randomize($size = 16)
    {
        return static::slice(str_shuffle('#*$@abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ012356789'), 0, $size);
    }

    /**
     * slugify créateur de slug en utilisant un chaine simple.
     * eg: "je suis un chaine de caractere" => "je-suis-un-chaine-de-caractere"
     * @param string $str
     *
     * @return string
     */
    public static function slugify($str)
    {
        return preg_replace("/[^a-z0-9]/", "-", strtolower(trim(strip_tags($str))));
    }

    /**
     * unslugify créateur de slug en utilisant un chaine simple.
     *
     * @param string $str
     *
     * @return string
     */
    public static function unSlugify($str)
    {
        return preg_replace("/[^a-z0-9]/", " ", strtolower(trim(strip_tags($str))));
    }

    /**
     * Vérifier si le mail est un mail valide.
     *
     * eg: dakiafranck@gmail.com => true
     *
     * @param string $email
     *
     * @throws \ErrorException
     *
     * @return int
     */
    public static function isMail($email)
    {
        if (!is_string($email)) {
            throw new \ErrorException("accept string " . gettype($email) . " given");
        }

        return static::match("/^[a-zA-Z0-9._-]@[a-z0-9._-]{2,}\.[a-z]{2,6}$/", $email);
    }

    /**
     * Vérifie si la chaine est un domaine
     *
     * eg: http://exemple.com => true
     * eg: http:/exemple.com => false
     *
     * @param string $domain
     *
     * @throws \ErrorException
     *
     * @return int
     */
    public static function isDomain($domain)
    {
        if (!is_string($domain)) {
            throw new \ErrorException("Accept string " . gettype($domain) . " given");
        }

        return static::match("/^((http|ftp|ssl|url):\/\/)[a-zA-Z0-9_-.]+\.[a-z]{2,6}$/", $domain);
    }

    /**
     * Retourne le nombre caractère dans une chaine.
     *
     * @param string $pattern
     * @param string $str
     *
     * @return int
     */
    public static function count($pattern, $str)
    {
        $c = 0;

        for($i = 0, $len = static::len($str); $i < $len; $i++) {
            if ($str[$i] == $pattern) {
                $c++;
            }
        }

        return $c;
    }

    /**
     * retourne un nombre détermine de mots dans une chaine de caractère.
     *
     * @param string $words
     * @param int $len
     *
     * @return string
     */
    public static function getNWords($words, $len)
    {
        $wordParts = explode(" ", $words);
        $sentence = "";

        foreach($wordParts as $word) {
            $sentence .= " " . $word;
        }

        return trim($sentence);
    }
}