<?php
// includes/class-bd-student-controller.php
if (! defined('ABSPATH')) {
    exit;
}

class BD_Student_Controller
{
    private $errors   = [];
    private $messages = [];

    /**
     * Creates a local WP user, hits the custom-register endpoint,
     * and sends a welcome email.
     */
    public function InviteIndividualStudent()
    {
        $this->errors   = [];
        $this->messages = [];

        parse_str(wp_unslash($_POST['inputs']), $formData);

        // Validate username, email, password
        if (empty($formData['username'])) {
            $this->errors['username'] = 'Username is required.';
        }
        if (empty($formData['email']) || ! filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
            $this->errors['email'] = 'Please enter a valid email address.';
        }
        // elseif (email_exists($formData['email'])) {
        //     $this->errors['email'] = 'This email address is already in use.';
        // }
        if (empty($formData['password'])) {
            $this->errors['password'] = 'Password is required.';
        } elseif (
            strlen($formData['password']) < 6 ||
            ! preg_match('/[A-Z]/', $formData['password']) ||
            ! preg_match('/[^a-zA-Z0-9]/', $formData['password'])
        ) {
            $this->errors['password'] = 'Password must be at least 6 characters, include an uppercase letter & special character.';
        }

        if ($this->errors) {
            return ['success' => false, 'errors' => $this->errors];
        }

        // // Create WP user
        // $user_id = wp_insert_user([
        //     'user_login' => sanitize_user($formData['username']),
        //     'user_email' => sanitize_email($formData['email']),
        //     'user_pass'  => $formData['password'],
        //     'role'       => 'employee',
        // ]);
        // if (is_wp_error($user_id)) {
        //     return ['success' => false, 'errors' => ['username' => $user_id->get_error_message()]];
        // }

        // 1) Call custom-register endpoint on parent site
        $activation_key = md5(uniqid(mt_rand(), true));
        $payload = [
            'email'           => $formData['email'],
            'user_id_parent'  => $user_id,
            'password'        => $formData['password'],
            'client_id'       => get_option('client_id'),
            'secret_key'      => get_option('secret_key'),
            'activation_key'  => $activation_key,
            'user_status'     => 1,
        ];
        $url = 'https://course-dashboard.com/custom-register/';
        $res = wp_remote_post($url, [
            'method'  => 'POST',
            'body'    => $payload,
            'timeout' => 45,
        ]);
        if (is_wp_error($res)) {
            $this->messages[] = 'Could not notify course-dashboard: ' . $res->get_error_message();
        } else {
            $this->messages[] = 'Registered on course-dashboard.com';
        }

        // 2) Send notification email
        $sent = $this->SendLearnerNotification(
            $formData['email'],
            $formData['username'],
            $formData['password']
        );
        if ($sent) {
            $this->messages[] = 'Welcome email sent.';
        } else {
            $this->messages[] = 'Failed to send welcome email.';
        }

        $this->messages[] = 'Employee account created locally.';
        return ['success' => true, 'messages' => $this->messages];
    }

    /**
     * Build & send the HTML email.
     */
    private function SendLearnerNotification($to, $name, $password)
    {
        $subject = 'Your New Account on Course Dashboard';
        $logo    = esc_url(get_field('main_logo', 'option'));
        $login_url = esc_url(home_url());

        $message  = '<html><body style="font-family:Arial,sans-serif">';
        $message .= '<div style="max-width:500px;margin:auto;padding:20px;background:#fff">';
        $message .= '<img src="' . $logo . '" style="max-width:100px;margin-bottom:20px">';
        $message .= "<h2>Welcome, {$name}!</h2>";
        $message .= '<p>Your account has been created. Here are your login details:</p>';
        $message .= '<ul>';
        $message .= "<li><strong>Username:</strong> {$name}</li>";
        $message .= "<li><strong>Password:</strong> {$password}</li>";
        $message .= '</ul>';
        $message .= '<p><a href="' . $login_url . '" style="display:inline-block;padding:10px 20px;background:#0073AA;color:#fff;text-decoration:none;border-radius:4px">Log In</a></p>';
        $message .= '<p>If you have any questions, contact support.</p>';
        $message .= '</div></body></html>';

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        return wp_mail($to, $subject, $message, $headers);
    }

    /**
     * AJAX: Register one employee.
     */
    public function RegisterIndividualEmployee()
    {
        // Build form inputs for InviteIndividualStudent()
        $learner_id = intval($_POST['learner'] ?? 0);
        $user = get_userdata($learner_id);
        if (! $user) {
            wp_send_json_error(['errors' => ['learner' => 'Invalid employee']]);
        }
        // Build $_POST['inputs']
        $_POST['inputs'] = http_build_query([
            'username' => $user->user_login,
            'email'    => $user->user_email,
            'password' => wp_generate_password(),
        ]);

        $out = $this->InviteIndividualStudent();
        if ($out['success']) {
            wp_send_json_success(['messages' => $out['messages']]);
        } else {
            wp_send_json_error(['errors' => $out['errors']]);
        }
    }

    /**
     * AJAX: Register multiple employees in bulk.
     */
    public function RegisterBulkEmployees()
    {
        $learners = $_POST['learners'] ?? [];
        if (! is_array($learners) || empty($learners)) {
            wp_send_json_error(['errors' => ['learners' => 'No employees selected']]);
        }
        $all_msgs = [];
        foreach ($learners as $uid) {
            $user = get_userdata(intval($uid));
            if (! $user) {
                $all_msgs[] = "User #{$uid} skipped.";
                continue;
            }
            $_POST['inputs'] = http_build_query([
                'username' => $user->user_login,
                'email'    => $user->user_email,
                'password' => wp_generate_password(),
            ]);
            $out = $this->InviteIndividualStudent();
            if ($out['success']) {
                $all_msgs = array_merge($all_msgs, $out['messages']);
            } else {
                $all_msgs[] = implode('; ', $out['errors']);
            }
        }
        wp_send_json_success(['messages' => $all_msgs]);
    }

    /**
     * Assign a single course to one employee (via AJAX).
     */
    public function AssignStudent()
    {
        // Initialize error array
        $errs = [];

        // Parse inputs
        parse_str(wp_unslash($_POST['inputs']), $f);

        // Validation
        if (empty($f['learner']) || ! get_user_by('ID', intval($f['learner']))) {
            $errs['learner'] = 'Invalid employee';
        }
        if (empty($f['course'])) {
            $errs['course'] = 'Select course';
        }
        if (empty($f['duration_days']) || intval($f['duration_days']) < 1) {
            $errs['duration_days'] = 'Enter a valid duration';
        }
        if ($errs) {
            wp_send_json_error(['errors' => $errs]);
        }

        global $wpdb;
        $course_id     = intval($f['course']);
        $duration_days = intval($f['duration_days']);
        $ptc_table     = $wpdb->prefix . 'ptc_items';

        // Fetch product_id
        $prod = $wpdb->get_var($wpdb->prepare(
            "SELECT product_id FROM {$ptc_table} WHERE course_id = %d",
            $course_id
        ));
        if (! $prod) {
            wp_send_json_error(['errors' => ['course' => 'Not linked']]);
        }

        // Prepare payload
        $user    = get_userdata(intval($f['learner']));
        $payload = [
            'email'         => $user->user_email,
            'course_id'     => $course_id,
            'product_id'    => intval($prod),
            'site_url'      => get_site_url(),
            'client_id'     => get_option('client_id'),
            'secret_key'    => get_option('secret_key'),
            'automated'     => 0,
            'duration_days' => $duration_days,
        ];

        // Call REST endpoint
        $res = wp_remote_post(
            'https://course-dashboard.com/wp-json/custom-api/v1/assign-course',
            [
                'headers' => ['Content-Type' => 'application/json'],
                'body'    => wp_json_encode($payload),
                'timeout' => 20,
            ]
        );
        if (is_wp_error($res)) {
            wp_send_json_error(['errors' => ['course' => $res->get_error_message()]]);
        }

        $body = json_decode(wp_remote_retrieve_body($res), true);
        if (empty($body['success'])) {
            wp_send_json_error(['errors' => $body['errors'] ?? ['course' => 'Assignment failed']]);
        }

        wp_send_json_success(['messages' => [$body['message']]]);
    }

    /**
     * Assign ALL courses in a category to many employees.
     */
    public function AssignCategory()
    {
        // Initialize
        $errors   = [];
        $messages = [];

        // Gather & validate inputs
        $category   = sanitize_text_field($_POST['category'] ?? '');
        $duration   = intval($_POST['duration_days'] ?? 0);
        $learners   = $_POST['learners'] ?? [];

        if ($duration < 1) {
            $errors['duration_days'] = 'Enter a valid duration';
        }
        if (! $category) {
            $errors['category'] = 'Select a category';
        }

        // “All employees” = empty learners[]
        if (empty($learners)) {
            $current  = get_current_user_id();
            $args     = ['role' => 'employee', 'fields' => 'ID'];
            if (! current_user_can('manage_options')) {
                $args['meta_key']   = 'company_admin_id';
                $args['meta_value'] = $current;
            }
            $users    = get_users($args);
            $learners = wp_list_pluck($users, 'ID');
        }
        if (empty($learners)) {
            $errors['learners'] = 'No employees selected';
        }
        if ($errors) {
            wp_send_json_error(['errors' => $errors]);
        }

        global $wpdb;
        $ptc_table  = $wpdb->prefix . 'ptc_items';

        // 1) Get ALL course IDs from ptc_items
        $course_ids = $wpdb->get_col("SELECT DISTINCT course_id FROM {$ptc_table}");
        if (empty($course_ids)) {
            wp_send_json_error(['errors' => ['category' => 'No courses found in import table.']]);
        }

        // 2) Fetch courses in the chosen category via batch API
        $endpoint = 'https://course-dashboard.com/wp-json/custom/v1/connected-courses-batch-size/';
        $batch_payload = [
            'course_ids' => array_map('intval', $course_ids),
            'page'       => 1,
            'per_page'   => count($course_ids),
            'type'       => 'general',
            'category'   => $category,
        ];
        $batch_res = wp_remote_post($endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . get_option('client_id') . ':' . get_option('secret_key'),
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode($batch_payload),
            'timeout' => 20,
        ]);
        if (is_wp_error($batch_res)) {
            wp_send_json_error(['errors' => ['category' => $batch_res->get_error_message()]]);
        }
        $batch_data = json_decode(wp_remote_retrieve_body($batch_res), true);
        if (empty($batch_data['courses'])) {
            wp_send_json_error(['errors' => ['category' => 'No courses in this category']]);
        }

        // 3) Loop users & courses, calling the same REST endpoint you use in AssignStudent()
        foreach ($learners as $uid) {
            $user = get_userdata(intval($uid));
            if (! $user) {
                $messages[] = "User #{$uid} not found, skipped.";
                continue;
            }

            foreach ($batch_data['courses'] as $course) {
                $cid   = intval($course['id']);
                $prod  = $wpdb->get_var($wpdb->prepare(
                    "SELECT product_id FROM {$ptc_table} WHERE course_id = %d",
                    $cid
                ));

                // Build the same payload
                $payload = [
                    'email'         => $user->user_email,
                    'course_id'     => $cid,
                    'product_id'    => intval($prod),
                    'site_url'      => get_site_url(),
                    'client_id'     => get_option('client_id'),
                    'secret_key'    => get_option('secret_key'),
                    'automated'     => 0,
                    'duration_days' => $duration,
                ];

                // Call REST assign endpoint
                $res = wp_remote_post(
                    'https://course-dashboard.com/wp-json/custom-api/v1/assign-course',
                    [
                        'headers' => ['Content-Type' => 'application/json'],
                        'body'    => wp_json_encode($payload),
                        'timeout' => 20,
                    ]
                );
                if (is_wp_error($res)) {
                    $messages[] = "Error assigning course {$cid} to {$user->display_name}: " . $res->get_error_message();
                    continue;
                }
                $body = json_decode(wp_remote_retrieve_body($res), true);
                if (empty($body['success'])) {
                    $err_text = $body['errors']
                        ? implode(', ', (array)$body['errors'])
                        : 'Unknown error';
                    $messages[] = "Failed: {$user->display_name} → course {$cid}: {$err_text}";
                } else {
                    $messages[] = "{$user->display_name} → {$body['course_title']} assigned.";
                }
            }
        }

        wp_send_json_success(['messages' => $messages]);
    }
}
// AJAX hooks for registration
add_action('wp_ajax_bd_register_individual', [new BD_Student_Controller(), 'RegisterIndividualEmployee']);
add_action('wp_ajax_bd_register_bulk',       [new BD_Student_Controller(), 'RegisterBulkEmployees']);
