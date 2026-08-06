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
            address varchar(255) NOT NULL,
            city varchar(255) NOT NULL,
            zip varchar(50) NOT NULL,
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
            is_confirmed tinyint(1) DEFAULT 0 NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

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
