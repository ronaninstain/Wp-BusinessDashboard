<?php
// includes/class-bd-manage-companies.php
if (! defined('ABSPATH')) exit;

class BD_Manage_Companies
{

    public function manage_companies_page()
    {
        // Prevent any stray whitespace/output
        ob_start();

        if (! current_user_can('manage_options') && ! current_user_can('company_admin')) {
            wp_die('Insufficient permissions.');
        }

        global $wpdb;
        $current = get_current_user_id();

        // 1) Employees
        $args = ['role' => 'employee', 'orderby' => 'display_name'];
        if (! current_user_can('manage_options')) {
            $args['meta_key']   = 'company_admin_id';
            $args['meta_value'] = $current;
        }
        $employees = get_users($args);

        // 2) Course IDs for single-course assign
        $course_ids = $wpdb->get_col("SELECT DISTINCT course_id FROM {$wpdb->prefix}ptc_items");
        $single_courses = $this->fetch_courses_by_ids($course_ids);

        // 3) Categories
        $categories = $this->fetch_categories();

        // Start output
?>
        <div class="wrap">
            <h1>Manage Companies & Employees</h1>

            <!-- Individual Course Assignment -->
            <h2>Assign Single Course</h2>
            <form id="assign_course_form">
                <table class="form-table">
                    <tr>
                        <th><label for="learner">Employee</label></th>
                        <td>
                            <select name="learner" id="learner" required>
                                <option value="">— Select Employee —</option>
                                <?php foreach ($employees as $emp) : ?>
                                    <option value="<?php echo esc_attr($emp->ID); ?>">
                                        <?php echo esc_html($emp->display_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback"></div>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="course">Course</label></th>
                        <td>
                            <select name="course" id="course" required>
                                <option value="">— Select Course —</option>
                                <?php if ($single_courses): ?>
                                    <?php foreach ($single_courses as $id => $title): ?>
                                        <option value="<?php echo esc_attr($id); ?>">
                                            <?php echo esc_html($title); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="">No courses found</option>
                                <?php endif; ?>
                            </select>
                            <div class="invalid-feedback"></div>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="duration_days">Duration (days)</label></th>
                        <td>
                            <input type="number" name="duration_days" id="duration_days" value="365" min="1" required style="width:80px;">
                            <div class="invalid-feedback"></div>
                        </td>
                    </tr>
                </table>
                <p>
                    <button type="submit" class="button button-primary assign-course-btn">
                        <span class="indicator-label">Assign Course</span>
                        <span class="indicator-progress" style="display:none;">Processing…</span>
                    </button>
                </p>
                <div class="success-feedback" style="display:none;margin-top:1em;padding:10px;border:1px solid #4CAF50;background:#DFF0D8;color:#3C763D;"></div>
            </form>

            <hr>

            <!-- Category Assignment -->
            <h2>Assign Category to Employees</h2>
            <form id="assign_category_form">
                <table class="form-table">
                    <tr>
                        <th><label for="cat_category">Category</label></th>
                        <td>
                            <select name="category" id="cat_category" required>
                                <option value="">— Select Category —</option>
                                <?php foreach ($categories as $slug => $label): ?>
                                    <option value="<?php echo esc_attr($slug); ?>">
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback"></div>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Employees</label></th>
                        <td>
                            <label><input type="checkbox" id="cat_all_emps" checked> All employees</label><br>
                            <select name="learners[]" id="cat_employees" multiple size="5" style="min-width:200px;">
                                <?php foreach ($employees as $e): ?>
                                    <option value="<?php echo esc_attr($e->ID); ?>">
                                        <?php echo esc_html($e->display_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="description">Hold Ctrl/Cmd to select multiple.</div>
                            <div class="invalid-feedback"></div>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="cat_duration">Duration (days)</label></th>
                        <td>
                            <input type="number" name="duration_days" id="cat_duration" value="365" min="1" required style="width:80px;">
                            <div class="invalid-feedback"></div>
                        </td>
                    </tr>
                </table>
                <p>
                    <button type="submit" class="button button-primary assign-category-btn">
                        <span class="indicator-label">Assign Category</span>
                        <span class="indicator-progress" style="display:none;">Processing…</span>
                    </button>
                </p>
                <div class="success-feedback" style="display:none;margin-top:1em;padding:10px;border:1px solid #4CAF50;background:#DFF0D8;color:#3C763D;"></div>
            </form>
        </div>
<?php

        echo ob_get_clean();
    }

    /**
     * Helper: fetch specific courses by IDs
     */
    private function fetch_courses_by_ids(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        $endpoint = 'https://course-dashboard.com/wp-json/custom/v1/connected-courses-batch-size/';
        $body = [
            'course_ids' => array_map('intval', $ids),
            'page'       => 1,
            'per_page'   => count($ids),
            'type'       => 'general',
            'category'   => ''
        ];
        $res = wp_remote_post($endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . get_option('client_id') . ':' . get_option('secret_key'),
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode($body),
            'timeout' => 20
        ]);
        if (is_wp_error($res)) {
            return [];
        }
        $data = json_decode(wp_remote_retrieve_body($res), true);
        if (empty($data['courses'])) {
            return [];
        }
        $out = [];
        foreach ($data['courses'] as $c) {
            $out[intval($c['id'])] = wp_strip_all_tags($c['title']);
        }
        return $out;
    }

    /**
     * Helper: fetch all categories
     */
    private function fetch_categories(): array
    {
        $endpoint = 'https://course-dashboard.com/wp-json/custom/v1/course-categories/';
        $res = wp_remote_get($endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . get_option('client_id') . ':' . get_option('secret_key'),
                'Content-Type' => 'application/json'
            ],
            'timeout' => 15
        ]);
        if (is_wp_error($res)) {
            return [];
        }
        $body = json_decode(wp_remote_retrieve_body($res), true);
        if (empty($body['data']) || ! is_array($body['data'])) {
            return [];
        }
        $cats = [];
        foreach ($body['data'] as $c) {
            $cats[sanitize_text_field($c['slug'])] = sanitize_text_field($c['name']);
        }
        return $cats;
    }
}
