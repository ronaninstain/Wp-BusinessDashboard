<?php
// includes/class-bd-register-employees.php
if (! defined('ABSPATH')) exit;

class BD_Register_Employees
{

    public function register_page()
    {
        add_submenu_page(
            'business-dashboard',
            'Register Employees',
            'Register Employees',
            'manage_options',
            'bd-register-employees',
            [$this, 'page_html']
        );
    }

    public function page_html()
    {
        if (! current_user_can('manage_options') && ! current_user_can('company_admin')) {
            wp_die('Insufficient permissions.');
        }

        // Fetch employees who are not yet registered on course-dashboard.com:
        // (you might track a user_meta flag once registered; for demo, list all employees)
        $current = get_current_user_id();
        $args = ['role' => 'employee', 'orderby' => 'display_name'];
        if (! current_user_can('manage_options')) {
            $args['meta_key']   = 'company_admin_id';
            $args['meta_value'] = $current;
        }
        $employees = get_users($args);
?>
        <div class="wrap">
            <h1>Register Employees to Course Dashboard</h1>

            <h2>Individual Registration</h2>
            <form id="bd_register_individual" action="javascript:void(0)" style="max-width:400px;">
                <table class="form-table">
                    <tr>
                        <th><label for="ind_learner">Employee</label></th>
                        <td>
                            <select name="learner" id="ind_learner" required>
                                <option value="">— Select Employee —</option>
                                <?php foreach ($employees as $e) : ?>
                                    <option value="<?php echo esc_attr($e->ID); ?>">
                                        <?php echo esc_html($e->display_name . ' (' . $e->user_email . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback"></div>
                        </td>
                    </tr>
                </table>
                <p>
                    <button type="submit" class="button button-primary bd-register-btn">
                        <span class="indicator-label">Register Employee</span>
                        <span class="indicator-progress" style="display:none;">Processing…</span>
                    </button>
                </p>
                <div class="success-feedback" style="display:none;margin-top:1em;padding:10px;border:1px solid #4CAF50;background:#DFF0D8;"></div>
            </form>

            <hr>

            <h2>Bulk Registration</h2>
            <form id="bd_register_bulk" action="javascript:void(0)" style="max-width:600px;">
                <p>Select one or more employees:</p>
                <select name="learners[]" id="bulk_learners" multiple size="6" style="width:100%;max-width:400px;">
                    <?php foreach ($employees as $e) : ?>
                        <option value="<?php echo esc_attr($e->ID); ?>">
                            <?php echo esc_html($e->display_name . ' (' . $e->user_email . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="invalid-feedback"></div>
                <p>
                    <button type="submit" class="button button-primary bd-register-bulk-btn">
                        <span class="indicator-label">Register Selected</span>
                        <span class="indicator-progress" style="display:none;">Processing…</span>
                    </button>
                </p>
                <div class="success-feedback" style="display:none;margin-top:1em;padding:10px;border:1px solid #4CAF50;background:#DFF0D8;"></div>
            </form>
        </div>
<?php
    }
}
