<?php
// includes/class-bd-import.php

use PhpOffice\PhpSpreadsheet\IOFactory;

if (! defined('ABSPATH')) exit;

class BD_Import
{
    public function bulk_import_page()
    {
        if (! current_user_can('manage_options')) wp_die();

        // On submit…
        if (isset($_POST['bd_import_submit'])) {
            check_admin_referer('bd_import_users');
            $this->process_import();
        }

?>
        <div class="wrap">
            <h1>Bulk Import Users</h1>
            <p><strong>Required columns (must NOT be empty):</strong></p>
            <ul>
                <li><code>username</code> (unique WP login)</li>
                <li><code>email</code> (valid, unique)</li>
                <li><code>company_admin_email</code> (must match an existing or new admin)</li>
            </ul>
            <p>Optional columns:</p>
            <ul>
                <li><code>first_name</code></li>
                <li><code>last_name</code></li>
                <li><code>role</code> (defaults to <code>employee</code>)</li>
            </ul>

            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('bd_import_users'); ?>
                <p>
                    <label for="bd_import_file">Excel file:</label><br>
                    <input type="file" name="bd_import_file" id="bd_import_file"
                        accept=".xls,.xlsx" required>
                </p>
                <p>
                    <button class="button button-primary" name="bd_import_submit">
                        Start Import
                    </button>
                </p>
            </form>
        </div>
<?php
    }

    private function process_import()
    {
        if (empty($_FILES['bd_import_file']['tmp_name'])) {
            add_action('admin_notices', fn() => print '<div class="error"><p>No file uploaded.</p></div>');
            return;
        }
        require_once BD_PLUGIN_DIR . 'vendor/autoload.php';

        try {
            $ss   = IOFactory::load($_FILES['bd_import_file']['tmp_name']);
            $rows = $ss->getActiveSheet()->toArray();
        } catch (\Throwable $e) {
            add_action('admin_notices', fn() => printf(
                '<div class="error"><p>Load error: %s</p></div>',
                esc_html($e->getMessage())
            ));
            return;
        }

        $headers = array_map('strtolower', array_map('trim', $rows[0]));
        // Check required headers
        foreach (['username', 'email', 'company_admin_email'] as $col) {
            if (! in_array($col, $headers, true)) {
                add_action('admin_notices', fn() => printf(
                    '<div class="error"><p>Missing required column: <code>%s</code></p></div>',
                    esc_html($col)
                ));
                return;
            }
        }

        // Process rows…
        $success = $errors = 0;
        for ($i = 1; $i < count($rows); $i++) {
            $data = array_combine($headers, $rows[$i]);

            // Skip rows missing mandatory data:
            if (empty($data['username']) || empty($data['email']) || empty($data['company_admin_email'])) {
                $errors++;
                continue;
            }

            // 1) Ensure Company Admin exists (or create)
            $admin_email = sanitize_email($data['company_admin_email']);
            $admin = get_user_by('email', $admin_email);
            if (! $admin) {
                $admin_username = sanitize_user($data['username'] . '_admin');
                $admin_pass     = wp_generate_password();
                $admin_id       = wp_create_user($admin_username, $admin_pass, $admin_email);
                if (is_wp_error($admin_id)) {
                    $errors++;
                    continue;
                }
                // Set meta and role
                wp_update_user([
                    'ID'         => $admin_id,
                    'first_name' => sanitize_text_field($data['first_name'] ?? ''),
                    'last_name'  => sanitize_text_field($data['last_name'] ?? ''),
                ]);
                $u = new WP_User($admin_id);
                $u->set_role('company_admin');
                $admin = get_user_by('ID', $admin_id);
            }

            // 2) Create the Employee
            if (username_exists($data['username']) || email_exists($data['email'])) {
                $errors++;
                continue;
            }
            $emp_pass = wp_generate_password();
            $emp_id   = wp_create_user(
                sanitize_user($data['username']),
                $emp_pass,
                sanitize_email($data['email'])
            );
            if (is_wp_error($emp_id)) {
                $errors++;
                continue;
            }
            // Set names, role, and link to admin
            wp_update_user([
                'ID'         => $emp_id,
                'first_name' => sanitize_text_field($data['first_name'] ?? ''),
                'last_name'  => sanitize_text_field($data['last_name'] ?? ''),
            ]);
            $wu = new WP_User($emp_id);
            $wu->set_role($data['role'] ?? 'employee');
            update_user_meta($emp_id, 'company_admin_id', $admin->ID);

            $success++;
        }

        add_action('admin_notices', function () use ($success, $errors) {
            printf(
                '<div class="updated"><p>Import complete: %d employees created, %d rows skipped/errors.</p></div>',
                $success,
                $errors
            );
        });
    }
}
