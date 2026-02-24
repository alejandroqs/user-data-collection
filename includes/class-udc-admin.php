<?php
if (!defined('ABSPATH')) {
    exit;
}

class UDC_Admin
{

    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_admin_menu']);
    }

    public function add_admin_menu()
    {
        add_menu_page(
            'Submissions',
            'Submissions',
            'manage_options',
            'udc-submissions',
            [$this, 'render_admin_page'],
            'dashicons-feedback',
            30
        );
    }

    public function render_admin_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'udc_submissions';

        // Fetch all submissions securely
        $submissions = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">User Submissions</h1>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Last Name</th>
                        <th>First Name</th>
                        <th>Date of Birth</th>
                        <th>Phone</th>
                        <th>Appt Date</th>
                        <th>Appt Time</th>
                        <th>Sub. Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($submissions): ?>
                        <?php foreach ($submissions as $row): ?>
                            <tr id="submission-row-<?php echo esc_attr($row->id); ?>">
                                <td>
                                    <?php echo esc_html($row->id); ?>
                                </td>
                                <td>
                                    <?php echo esc_html($row->last_name); ?>
                                </td>
                                <td>
                                    <?php echo esc_html($row->first_name); ?>
                                </td>
                                <td>
                                    <?php echo esc_html($row->dob); ?>
                                </td>
                                <td>
                                    <?php echo esc_html($row->phone); ?>
                                </td>
                                <td>
                                    <?php echo esc_html($row->appointment_date); ?>
                                </td>
                                <td>
                                    <?php echo esc_html($row->appointment_time); ?>
                                </td>
                                <td>
                                    <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($row->created_at))); ?>
                                </td>
                                <td class="status-cell">
                                    <?php if ($row->is_confirmed): ?>
                                        <span style="color: green; font-weight: bold;">Confirmed</span>
                                    <?php else: ?>
                                        <span style="color: orange; font-weight: bold;">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!$row->is_confirmed): ?>
                                        <button class="button button-primary udc-confirm-btn" data-id="<?php echo esc_attr($row->id); ?>"
                                            data-nonce="<?php echo esc_attr(wp_create_nonce('udc_confirm_nonce_' . $row->id)); ?>">
                                            Confirm
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10">No submissions found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const buttons = document.querySelectorAll('.udc-confirm-btn');

                buttons.forEach(button => {
                    button.addEventListener('click', function (e) {
                        e.preventDefault();

                        const btn = this;
                        const id = btn.getAttribute('data-id');
                        const nonce = btn.getAttribute('data-nonce');

                        btn.disabled = true;
                        btn.innerText = 'Confirming...';

                        // Using Fetch API for strictly modern vanilla AJAX
                        const formData = new FormData();
                        formData.append('action', 'udc_confirm_submission');
                        formData.append('submission_id', id);
                        formData.append('security', nonce);

                        fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                            method: 'POST',
                            body: formData
                        })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    // Update UI gracefully
                                    const row = document.getElementById('submission-row-' + id);
                                    const statusCell = row.querySelector('.status-cell');
                                    statusCell.innerHTML = '<span style="color: green; font-weight: bold;">Confirmed</span>';
                                    btn.remove(); // Remove button upon success
                                } else {
                                    alert(data.data || 'An error occurred.');
                                    btn.disabled = false;
                                    btn.innerText = 'Confirm';
                                }
                            })
                            .catch(error => {
                                alert('Network Error');
                                btn.disabled = false;
                                btn.innerText = 'Confirm';
                            });
                    });
                });
            });
        </script>
        <?php
    }
}
