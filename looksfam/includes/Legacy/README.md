# ⚠️ LEGACY CODE DIRECTORY - DO NOT USE IN NEW DEVELOPMENT

## Status: DEPRECATED
All files in this directory contain obsolete code relying on WordPress `wp_posts` and `wp_postmeta` tables. 
They have been moved here to preserve data migration capabilities but **MUST NOT** be loaded in the new architecture.

## Files Contained
- `class-admin-class.php` - Legacy class management (uses post meta)
- `class-admin-enroll.php` - Legacy enrollment logic (uses post meta)
- `class-admin-exam.php` - Legacy exam builder (uses post meta)
- `class-admin-question.php` - Legacy question bank (uses post meta)
- `class-display-activity.php` - Legacy activity display
- `class-display-confirmation.php` - Legacy confirmation page
- `class-display-exam.php` - Legacy exam interface
- `class-display-exercise.php` - Legacy exercise mode
- `class-display-functions-v2.php` - Legacy helper functions
- `class-display-functions.php` - Legacy helper functions v1
- `class-display-review.php` - Legacy review interface
- `class-display-user.php` - Legacy user dashboard
- `class-payment.php` - Legacy payment processing
- `class-post.php` - Legacy post type registrations

## Migration Status
✅ **Write Path**: Intercepted by `Write_Interceptors.php`. New data goes to custom tables.
✅ **Read Path**: Handled by `Core/Database.php` with lazy migration fallback.
⚠️ **Loading**: These files are NO LONGER auto-loaded by the main plugin file.

## Removal Plan
1. Run `bin/force-migrate-all.php` to pre-migrate all existing data.
2. Verify site functionality for 2 weeks.
3. Delete this entire directory.

## Emergency Rollback
If critical issues arise, these files can be temporarily re-included in `wordpress-plugin-template.php` to revert to the old system while maintaining data integrity via the migration script.
