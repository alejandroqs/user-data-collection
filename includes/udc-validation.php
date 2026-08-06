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
        return strlen((string) $value) <= $limit;
    }
}
