# CVE Fix Analysis: Commit 01421cff3b2b754eff083fd3abd4fd70b5cd7387

## Fix Summary

**Commit:** `01421cff3b2b754eff083fd3abd4fd70b5cd7387`  
**Date:** April 8, 2025  
**CVE:** https://wpscan.com/vulnerability/b90d6420-0de1-42cd-aee2-2473a757a320  
**Type:** PHP Object Injection / Unsafe Deserialization

---

## The Core Fix: `evf_maybe_unserialize()` Function

The fix introduces a **safe wrapper function** that prevents PHP Object Injection:

### New Function Added (`includes/evf-core-functions.php`)

```php
if ( ! function_exists( 'evf_maybe_unserialize' ) ) {
    /**
     * EVF Unserialize data.
     *
     * @param string $data Data that might be unserialized.
     * @param array  $options Options.
     *
     * @return mixed Unserialized data can be any type.
     *
     * @since 3.1.2
     */
    function evf_maybe_unserialize( $data, $options = array() ) {

        if ( is_serialized( $data ) ) {
            if ( version_compare( PHP_VERSION, '7.1.0', '>=' ) ) {
                // KEY FIX: 'allowed_classes' => false prevents object instantiation!
                $options = wp_parse_args( $options, array( 'allowed_classes' => false ) );
                return @unserialize( trim( $data ), $options );
            }
            // For PHP < 7.1, still allows object injection (fallback)
            return @unserialize( trim( $data ) );
        }

        return $data;
    }
}
```

### Why This Works

| PHP Version | Behavior |
|-------------|----------|
| **PHP 7.1+** | Uses `allowed_classes => false` - Objects are converted to `__PHP_Incomplete_Class` (safe) |
| **PHP < 7.1** | Still vulnerable (no `allowed_classes` option available) |

The `allowed_classes => false` option tells PHP:
- **DO NOT** instantiate any objects during deserialization
- Convert serialized objects to `__PHP_Incomplete_Class` instead
- Magic methods (`__wakeup()`, `__destruct()`) will **NOT** execute

---

## All Changes Summary

### 18 Files Modified

| File | Change |
|------|--------|
| `includes/evf-core-functions.php` | Added `evf_maybe_unserialize()` function |
| `includes/admin/views/html-admin-page-entries-view.php` | ⭐ **Critical fix** - The main trigger point |
| `includes/admin/class-evf-admin-entries-table-list.php` | Replaced `maybe_unserialize()` |
| `includes/admin/class-evf-admin-entries.php` | Replaced `maybe_unserialize()` |
| `includes/abstracts/class-evf-form-fields.php` | Replaced `maybe_unserialize()` |
| `includes/abstracts/class-evf-session.php` | Replaced `maybe_unserialize()` |
| `includes/class-evf-ajax.php` | Replaced `maybe_unserialize()` |
| `includes/class-evf-form-task.php` | Replaced `maybe_unserialize()` |
| `includes/export/class-evf-entry-csv-exporter.php` | Replaced `maybe_unserialize()` |
| `includes/fields/class-evf-field-checkbox.php` | Replaced `maybe_unserialize()` |
| `includes/fields/class-evf-field-country.php` | Replaced `maybe_unserialize()` |
| `includes/fields/class-evf-field-radio.php` | Replaced `maybe_unserialize()` |
| `includes/fields/class-evf-field-rating.php` | Replaced `maybe_unserialize()` |
| `includes/fields/class-evf-field-wysiwyg.php` | Replaced `maybe_unserialize()` (2 places) |
| `includes/class-evf-template-loader.php` | Added input sanitization + escaping |
| `templates/form-preview/evf-form-preview-template.php` | Added capability check + sanitization |

---

## Detailed Analysis of Key Fixes

### 1. Main Vulnerability Point Fixed

**File:** `includes/admin/views/html-admin-page-entries-view.php`

```diff
- $field_value = maybe_unserialize( $field_value );
+ $field_value = evf_maybe_unserialize( $field_value );
```

**Before (Vulnerable):**
```php
if ( is_serialized( $field_value ) ) {
    $field_value = maybe_unserialize( $field_value );  // Objects instantiated!
```

**After (Fixed):**
```php
if ( is_serialized( $field_value ) ) {
    $field_value = evf_maybe_unserialize( $field_value );  // Objects blocked!
```

### 2. Entries Table List Fixed

**File:** `includes/admin/class-evf-admin-entries-table-list.php`

```diff
- $field_value = maybe_unserialize( $value );
+ $field_value = evf_maybe_unserialize( $value );
```

### 3. Session Handler Fixed

**File:** `includes/abstracts/class-evf-session.php`

```diff
- return isset( $this->_data[ $key ] ) ? maybe_unserialize( $this->_data[ $key ] ) : $default;
+ return isset( $this->_data[ $key ] ) ? evf_maybe_unserialize( $this->_data[ $key ] ) : $default;
```

### 4. AJAX Handler Fixed

**File:** `includes/class-evf-ajax.php`

```diff
- $booked_slot = maybe_unserialize( get_option( 'evf_booked_slot', '' ) );
+ $booked_slot = evf_maybe_unserialize( get_option( 'evf_booked_slot', '' ) );
```

### 5. CSV Exporter Fixed

**File:** `includes/export/class-evf-entry-csv-exporter.php`

```diff
- $given_answer = maybe_unserialize( $given_answer )['label'];
+ $given_answer = evf_maybe_unserialize( $given_answer )['label'];
```

### 6. All Field Types Fixed

| Field Type | File |
|------------|------|
| Checkbox | `class-evf-field-checkbox.php` |
| Radio | `class-evf-field-radio.php` |
| Country | `class-evf-field-country.php` |
| Rating | `class-evf-field-rating.php` |
| WYSIWYG | `class-evf-field-wysiwyg.php` |

---

## Additional Security Fixes in Same Commit

### 1. Form Preview Capability Check

**File:** `templates/form-preview/evf-form-preview-template.php`

```php
// NEW: Added capability check
if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'everest_forms_view_forms' ) ) {
    echo '<div style="...">';
    echo __( "You don't have permission to view this page.", 'everest-forms' );
    echo '</div>';
    exit;
}
```

### 2. Input Sanitization & Escaping

**File:** `includes/class-evf-template-loader.php`

```diff
- $form_id = $_GET['form_id'];
+ $form_id = absint( $_GET['form_id'] );

- $html .= get_the_title( $form_id );
+ $html .= esc_html( get_the_title( $form_id ) );

- $html .= '... data-id="' . $_GET['form_id'] . '">';
+ $html .= '... data-id="' . absint( $_GET['form_id'] ) . '">';
```

---

## 🚨 CRITICAL: Incomplete Fix - Vulnerability Still Present!

### The Fix MISSED the Primary Vulnerability!

The security fix commit `01421cff` only fixed **ONE of TWO** vulnerable `unserialize()` calls in `html-admin-page-entries-view.php`.

#### Timeline Analysis:

| Commit | Action | Line 107/134 (raw unserialize) | Line 147/194 (maybe_unserialize) |
|--------|--------|-------------------------------|----------------------------------|
| `83f525f2` | Introduced vulnerability | ❌ `unserialize()` added | ❌ `maybe_unserialize()` added |
| `01421cff` | Security fix | ❌ **NOT FIXED!** | ✅ Fixed to `evf_maybe_unserialize()` |
| Current | Still vulnerable | ❌ **STILL VULNERABLE** | ✅ Safe |

### Proof: Before and After the Fix

**BEFORE Fix (`01421cff^`):**
```
Line 107: $raw_meta_val = unserialize( $meta_value );     // VULNERABLE
Line 147: $field_value = maybe_unserialize( $field_value ); // VULNERABLE
```

**AFTER Fix (`01421cff`):**
```
Line 107: $raw_meta_val = unserialize( $meta_value );       // STILL VULNERABLE! ❌
Line 147: $field_value = evf_maybe_unserialize( $field_value ); // FIXED ✅
```

**CURRENT Version (HEAD):**
```
Line 134: $raw_meta_val = unserialize( $meta_value );       // STILL VULNERABLE! ❌
Line 194: $field_value = evf_maybe_unserialize( $field_value ); // Safe ✅
```

### Why This Matters

The **primary trigger point** (line 134) that performs raw object deserialization was **never fixed**!

```php
// File: includes/admin/views/html-admin-page-entries-view.php
// Line 134 - ACTIVELY EXPLOITABLE!

if ( is_serialized( $meta_value ) ) {
    // This raw unserialize() instantiates ANY PHP class!
    $raw_meta_val = unserialize( $meta_value ); // phpcs:ignore
    
    // Magic methods __wakeup(), __destruct() WILL EXECUTE
    // POP chain attacks are POSSIBLE
}
```

### Additional Gap: PHP < 7.1 Fallback

The `evf_maybe_unserialize()` function still has a vulnerable fallback:

```php
if ( version_compare( PHP_VERSION, '7.1.0', '>=' ) ) {
    // Safe - uses allowed_classes => false
} else {
    return @unserialize( trim( $data ) ); // STILL VULNERABLE on old PHP!
}
```

---

## Attack Surface Analysis

### Before Fix:
```
Entry Import → Database Storage → Admin Views Entry → unserialize() → POP Chain ❌
                                                    → maybe_unserialize() → POP Chain ❌
```

### After "Fix" (INCOMPLETE!):
```
Entry Import → Database Storage → Admin Views Entry 
                                        ↓
                        ┌───────────────┴───────────────┐
                        ↓                               ↓
              Line 134 (raw)                    Line 194 (safe)
              unserialize()                    evf_maybe_unserialize()
                   ↓                                    ↓
              POP Chain ❌                    allowed_classes => false ✅
              STILL EXPLOITABLE!              Objects blocked safely
```

### Exploitation Path (Still Works!)

```
1. Attacker imports malicious CSV with serialized payload
2. Data stored in wp_evf_entrymeta table
3. Admin navigates to view entry page
4. Line 134 executes: unserialize($meta_value)
5. PHP instantiates the malicious object
6. __wakeup() or __destruct() executes
7. POP chain leads to code execution
```

---

## Testing the Fix

### Test Case 1: Verify Fix Works

```php
<?php
// Simulate the fix behavior

// Malicious payload
$payload = 'O:21:"SimpleFileWriteGadget":2:{s:4:"file";s:26:"/tmp/evf_poc_marker.txt";s:7:"content";s:21:"POP chain triggered!";}';

// BEFORE FIX - Would instantiate object
$result_unsafe = unserialize($payload);
// Result: Object created, __destruct() WILL execute

// AFTER FIX - Object blocked
$result_safe = unserialize($payload, ['allowed_classes' => false]);
// Result: __PHP_Incomplete_Class object, __destruct() will NOT execute

var_dump($result_safe);
// Output: object(__PHP_Incomplete_Class)#1 (3) { ... }
```

### Test Case 2: Verify Remaining Vulnerability (Line 134)

```php
// The fix missed line 134 in html-admin-page-entries-view.php
// This is still vulnerable:

$meta_value = 'O:21:"SimpleFileWriteGadget":...';

if ( is_serialized( $meta_value ) ) {
    // Line 134 - NOT FIXED
    $raw_meta_val = unserialize( $meta_value ); // VULNERABLE!
}
```

---

## Recommendations

### For Plugin Developers:

1. **Fix Line 134** in `html-admin-page-entries-view.php`:
   ```diff
   - $raw_meta_val = unserialize( $meta_value );
   + $raw_meta_val = evf_maybe_unserialize( $meta_value );
   ```

2. **Block PHP < 7.1** in the safe function:
   ```php
   if ( version_compare( PHP_VERSION, '7.1.0', '<' ) ) {
       // Return null or throw exception instead of unsafe unserialize
       return null;
   }
   ```

3. **Use `json_encode/json_decode`** instead of serialization where possible

### For Security Testers:

1. Check if the target is running PHP < 7.1 (fallback is vulnerable)
2. Check if line 134 was actually fixed in the deployed version
3. Look for other `unserialize()` or `maybe_unserialize()` calls that might have been missed

---

## Version Information

| Version | Status | Details |
|---------|--------|---------|
| < 3.1.1.1 | Fully Vulnerable | Both unserialize calls exploitable |
| 3.1.1.1+ | **STILL VULNERABLE** | Only 1 of 2 calls fixed - Line 134 still exploitable! |

### Current Codebase Status (as of this analysis)

```bash
$ grep -n "unserialize" includes/admin/views/html-admin-page-entries-view.php
134:  $raw_meta_val = unserialize( $meta_value );  # ← VULNERABLE!
194:  $field_value = evf_maybe_unserialize( $field_value );  # ← Safe
```

---

## References

- [WPScan CVE](https://wpscan.com/vulnerability/b90d6420-0de1-42cd-aee2-2473a757a320)
- [PHP unserialize() Documentation](https://www.php.net/manual/en/function.unserialize.php)
- [allowed_classes Option (PHP 7.1+)](https://www.php.net/manual/en/function.unserialize.php#refsect1-function.unserialize-changelog)
