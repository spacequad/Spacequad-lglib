<?php
/**
*   Functions to parse names into components.
*   Supports full names, with or without prefixes, initials or suffixes
*   Based on php-name-parser <http://code.google.com/p/php-name-parser/>
*   by Josh Fraser.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @author     Josh Fraser <joshfraz@gmail.com>
*   @copyright  Copyright (c) 2012 Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2012 <joshfraz@gmail.com>
*   @package    lglib
*   @version    0.0.1
*   @license    http://opensource.org/licenses/Apache-2.0
*               Apache License, Version 2.0
*   @filesource
*/

define('NAME_TRIM_STR', " (),\t\n\r\0\x0B");

/**
*   Class to parse names into componentes.
*   @package lglib
*/
class NameParser
{
    /**
    *   Split full names into the following parts:
    *       prefix / salutation  (Mr., Mrs., etc)
    *       given name / first name
    *       middle initial
    *       surname / last name
    *       suffix (II, Phd, Jr, etc)
    *
    *   @uses   self::isSalutation()
    *   @uses   self::isSuffix()
    *   @uses   self::isCompoundLName()
    *   @uses   self::isInitial()
    *   @uses   self::FixCase()
    *   @param  string  $full_name  Full name to check
    *   @return array   Array of name components
    */
    public static function Parse($full_name)
    {
        static $name = array();

        // If this full name has already been parsed, just return it.
        $array_idx = md5($full_name);
        if (isset($name[$array_idx])) return $name[$array_idx];

        $name_parts = array();
        $lname = '';
        $fname = '';
        $initials = '';
        $suffix = '';
        $salutation = '';
        $nickname = '';

        // Trim the original string
        $full_name = trim($full_name, NAME_TRIM_STR);

        // Extract a nickname "Joe (Bob) Smith". If a parentheses set is found,
        // set the $nickname value and remove it from the string.
        // The first occurance is assumed to be a nickname.
        $regex = '/\(([^)]+)\)/';
        $status = preg_match_all($regex, $full_name, $matches);
        if ($status > 0) {
            // At least one match. Grab the first as the nickname. All matches
            // are removed from the full_name
            $nickname = $matches[0][0];
            $full_name = preg_replace($regex, '', $full_name);
        }

        // remove redundant whitespace
        $full_name = preg_replace('/\s+/', ' ', $full_name);

        // split name into parts
        $name_parts = explode(' ', $full_name);
        $num_words = count($name_parts);

        // Is the first word a title? (Mr. Mrs, etc)?
        $salutation = self::isSalutation($name_parts[0]);
        // Is the last word a suffix? (Dr., Jr., Senior, etc.)
        $suffix = self::isSuffix($name_parts[sizeof($name_parts)-1]);

        // Set the range for the middle part (skip prefixes & suffixes)
        $start = ($salutation) ? 1 : 0;
        $end = ($suffix) ? $num_words - 1 : $num_words;

        // concat the first name
        for ($i = $start; $i < $end - 1; $i++) {
            $word = $name_parts[$i];

            // Move on to parsing the last name if we find an indicator
            // of a compound last name (Von, Van, etc).
            // We use $i != $start to allow for rare cases where an indicator
            // is actually the first name (like "Van Morrison")
            if (self::isCompoundLName($word) && $i != $start)
                break;

            // is it a middle initial or part of their first name?
            // if we start off with an initial, we'll call it the first name
            if (self::isInitial($word)) {
                // is the initial the first word?  
                if ($i == $start) {
                    // If so, do a look-ahead to see if they go by their
                    // middle name. For ex: "R. Jason Smith" => "Jason Smith"
                    // & "R." is stored as an initial, but "R. J. Smith" =>
                    // "R. Smith" and "J." is stored as an initial
                    if (self::isInitial($name_parts[$i+1])) {
                        // If the next word is an initial, consider this the
                        // first name and the next will be the middle initial.
                        $fname .= ' ' . strtoupper($word);
                    } else {
                        // Add the next word to the initial for the first name.
                        // Increment $i to skip the extra word added
                        //$initials .= ' ' . strtoupper($word);
                        $fname .= ' ' . strtoupper($word) . ' ' .
                                $name_parts[$i+1];
                        $i++;   
                    }

                } else {
                    // otherwise, just go ahead and save the initial
                    $initials .= ' ' . strtoupper($word);
                }
            } else {
                $fname .= ' ' . self::FixCase($word);
            }  
        }
        // check that we have more than 1 word in our string
        if (($end - $start) > 1) {
            // concat the last name
            for ($i; $i < $end; $i++) {
                $lname .= ' ' . self::FixCase($name_parts[$i]);
            }
        } else {
            // otherwise, single word strings are assumed to be first names
            $fname = self::FixCase($name_parts[$i]);
        }

        // return the various parts in an array
        $name[$array_idx] = array(
            'salutation'    => $salutation,
            'fname'         => trim($fname, NAME_TRIM_STR),
            'initials'      => trim($initials, NAME_TRIM_STR),
            'lname'         => trim($lname, NAME_TRIM_STR),
            'suffix'        => $suffix,
            'nickname'      => trim($nickname, NAME_TRIM_STR)
        );
        return $name[$array_idx];
    }

    /**
    *   Detect and format standard salutations
    *
    *   @param  string  $word   Word to check
    *   @return mixed   Salutation, or False if $word is not a salutation
    */
    private static function isSalutation($word)
    {
        // ignore periods
        $word = str_replace('.', '', strtolower($word));

        // returns normalized values
        switch ($word) {
        case 'mr':  case 'master':  case 'mister':
            $val = 'Mr.';
            break;
        case 'mrs':
            $val = 'Mrs.';
            break;
        case 'miss':    case 'ms':
            $val = 'Ms.';
            break;
        case 'dr':
            $val = 'Dr.';
            break;
        case 'rev':
            $val = 'Rev.';
            break;
        case 'fr':
            $val = 'Fr.';
            break;
        default:
            $val = false;
            break;
        }
        return $val;
    }

    /**
    *   Detect and format common suffixes
    *
    *   @param  string  $word   Word to check
    *   @return mixed   Suffix, or False if $word is not a suffix
    */
    private static function isSuffix($word)
    {
        global $LANG_LGLIB;

        // ignore periods
        $word = strtolower(str_replace('.', '', $word));
        foreach ($LANG_LGLIB['nameparser_suffixes'] as $suffix) {
            if (strtolower($suffix) == $word)
                return $suffix;
        }
        return false;
    }

    /**
    *   Detect compound last names like "Von Fange"
    *
    *   @param  string  $word   Word to check (Von, Van, etc.)
    *   @return mixed   False if $word is not compound, nonzero if it is
    */
    private static function isCompoundLName($word)
    {
        global $LANG_LGLIB;

        $word = strtolower($word);
        return array_search($word, $LANG_LGLIB['nameparser_compound']);
    }


    /**
    *   Detect single letter, possibly followed by a period
    *
    *   @param  string  $word   Word to check
    *   @return boolean     True if $word is an initial, False if not
    */
    private static function isInitial($word)
    {
        return ((strlen($word) == 1) ||
            (strlen($word) == 2 && $word{1} == '.'));
    }

    /**
    *   Detect mixed case words like "McDonald"
    *
    *   @param  string  $word   Word to check   
    *   @return boolean False if the string is all one case, True if mixed
    */
    private static function isCamelCase($word)
    {
        if (preg_match("|[A-Z]+|s", $word) && preg_match("|[a-z]+|s", $word))
            return true;
        else 
            return false;
    }

    /**
    *   Upper-case first words split by dashes or periods, 
    *   but leave camelcase words alone
    *
    *   @uses   self::SafeUCFirst()
    *   @param  string  $word   Word to be modified
    *   @return string  Converted word
    */
    private static function FixCase($word)
    {
        // uppercase words split by dashes, like "Kimura-Fay"
        $word = self::SafeUCFirst('-', $word);

        // uppercase words split by periods, like "J.P."
        $word = self::SafeUCFirst('.', $word);

        return $word;
    }

    /**
    *   Helper function for FixCase.
    *   Convert words to proper case, handling words separated by a character.
    *   Do not convert CamelCase words
    *
    *   @uses   self::isCamelCase()
    *   @param  string  $word   Word to be converted
    *   @return string  Converted word
    */
    private static function SafeUCFirst($separator, $word)
    {
        // Split the words by the separator and upper-case each one if not
        // CamelCase
        $parts = explode($separator, $word);
        foreach ($parts as $word) {
            $words[] = (self::isCamelCase($word)) ?
                    $word : ucfirst(strtolower($word));
        }
        // Put the string back together again
        return implode($separator, $words);
    }


    /**
    *   Get "lastname, firstname" for a given full name.
    *
    *   @author Lee Garner <lee@leegarner.com>
    *   @uses   self::Parse
    *   @param  string  $full_name  Full name to parse
    *   @param  boolean $initial    True to append the initial, False to omit
    *   @return string      Lastname, Firstname (or just Firstname)
    */
    public static function LCF($full_name, $initial = false)
    {
        $retval = '';
        $name_parts = self::Parse($full_name);
        if (empty($name_parts['lname'])) {  // one name, like "Cher"
            $retval = $name_parts['fname'];
        } else {
            $retval = $name_parts['lname'];
            if (!empty($name_parts['fname'])) { // shouldn't be empty
                $retval .= ', ' . $name_parts['fname'];
            }
        }

        if ($initial && !empty($name_parts['initials'])) {
            $retval .= ' ' . $name_parts['initials'];
        }

        return $retval;
    }

    /**
    *   Get "Lastname, Firstname Initial", e.g. "Public, John Q."
    *
    *   @author Lee Garner <lee@leegarner.com>
    *   @uses   self::LCF()
    *   @param  string  $full_name  Full name to parse
    *   @return string  Formatted full name
    */
    public static function LCFI($full_name)
    {
        return self::LCF($full_name, true);
    }

    /**
    *   Get just the first name.
    *
    *   @author Lee Garner <lee@leegarner.com>
    *   @uses   self::Parse
    *   @param  string  $full_name  Full name to parse
    *   @return string              First name
    */
    public static function F($full_name)
    {
        $parts = self::Parse($full_name);
        return $parts['fname'];
    }

    /**
    *   Get just the last name.
    *   Returns an empty string if this is a single-name person ("Cher")
    *
    *   @author Lee Garner <lee@leegarner.com>
    *   @uses   self::Parse
    *   @param  string  $full_name  Full name to parse
    *   @return string              Last name
    */
    public static function L($full_name)
    {
        $parts = self::Parse($full_name);
        return $parts['lname'];
    }

    /**
    *   Get the first and last name, e.g. "John Public" for "John Q. Public"
    *
    *   @author Lee Garner <lee@leegarner.com>
    *   @uses   self::Parse
    *   @param  string  $full_name  Full name to parse
    *   @param  boolean $initial    True to append the initial, False to omit
    *   @return string              First and Last name
    */
    public static function FL($full_name, $initial=false)
    {
        $parts = self::Parse($full_name);
        $retval = $parts['fname'];
        if ($initial && !empty($parts['initials']))
            $retval .= ' ' . $parts['initials'];
        if (!empty($parts['lname'])) $retval .= ' ' . $parts['lname'];
        return $retval;
    }

    /**
    *   Get the firstname, initial, lastname
    *
    *   @author Lee Garner <lee@leegarner.com>
    *   @uses   self::FL()
    *   @param  string  $full_name  Full name to parse
    *   @return string      "firstname initial lastname"
    */
    public static function FIL($full_name)
    {
        return self::FL($full_name, true);
    }

}
?>
