📚 B2B Employee Course Management (WordPress Plugin)
A custom WordPress plugin designed to streamline employee-course assignments for B2B clients using WooCommerce and REST API integration. The plugin provides an admin interface to:

Bulk import users via Excel

Assign individual courses or course categories to employees

Register employees directly to the course dashboard

Sync employee data between client and source (parent) sites

Use secure REST API with client_id and secret_key validation

✨ Features
✅ Bulk user import via .xls or .xlsx

✅ Assign courses individually or by category

✅ AJAX-powered WordPress admin interface

✅ REST API integration with remote LMS dashboard

✅ Auto-create users if not found

✅ Validation using client_id and secret_key

✅ Admin notifications & error handling

🔗 REST API Endpoints
POST /wp-json/custom-api/v1/assign-course

POST /wp-json/custom-api/v1/assign-category

All endpoints require client_id and secret_key for authentication.

🛠️ Tech Stack
WordPress (Custom Plugin)

WooCommerce

jQuery (AJAX)

PHP 7.4+

Excel file parser (PhpSpreadsheet)

REST API (WP native)

🧪 Development Setup
Clone the repo to wp-content/plugins/

Activate the plugin via WP Admin

Add your client_id and secret_key via Settings

Ensure the source site has API routes and matching keys configured

🔒 Security Notes
All API requests are authenticated using secure tokens

Server-side validation ensures only authorized data passes through

Data sanitation is handled before DB writes
