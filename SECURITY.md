# Security Considerations for Autotask Time Entry Plugin

This document outlines important security considerations that should be addressed before deploying this plugin in a production environment.

## Current Security Limitations

For development purposes, several security measures have been disabled or simplified. These should be properly implemented before production use:

1. **GitHub Access Token Storage**
   - The current implementation stores the GitHub access token directly in a PHP file.
   - This is insecure as PHP files can potentially be accessed if server configurations are incorrect.

2. **Authentication & Authorization**
   - The plugin currently does not implement robust authentication for Autotask API access.
   - User role capabilities for plugin access are minimal.

3. **Data Validation & Sanitization**
   - Input validation and data sanitization are minimal in the development version.

4. **Error Handling**
   - Error handling is basic and may expose sensitive information in error messages.

## Required Security Improvements

Before production deployment, implement the following security measures:

### 1. Secure Credential Storage

Option A: WordPress Options API with Encryption
```php
// Store credentials
function store_encrypted_credentials($api_key) {
    $encrypted = encrypt_data($api_key); // Implement secure encryption
    update_option('autotask_api_credentials', $encrypted, true);
}

// Retrieve credentials
function get_decrypted_credentials() {
    $encrypted = get_option('autotask_api_credentials');
    return decrypt_data($encrypted); // Implement secure decryption
}
```

Option B: Store credentials outside web root
- Create a secure configuration file outside the web root directory
- Use WordPress constants in wp-config.php

```php
// In wp-config.php
define('AUTOTASK_CREDENTIALS_FILE', '/path/outside/webroot/autotask-credentials.php');

// In your plugin
if (defined('AUTOTASK_CREDENTIALS_FILE') && file_exists(AUTOTASK_CREDENTIALS_FILE)) {
    require_once AUTOTASK_CREDENTIALS_FILE;
}
```

### 2. Implement Proper Authentication

```php
// Add capability checks
function check_autotask_permissions() {
    if (!current_user_can('manage_autotask_entries')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
}

// Add custom capabilities during plugin activation
function add_autotask_capabilities() {
    $admin = get_role('administrator');
    $admin->add_cap('manage_autotask_entries');
}
```

### 3. Data Validation and Sanitization

For all data input, implement:

```php
// Sanitize text fields
$safe_input = sanitize_text_field($user_input);

// Validate numeric values
if (!is_numeric($user_input)) {
    // Handle error
}

// Use WordPress nonces for forms
wp_nonce_field('autotask_action', 'autotask_nonce');

// Verify nonces
if (!isset($_POST['autotask_nonce']) || !wp_verify_nonce($_POST['autotask_nonce'], 'autotask_action')) {
    wp_die('Security check failed');
}
```

### 4. Secure API Communication

Ensure all API calls use:
- HTTPS with certificate validation
- Authentication headers
- Rate limiting
- Data encryption where necessary

```php
// Example of secure API request
$response = wp_remote_post('https://api.autotask.net/endpoint', [
    'headers' => [
        'Authorization' => 'Bearer ' . $this->get_decrypted_credentials(),
        'Content-Type' => 'application/json'
    ],
    'body' => json_encode($data),
    'timeout' => 30,
    'sslverify' => true
]);
```

### 5. Error Handling & Logging

Implement robust error handling:

```php
try {
    // Critical operation
} catch (Exception $e) {
    // Log error securely
    $this->log_error($e->getMessage());
    
    // Show generic message to user
    return new WP_Error('api_error', __('There was an error processing your request. Please try again later.'));
}

// Secure logging function
private function log_error($message) {
    // Remove sensitive data
    $message = $this->sanitize_for_logging($message);
    
    // Log to WordPress error log
    error_log('Autotask Plugin Error: ' . $message);
}
```

## Plugin Update Security

For the auto-update functionality, implement these security measures:

1. **Integrity Verification**
   - Add package signature verification when downloading updates
   - Implement checksums for package validation

2. **Update Source Validation**
   - Verify update source before installing updates
   - Use HTTPS for all update checks

3. **Secure GitHub Integration**
   - Use GitHub webhooks with proper authentication
   - Implement token rotation for GitHub access tokens

## Timeline for Security Implementation

1. Development Phase (Current)
   - Basic functionality without full security measures

2. Pre-Beta Release
   - Implement secure credential storage
   - Add input validation and sanitization
   - Improve error handling

3. Beta Release
   - Implement proper authentication and authorization
   - Add secure logging
   - Implement HTTPS enforcement

4. Production Release
   - Complete security audit
   - Implement all remaining security measures
   - Documentation for secure deployment
