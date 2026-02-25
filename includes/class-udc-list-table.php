<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class UDC_List_Table extends WP_List_Table
{
    public function __construct()
    {
        parent::__construct([
            'singular' => __('Submission', 'user-data-collection'),
            'plural' => __('Submissions', 'user-data-collection'),
            'ajax' => false
        ]);
    }

    public function get_columns()
    {
        return [
            'id' => __('ID', 'user-data-collection'),
            'last_name' => __('Last Name', 'user-data-collection'),
            'first_name' => __('First Name', 'user-data-collection'),
            'dob' => __('Date of Birth', 'user-data-collection'),
            'phone' => __('Phone', 'user-data-collection'),
            'appointment_date' => __('Appt Date', 'user-data-collection'),
            'appointment_time' => __('Appt Time', 'user-data-collection'),
            'created_at' => __('Sub. Date', 'user-data-collection'),
            'is_confirmed' => __('Status', 'user-data-collection'),
            'action' => __('Action', 'user-data-collection'),
        ];
    }

    protected function get_sortable_columns()
    {
        return [
            'id' => ['id', false],
            'last_name' => ['last_name', false],
            'first_name' => ['first_name', false],
            'dob' => ['dob', false],
            'appointment_date' => ['appointment_date', false],
            'created_at' => ['created_at', false],
            'is_confirmed' => ['is_confirmed', false]
        ];
    }

    protected function get_views()
    {
        $views = [];
        $current = isset($_REQUEST['appt_filter']) ? sanitize_text_field(wp_unslash($_REQUEST['appt_filter'])) : 'upcoming';

        // Count for upcoming
        global $wpdb;
        $table_name = $wpdb->prefix . 'udc_submissions';
        $today = current_time('Y-m-d');

        $upcoming_count = $wpdb->get_var("SELECT COUNT(id) FROM $table_name WHERE appointment_date >= '$today'");
        $past_count = $wpdb->get_var("SELECT COUNT(id) FROM $table_name WHERE appointment_date < '$today'");

        $class = ($current == 'upcoming') ? ' class="current"' : '';
        $upcoming_url = add_query_arg('appt_filter', 'upcoming');
        $views['upcoming'] = "<a href='{$upcoming_url}' {$class}>" . __('Upcoming', 'user-data-collection') . " <span class='count'>({$upcoming_count})</span></a>";

        $class = ($current == 'past') ? ' class="current"' : '';
        $past_url = add_query_arg('appt_filter', 'past');
        $views['past'] = "<a href='{$past_url}' {$class}>" . __('Past', 'user-data-collection') . " <span class='count'>({$past_count})</span></a>";

        return $views;
    }

    public function prepare_items()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'udc_submissions';

        $per_page = 20;

        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = [$columns, $hidden, $sortable];

        $filter = isset($_REQUEST['appt_filter']) ? sanitize_text_field(wp_unslash($_REQUEST['appt_filter'])) : 'upcoming';
        $today = current_time('Y-m-d');

        $where = "";
        if ($filter === 'upcoming') {
            $where = "WHERE appointment_date >= '$today'";
            $default_orderby = 'appointment_date';
            $default_order = 'ASC';
        } else {
            $where = "WHERE appointment_date < '$today'";
            $default_orderby = 'created_at';
            $default_order = 'DESC';
        }

        $orderby = isset($_GET['orderby']) ? sanitize_text_field(wp_unslash($_GET['orderby'])) : $default_orderby;
        $order = isset($_GET['order']) && strtolower(sanitize_text_field(wp_unslash($_GET['order']))) === 'asc' ? 'ASC' : (isset($_GET['order']) && strtolower(sanitize_text_field(wp_unslash($_GET['order']))) === 'desc' ? 'DESC' : $default_order);

        $valid_columns = array_keys($this->get_sortable_columns());
        if (!in_array($orderby, $valid_columns)) {
            $orderby = $default_orderby;
        }

        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;

        $total_items = $wpdb->get_var("SELECT COUNT(id) FROM $table_name $where");

        $sql = "SELECT * FROM $table_name $where";
        $sql .= " ORDER BY " . esc_sql($orderby) . " " . esc_sql($order);
        $sql .= " LIMIT %d OFFSET %d";

        $this->items = $wpdb->get_results($wpdb->prepare($sql, $per_page, $offset));

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);
    }

    public function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'created_at':
                return esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item->$column_name)));
            default:
                return isset($item->$column_name) ? esc_html($item->$column_name) : '';
        }
    }

    public function column_last_name($item)
    {
        $view_url = add_query_arg(['page' => 'udc-submissions', 'action' => 'view', 'id' => $item->id], admin_url('admin.php'));

        $actions = [
            'view' => sprintf('<a href="%s">%s</a>', esc_url($view_url), esc_html__('View Details', 'user-data-collection')),
        ];

        return sprintf('%1$s %2$s', esc_html($item->last_name), $this->row_actions($actions));
    }

    public function column_action($item)
    {
        if ($item->is_confirmed) {
            return sprintf(
                '<button type="button" class="button button-secondary udc-unconfirm-btn" data-id="%s" data-nonce="%s">%s</button>',
                esc_attr($item->id),
                esc_attr(wp_create_nonce('udc_unconfirm_nonce_' . $item->id)),
                esc_html__('Unconfirm', 'user-data-collection')
            );
        } else {
            return sprintf(
                '<button type="button" class="button button-primary udc-confirm-btn" data-id="%s" data-nonce="%s">%s</button>',
                esc_attr($item->id),
                esc_attr(wp_create_nonce('udc_confirm_nonce_' . $item->id)),
                esc_html__('Confirm', 'user-data-collection')
            );
        }
    }

    public function column_is_confirmed($item)
    {
        $out = '<div class="status-cell">';
        if ($item->is_confirmed) {
            $out .= '<span style="color: green; font-weight: bold;">' . esc_html__('Confirmed', 'user-data-collection') . '</span>';
        } else {
            $out .= '<span style="color: orange; font-weight: bold;">' . esc_html__('Pending', 'user-data-collection') . '</span>';
        }
        $out .= '</div>';
        return $out;
    }

    public function single_row($item)
    {
        echo '<tr id="submission-row-' . esc_attr($item->id) . '">';
        $this->single_row_columns($item);
        echo '</tr>';
    }
}
