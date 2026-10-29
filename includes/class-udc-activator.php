<?php
if (!defined('ABSPATH')) {
    exit;
}

class UDC_Activator
{

    public static function activate()
    {
        global $wpdb;

        if (false === has_filter('cron_schedules', ['UDC_GDrive', 'add_cron_schedule'])) {
            add_filter('cron_schedules', ['UDC_GDrive', 'add_cron_schedule']);
        }
        if (false === has_filter('cron_schedules', ['UDC_Email_Sync', 'add_cron_schedule'])) {
            add_filter('cron_schedules', ['UDC_Email_Sync', 'add_cron_schedule']);
        }

        $table_name = $wpdb->prefix . 'udc_submissions';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            last_name varchar(255) NOT NULL,
            first_name varchar(255) NOT NULL,
            dob date NOT NULL,
            minor_declared tinyint(1) DEFAULT NULL,
            phone varchar(50) NOT NULL,
            health_good tinyint(1) DEFAULT 0 NOT NULL,
            health_treatment tinyint(1) DEFAULT 0 NOT NULL,
            health_blood_thinners tinyint(1) DEFAULT 0 NOT NULL,
            health_allergies tinyint(1) DEFAULT 0 NOT NULL,
            health_pregnant tinyint(1) DEFAULT 0 NOT NULL,
            liability_accepted tinyint(1) DEFAULT 0 NOT NULL,
            appointment_date date NOT NULL,
            appointment_time time NOT NULL,
            piercing_location varchar(255) NOT NULL,
            form_type varchar(20) DEFAULT 'legacy' NOT NULL,
            piercing_category varchar(32) DEFAULT '' NOT NULL,
            form_language varchar(32) DEFAULT '' NOT NULL,
            form_version varchar(20) DEFAULT '' NOT NULL,
            form_evidence longtext NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // dbDelta does not remove columns omitted from CREATE TABLE.
        $quoted_table = '`' . str_replace('`', '``', $table_name) . '`';
        $columns = $wpdb->get_col("SHOW COLUMNS FROM $quoted_table");
        if (!is_array($columns) || empty($columns) || '' !== $wpdb->last_error) {
            error_log('UDC_SCHEMA_INSPECTION_FAILED');
            return;
        }
        if (array_diff(['minor_declared', 'form_type', 'piercing_category', 'form_language', 'form_version', 'form_evidence'], $columns)) {
            error_log('UDC_FORM_SCHEMA_UPGRADE_FAILED');
            return;
        }
        foreach (['address', 'city', 'zip'] as $retired) {
            if (in_array($retired, $columns, true) && false === $wpdb->query("ALTER TABLE $quoted_table DROP COLUMN `$retired`")) {
                error_log('UDC_ADDRESS_REMOVAL_FAILED');
                return;
            }
        }
        $remaining = $wpdb->get_col("SHOW COLUMNS FROM $quoted_table");
        if (!is_array($remaining) || '' !== $wpdb->last_error || array_intersect(['address', 'city', 'zip'], $remaining)) {
            error_log('UDC_ADDRESS_REMOVAL_FAILED');
            return;
        }
        if (in_array('is_confirmed', $columns, true)) {
            if (false === $wpdb->query("ALTER TABLE $quoted_table DROP COLUMN `is_confirmed`")) {
                error_log('UDC_CONFIRMATION_REMOVAL_FAILED');
                return;
            }
        }

        update_option('udc_db_version', UDC_DB_VERSION);

        // Schedule Backups & Syncs
        $backup_scheduled = UDC_Backup::schedule_cron();
        $gdrive_scheduled = UDC_GDrive::schedule_cron();
        $email_scheduled = UDC_Email_Sync::schedule_cron();
        if (false === $backup_scheduled || false === $gdrive_scheduled || false === $email_scheduled) {
            error_log('UDC_CRON_SCHEDULE_FAILED');
        }
    }
}
