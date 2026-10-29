<?php
if (!defined('ABSPATH')) {
    exit;
}

final class UDC_Validation
{
    public static function valid_date($value)
    {
        $value = (string) $value;
        $date = DateTime::createFromFormat('!Y-m-d', $value);
        $errors = DateTime::getLastErrors();
        return $date && $date->format('Y-m-d') === $value && (false === $errors || (0 === $errors['warning_count'] && 0 === $errors['error_count']));
    }

    /** Calendar years; a February 29 birthday reaches its anniversary on March 1 in non-leap years. */
    public static function is_minor($dob, $today)
    {
        if (!self::valid_date($dob) || !self::valid_date($today) || $dob > $today) { return false; }
        $age = (int) substr($today, 0, 4) - (int) substr($dob, 0, 4);
        if (substr($today, 5) < substr($dob, 5)) { --$age; }
        return $age < 18;
    }

    public static function valid_datetime($value)
    {
        $value = (string) $value;
        $date = DateTime::createFromFormat('!Y-m-d H:i:s', $value);
        $errors = DateTime::getLastErrors();
        return $date && $date->format('Y-m-d H:i:s') === $value && (false === $errors || (0 === $errors['warning_count'] && 0 === $errors['error_count']));
    }

    public static function normalize_time($value)
    {
        $value = (string) $value;
        if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value)) {
            return $value . ':00';
        }
        return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d$/', $value) ? $value : false;
    }

    public static function within_length($value, $limit)
    {
        return preg_match('/^.{0,' . (int) $limit . '}$/usD', (string) $value) === 1;
    }
}
