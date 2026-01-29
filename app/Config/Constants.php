<?php

/*
 | --------------------------------------------------------------------
 | App Namespace
 | --------------------------------------------------------------------
 |
 | This defines the default Namespace that is used throughout
 | CodeIgniter to refer to the Application directory. Change
 | this constant to change the namespace that all application
 | classes should use.
 |
 | NOTE: changing this will require manually modifying the
 | existing namespaces of App\* namespaced-classes.
 */
defined('APP_NAMESPACE') || define('APP_NAMESPACE', 'App');

/*
 | --------------------------------------------------------------------------
 | Composer Path
 | --------------------------------------------------------------------------
 |
 | The path that Composer's autoload file is expected to live. By default,
 | the vendor folder is in the Root directory, but you can customize that here.
 */
defined('COMPOSER_PATH') || define('COMPOSER_PATH', ROOTPATH . 'vendor/autoload.php');

/*
 |--------------------------------------------------------------------------
 | Timing Constants
 |--------------------------------------------------------------------------
 |
 | Provide simple ways to work with the myriad of PHP functions that
 | require information to be in seconds.
 */
defined('SECOND') || define('SECOND', 1);
defined('MINUTE') || define('MINUTE', 60);
defined('HOUR')   || define('HOUR', 3600);
defined('DAY')    || define('DAY', 86400);
defined('WEEK')   || define('WEEK', 604800);
defined('MONTH')  || define('MONTH', 2_592_000);
defined('YEAR')   || define('YEAR', 31_536_000);
defined('DECADE') || define('DECADE', 315_360_000);

/*
 | --------------------------------------------------------------------------
 | Exit Status Codes
 | --------------------------------------------------------------------------
 |
 | Used to indicate the conditions under which the script is exit()ing.
 | While there is no universal standard for error codes, there are some
 | broad conventions.  Three such conventions are mentioned below, for
 | those who wish to make use of them.  The CodeIgniter defaults were
 | chosen for the least overlap with these conventions, while still
 | leaving room for others to be defined in future versions and user
 | applications.
 |
 | The three main conventions used for determining exit status codes
 | are as follows:
 |
 |    Standard C/C++ Library (stdlibc):
 |       http://www.gnu.org/software/libc/manual/html_node/Exit-Status.html
 |       (This link also contains other GNU-specific conventions)
 |    BSD sysexits.h:
 |       http://www.gsp.com/cgi-bin/man.cgi?section=3&topic=sysexits
 |    Bash scripting:
 |       http://tldp.org/LDP/abs/html/exitcodes.html
 |
 */
defined('EXIT_SUCCESS')        || define('EXIT_SUCCESS', 0);        // no errors
defined('EXIT_ERROR')          || define('EXIT_ERROR', 1);          // generic error
defined('EXIT_CONFIG')         || define('EXIT_CONFIG', 3);         // configuration error
defined('EXIT_UNKNOWN_FILE')   || define('EXIT_UNKNOWN_FILE', 4);   // file not found
defined('EXIT_UNKNOWN_CLASS')  || define('EXIT_UNKNOWN_CLASS', 5);  // unknown class
defined('EXIT_UNKNOWN_METHOD') || define('EXIT_UNKNOWN_METHOD', 6); // unknown class member
defined('EXIT_USER_INPUT')     || define('EXIT_USER_INPUT', 7);     // invalid user input
defined('EXIT_DATABASE')       || define('EXIT_DATABASE', 8);       // database error
defined('EXIT__AUTO_MIN')      || define('EXIT__AUTO_MIN', 9);      // lowest automatically-assigned error code
defined('EXIT__AUTO_MAX')      || define('EXIT__AUTO_MAX', 125);    // highest automatically-assigned error code
/*
 |--------------------------------------------------------------------------
 | WebSchedulr Application Constants
 |--------------------------------------------------------------------------
 |
 | Application-specific constants for WebSchedulr. These replace magic strings
 | throughout the codebase for better maintainability and IDE support.
 |
 */

// User Roles
defined('ROLE_ADMIN')    || define('ROLE_ADMIN', 'admin');
defined('ROLE_PROVIDER') || define('ROLE_PROVIDER', 'provider');
defined('ROLE_STAFF')    || define('ROLE_STAFF', 'staff');
defined('ROLE_CUSTOMER') || define('ROLE_CUSTOMER', 'customer');

// Appointment Status
defined('APPOINTMENT_PENDING')   || define('APPOINTMENT_PENDING', 'pending');
defined('APPOINTMENT_CONFIRMED') || define('APPOINTMENT_CONFIRMED', 'confirmed');
defined('APPOINTMENT_COMPLETED') || define('APPOINTMENT_COMPLETED', 'completed');
defined('APPOINTMENT_CANCELLED') || define('APPOINTMENT_CANCELLED', 'cancelled');
defined('APPOINTMENT_NO_SHOW')   || define('APPOINTMENT_NO_SHOW', 'no_show');

// Notification Channels
defined('NOTIFICATION_EMAIL')    || define('NOTIFICATION_EMAIL', 'email');
defined('NOTIFICATION_SMS')      || define('NOTIFICATION_SMS', 'sms');
defined('NOTIFICATION_WHATSAPP') || define('NOTIFICATION_WHATSAPP', 'whatsapp');
defined('NOTIFICATION_PUSH')     || define('NOTIFICATION_PUSH', 'push');

// Notification Status
defined('NOTIFICATION_PENDING')   || define('NOTIFICATION_PENDING', 'pending');
defined('NOTIFICATION_SENT')      || define('NOTIFICATION_SENT', 'sent');
defined('NOTIFICATION_FAILED')    || define('NOTIFICATION_FAILED', 'failed');
defined('NOTIFICATION_DELIVERED') || define('NOTIFICATION_DELIVERED', 'delivered');

// Payment Status
defined('PAYMENT_PENDING')   || define('PAYMENT_PENDING', 'pending');
defined('PAYMENT_COMPLETED') || define('PAYMENT_COMPLETED', 'completed');
defined('PAYMENT_FAILED')    || define('PAYMENT_FAILED', 'failed');
defined('PAYMENT_REFUNDED')  || define('PAYMENT_REFUNDED', 'refunded');

// Booking Status
defined('BOOKING_DRAFT')     || define('BOOKING_DRAFT', 'draft');
defined('BOOKING_PENDING')   || define('BOOKING_PENDING', 'pending');
defined('BOOKING_CONFIRMED') || define('BOOKING_CONFIRMED', 'confirmed');
defined('BOOKING_CANCELLED') || define('BOOKING_CANCELLED', 'cancelled');

// Service Status
defined('SERVICE_ACTIVE')   || define('SERVICE_ACTIVE', 'active');
defined('SERVICE_INACTIVE') || define('SERVICE_INACTIVE', 'inactive');
defined('SERVICE_ARCHIVED') || define('SERVICE_ARCHIVED', 'archived');

// Provider Status
defined('PROVIDER_ACTIVE')   || define('PROVIDER_ACTIVE', 'active');
defined('PROVIDER_INACTIVE') || define('PROVIDER_INACTIVE', 'inactive');
defined('PROVIDER_SUSPENDED') || define('PROVIDER_SUSPENDED', 'suspended');

// SMS Providers
defined('SMS_PROVIDER_TWILIO')   || define('SMS_PROVIDER_TWILIO', 'twilio');
defined('SMS_PROVIDER_VONAGE')   || define('SMS_PROVIDER_VONAGE', 'vonage');
defined('SMS_PROVIDER_MESSAGEBIRD') || define('SMS_PROVIDER_MESSAGEBIRD', 'messagebird');

// WhatsApp Providers
defined('WHATSAPP_PROVIDER_META')      || define('WHATSAPP_PROVIDER_META', 'meta_cloud');
defined('WHATSAPP_PROVIDER_TWILIO')    || define('WHATSAPP_PROVIDER_TWILIO', 'twilio');
defined('WHATSAPP_PROVIDER_LINK')      || define('WHATSAPP_PROVIDER_LINK', 'link_generator');

// Setup Status
defined('SETUP_INCOMPLETE') || define('SETUP_INCOMPLETE', 'incomplete');
defined('SETUP_COMPLETE')   || define('SETUP_COMPLETE', 'complete');

// Table Prefixes (for reference)
defined('TABLE_PREFIX') || define('TABLE_PREFIX', 'xs_');