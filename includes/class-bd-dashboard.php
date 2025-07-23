<?php
class BD_Dashboard
{
  public static function dashboard_page()
  {
    if (! current_user_can('manage_options')) {
      wp_die('You do not have sufficient permissions to access this page.');
    }
?>
    <div class="wrap">
      <h1>Business Dashboard</h1>
      <p>Welcome to the Business Dashboard. Use the links below to manage your companies and users.</p>
      <ul>
        <li>
          <a href="<?php echo esc_url(admin_url('admin.php?page=bd-bulk-import')); ?>">
            Bulk Import Users
          </a>
        </li>
        <li>
          <a href="<?php echo esc_url(admin_url('admin.php?page=bd-manage-companies')); ?>">
            Manage Companies
          </a>
        </li>
      </ul>
    </div>
<?php
  }
}
