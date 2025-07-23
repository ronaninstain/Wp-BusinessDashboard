<?php
// includes/class-bd-company-admin.php

if (! defined('ABSPATH')) exit;

class BD_Company_Admin_Manager
{

    public function register_page()
    {
        add_submenu_page(
            'business-dashboard',
            'Company Admins',
            'Company Admins',
            'manage_options',
            'bd-company-admins',
            [$this, 'page_html']
        );
    }

    public function page_html()
    {
        if (! current_user_can('manage_options')) {
            wp_die('Insufficient permissions.');
        }

        // Handle form submissions:
        if (isset($_POST['bd_create_ca'])) {
            check_admin_referer('bd_create_ca');
            $this->do_create_company_admin();
        }
        if (isset($_POST['bd_create_emp'])) {
            check_admin_referer('bd_create_emp');
            $this->do_create_employee();
        }

        // Fetch existing company admins for the dropdown:
        $admins = get_users([
            'role'    => 'company_admin',
            'orderby' => 'display_name',
        ]);
?>
        <div class="wrap">
            <h1>Company Admins & Employees</h1>

            <h2>Create Company Admin</h2>
            <form method="post" style="max-width:400px;margin-bottom:2em;">
                <?php wp_nonce_field('bd_create_ca'); ?>
                <p><label>Username <span style="color:red">*</span></label><br>
                    <input name="ca_username" required class="regular-text">
                </p>
                <p><label>Email <span style="color:red">*</span></label><br>
                    <input name="ca_email" type="email" required class="regular-text">
                </p>
                <p><label>First Name</label><br>
                    <input name="ca_first_name" class="regular-text">
                </p>
                <p><label>Last Name</label><br>
                    <input name="ca_last_name" class="regular-text">
                </p>
                <p><button name="bd_create_ca" class="button button-primary">Create Company Admin</button></p>
            </form>

            <h2>Create Employee under Company Admin</h2>
            <form method="post" style="max-width:400px;margin-bottom:2em;">
                <?php wp_nonce_field('bd_create_emp'); ?>
                <p><label>Company Admin <span style="color:red">*</span></label><br>
                    <select name="emp_admin" required class="regular-text">
                        <option value="">— Select Admin —</option>
                        <?php foreach ($admins as $a) : ?>
                            <option value="<?php echo esc_attr($a->ID); ?>">
                                <?php echo esc_html($a->display_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </p>
                <p><label>Username <span style="color:red">*</span></label><br>
                    <input name="emp_username" required class="regular-text">
                </p>
                <p><label>Email <span style="color:red">*</span></label><br>
                    <input name="emp_email" type="email" required class="regular-text">
                </p>
                <p><label>First Name</label><br>
                    <input name="emp_first_name" class="regular-text">
                </p>
                <p><label>Last Name</label><br>
                    <input name="emp_last_name" class="regular-text">
                </p>
                <p><button name="bd_create_emp" class="button button-primary">Create Employee</button></p>
            </form>

            <h2>Existing Company Admins & Their Employees</h2>
            <?php if (empty($admins)) : ?>
                <p>No Company Admins found.</p>
            <?php else : ?>
                <?php foreach ($admins as $a) : ?>
                    <h3><?php echo esc_html($a->display_name); ?> (<?php echo esc_html($a->user_email); ?>)</h3>
                    <?php
                    $emps = get_users([
                        'role'       => 'employee',
                        'meta_key'   => 'company_admin_id',
                        'meta_value' => $a->ID,
                    ]);
                    ?>
                    <?php if (empty($emps)) : ?>
                        <p><em>No employees assigned</em></p>
                    <?php else : ?>
                        <ul>
                            <?php foreach ($emps as $e) : ?>
                                <li><?php echo esc_html($e->display_name); ?> &mdash; <?php echo esc_html($e->user_email); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
<?php
    }

    private function do_create_company_admin()
    {
        $u = sanitize_user($_POST['ca_username']);
        $e = sanitize_email($_POST['ca_email']);
        if (username_exists($u) || email_exists($e)) {
            add_action('admin_notices', fn() => print '<div class="error"><p>Username or Email already exists.</p></div>');
            return;
        }
        $pwd = wp_generate_password();
        $id  = wp_create_user($u, $pwd, $e);
        if (is_wp_error($id)) {
            add_action('admin_notices', fn() => print '<div class="error"><p>' . $id->get_error_message() . '</p></div>');
            return;
        }
        wp_update_user([
            'ID'         => $id,
            'first_name' => sanitize_text_field($_POST['ca_first_name']),
            'last_name'  => sanitize_text_field($_POST['ca_last_name']),
        ]);
        (new WP_User($id))->set_role('company_admin');

        add_action('admin_notices', fn() => printf(
            '<div class="updated"><p>Company Admin <strong>%s</strong> created.</p></div>',
            esc_html($u)
        ));
    }

    private function do_create_employee()
    {
        $aid = intval($_POST['emp_admin']);
        $u   = sanitize_user($_POST['emp_username']);
        $e   = sanitize_email($_POST['emp_email']);

        if (! get_user_by('id', $aid)) {
            add_action('admin_notices', fn() => print '<div class="error"><p>Invalid Company Admin.</p></div>');
            return;
        }
        if (username_exists($u) || email_exists($e)) {
            add_action('admin_notices', fn() => print '<div class="error"><p>Username or Email already exists.</p></div>');
            return;
        }

        $pwd = wp_generate_password();
        $id  = wp_create_user($u, $pwd, $e);
        if (is_wp_error($id)) {
            add_action('admin_notices', fn() => print '<div class="error"><p>' . $id->get_error_message() . '</p></div>');
            return;
        }
        wp_update_user([
            'ID'         => $id,
            'first_name' => sanitize_text_field($_POST['emp_first_name']),
            'last_name'  => sanitize_text_field($_POST['emp_last_name']),
        ]);
        (new WP_User($id))->set_role('employee');
        update_user_meta($id, 'company_admin_id', $aid);

        add_action('admin_notices', fn() => printf(
            '<div class="updated"><p>Employee <strong>%s</strong> created under Admin #%d.</p></div>',
            esc_html($u),
            $aid
        ));
    }
}
