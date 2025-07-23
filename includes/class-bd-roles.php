<?php
if (! defined('ABSPATH')) exit;
class BD_Roles
{
    public function __construct()
    {
        add_action('init', [$this, 'add_roles']);
    }
    public function add_roles()
    {
        add_role('company_admin', 'Company Admin', ['read' => true]);
        add_role('employee', 'Employee', ['read' => true]);
    }
}
