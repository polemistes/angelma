<?php

declare(strict_types=1);

/*
 * English interface strings. Use through t('key'). Placeholders look like {name}.
 */
return [
    // Errors
    'error_404_title' => 'Page not found',
    'error_404_text' => 'The page you asked for does not exist.',
    'error_403_title' => 'Access denied',
    'error_403_text' => 'You are not allowed to do this. If you submitted a form, reload the page and try again.',
    'error_405_title' => 'Not allowed',
    'error_405_text' => 'This address does not accept that kind of request.',
    'error_500_title' => 'Something went wrong',
    'error_500_text' => 'The server could not complete the request. Please try again later.',
    'back_to_home' => 'Go to the home page',

    // Public site
    'menu_label' => 'Main menu',
    'powered_by' => 'Powered by Angelma',
    'menu_login' => 'Log in',
    'menu_logout' => 'Log out',

    // Login
    'login_title' => 'Log in',
    'login_button' => 'Log in',
    'login_invalid' => 'Wrong username or password.',
    'login_blocked' => 'Too many failed attempts. Try again in {minutes} minutes.',
    'login_welcome' => 'Welcome, {name}.',
    'logout_done' => 'You are logged out.',

    // Common form fields and validation
    'field_site_title' => 'Site title',
    'field_page_width' => 'Page width in pixels (600 to 1600)',
    'field_scheme' => 'Colour scheme',
    'field_username' => 'Username',
    'field_display_name' => 'Display name',
    'field_password' => 'Password',
    'field_password_repeat' => 'Repeat password',
    'password_hint' => 'At least {min} characters.',
    'error_page_width' => 'Enter a width between 600 and 1600.',
    'error_username' => 'Use 3 to 40 letters, digits, dots, dashes or underscores.',
    'error_display_name' => 'Enter a display name of at most 80 characters.',
    'error_password_short' => 'The password must have at least {min} characters.',
    'error_password_repeat' => 'The two passwords do not match.',

    // Colour schemes
    'scheme_paper' => 'Paper (light, warm)',
    'scheme_slate' => 'Slate (light, cool grey)',
    'scheme_night' => 'Night (dark)',
    'scheme_forest' => 'Forest (greens)',
    'scheme_ocean' => 'Ocean (blues)',
    'scheme_custom' => 'Custom',

    // Installer
    'install_title' => 'Install Angelma',
    'install_already' => 'Angelma is already installed.',
    'install_step_requirements' => 'Requirements',
    'install_step_site' => 'Site',
    'install_step_admin' => 'Administrator',
    'install_step_finish' => 'Finish',
    'install_check_php' => 'PHP version {version} or newer',
    'install_check_ext' => 'PHP extension {ext}',
    'install_check_ext_optional' => 'PHP extension {ext} (optional)',
    'install_check_writable' => 'Folder {dir} is writable',
    'install_rewrite_checking' => 'Checking URL rewriting (mod_rewrite) ...',
    'install_rewrite_ok' => 'URL rewriting (mod_rewrite) works',
    'install_rewrite_fail' => 'URL rewriting (mod_rewrite) does not seem to work. Pages other than the home page may not load. Ask your host to enable mod_rewrite and .htaccess files.',
    'install_fix_requirements' => 'Fix the items marked in red, then check again.',
    'install_check_again' => 'Check again',
    'install_continue' => 'Continue',
    'install_error_title' => 'Enter a site title of at most 100 characters.',
    'install_error_scheme' => 'Choose a colour scheme.',
    'install_finish_text' => 'Angelma will now create the database, the administrator account and a home page.',
    'install_button' => 'Install',
    'install_error_existing_db' => 'The database data/angelma.sqlite already contains users. Delete it to install again, or restore data/installed.lock.',
    'install_error_write' => 'Could not write {file}. Check the folder permissions.',
    'install_error_failed' => 'Installation failed. Details are in data/error.log.',
    'install_done_title' => 'Angelma is installed',
    'install_done_text' => 'Your site is ready. Log in to the admin area to start adding pages.',
    'install_delete_folder' => 'For safety, delete the install/ folder from your web host now.',
    'install_view_site' => 'View the site',
    'install_open_admin' => 'Open the admin area',
    'install_welcome_markdown' => "# Welcome\n\nThis is your new website, made with Angelma.\n\n[Log in](/login) to edit this page, add new pages, images and a blog.",

    // Admin frame
    'admin_suffix' => 'admin',
    'admin_nav_label' => 'Admin menu',
    'admin_logged_in_as' => 'Logged in as {name}',
    'nav_dashboard' => 'Dashboard',
    'nav_pages' => 'Pages',
    'nav_images' => 'Images',
    'nav_blogs' => 'Blogs',
    'nav_comments' => 'Comments',
    'nav_users' => 'Users',
    'nav_settings' => 'Settings',
    'nav_backup' => 'Backup',
    'nav_account' => 'Account',
    'nav_view_site' => 'View site',
    'nav_logout' => 'Log out',
    'role_admin' => 'Administrator',
    'role_editor' => 'Editor',
    'role_user' => 'User',
    'status_draft' => 'Draft',
    'status_published' => 'Published',
    'confirm_delete' => 'Delete this? This cannot be undone.',

    // Dashboard
    'dashboard_posts' => 'Posts',
    'dashboard_pending' => 'Pending comments',
    'dashboard_quick_links' => 'Quick links',
    'dashboard_new_page' => 'New page',
    'dashboard_upload_image' => 'Upload an image',
    'dashboard_write_post' => 'Write a post',
    'dashboard_recent_posts' => 'Recently edited posts',
    'dashboard_no_posts' => 'No posts yet.',
    'dashboard_pending_comments' => 'Comments waiting for approval',
    'dashboard_no_pending' => 'No comments are waiting.',
    'dashboard_on_post' => 'on "{title}"',
    'dashboard_moderate' => 'Moderate comments',
];
