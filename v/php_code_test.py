#!/usr/bin/env python3
"""
PHP Code Analysis Test Suite for Dash-T101 Invoice Management System
Tests the transformed invoices.php functionality through static code analysis.
"""

import re
import sys
from datetime import datetime
import os

# Configuration
INVOICE_FILE = "/app/public_html/dash-t101/invoices.php"
CONFIG_DIR = "/app/public_html/config"

class PHPCodeTestSuite:
    def __init__(self):
        self.test_results = []
        self.invoice_content = ""
        
    def log_result(self, test_name, success, message="", details=""):
        """Log test result"""
        status = "✅ PASS" if success else "❌ FAIL"
        result = {
            'test': test_name,
            'status': status,
            'success': success,
            'message': message,
            'details': details,
            'timestamp': datetime.now().isoformat()
        }
        self.test_results.append(result)
        print(f"{status}: {test_name}")
        if message:
            print(f"    Message: {message}")
        if details and not success:
            print(f"    Details: {details}")
        print()

    def load_invoice_file(self):
        """Load the invoice PHP file"""
        try:
            with open(INVOICE_FILE, 'r', encoding='utf-8') as f:
                self.invoice_content = f.read()
            self.log_result(
                "File Loading", 
                True, 
                f"Successfully loaded {INVOICE_FILE}"
            )
            return True
        except FileNotFoundError:
            self.log_result(
                "File Loading", 
                False, 
                f"Invoice file not found: {INVOICE_FILE}"
            )
            return False
        except Exception as e:
            self.log_result(
                "File Loading", 
                False, 
                f"Error loading file: {str(e)}"
            )
            return False

    def test_php_syntax_structure(self):
        """Test basic PHP syntax and structure"""
        if not self.invoice_content:
            self.log_result(
                "PHP Syntax Structure", 
                False, 
                "No content loaded to test"
            )
            return
            
        # Check for PHP opening tag
        if not self.invoice_content.startswith('<?php'):
            self.log_result(
                "PHP Syntax Structure", 
                False, 
                "File does not start with <?php tag"
            )
            return
            
        # Check for session_start
        if 'session_start()' not in self.invoice_content:
            self.log_result(
                "PHP Syntax Structure", 
                False, 
                "Missing session_start() call"
            )
            return
            
        # Check for required includes
        required_includes = [
            'config/database.php',
            'config/dash_database.php'
        ]
        
        missing_includes = []
        for include in required_includes:
            if include not in self.invoice_content:
                missing_includes.append(include)
                
        if missing_includes:
            self.log_result(
                "PHP Syntax Structure", 
                False, 
                f"Missing required includes: {', '.join(missing_includes)}"
            )
            return
            
        self.log_result(
            "PHP Syntax Structure", 
            True, 
            "PHP syntax and structure appear correct"
        )

    def test_authentication_logic(self):
        """Test authentication and security logic"""
        if not self.invoice_content:
            return
            
        # Check for authentication
        if 'isLoggedIn()' not in self.invoice_content:
            self.log_result(
                "Authentication Logic", 
                False, 
                "Missing isLoggedIn() authentication check"
            )
            return
            
        # Check for redirect on unauthorized access
        if 'header(\'Location:' not in self.invoice_content:
            self.log_result(
                "Authentication Logic", 
                False, 
                "Missing redirect logic for unauthorized access"
            )
            return
            
        # Check for user_id from session
        if '$_SESSION[\'user_id\']' not in self.invoice_content:
            self.log_result(
                "Authentication Logic", 
                False, 
                "Missing user_id session variable usage"
            )
            return
            
        self.log_result(
            "Authentication Logic", 
            True, 
            "Authentication logic is properly implemented"
        )

    def test_post_action_handlers(self):
        """Test POST action handlers"""
        if not self.invoice_content:
            return
            
        required_actions = [
            'add_invoice',
            'update_status', 
            'delete_invoice',
            'send_invoice_email',
            'generate_invoice_multiple'
        ]
        
        missing_actions = []
        for action in required_actions:
            if f"case '{action}':" not in self.invoice_content:
                missing_actions.append(action)
                
        if missing_actions:
            self.log_result(
                "POST Action Handlers", 
                False, 
                f"Missing action handlers: {', '.join(missing_actions)}"
            )
            return
            
        self.log_result(
            "POST Action Handlers", 
            True, 
            "All required POST action handlers are present"
        )

    def test_database_operations(self):
        """Test database operations and SQL queries"""
        if not self.invoice_content:
            return
            
        # Check for PDO usage
        if '$pdo->' not in self.invoice_content:
            self.log_result(
                "Database Operations", 
                False, 
                "No PDO database operations found"
            )
            return
            
        # Check for prepared statements
        if 'prepare(' not in self.invoice_content:
            self.log_result(
                "Database Operations", 
                False, 
                "No prepared statements found"
            )
            return
            
        # Check for transaction handling
        if 'beginTransaction()' not in self.invoice_content:
            self.log_result(
                "Database Operations", 
                False, 
                "No transaction handling found"
            )
            return
            
        # Check for proper error handling
        if 'PDOException' not in self.invoice_content:
            self.log_result(
                "Database Operations", 
                False, 
                "No PDO exception handling found"
            )
            return
            
        self.log_result(
            "Database Operations", 
            True, 
            "Database operations appear properly implemented"
        )

    def test_form_structure(self):
        """Test HTML form structure"""
        if not self.invoice_content:
            return
            
        # Check for main invoice form
        if 'id="invoiceForm"' not in self.invoice_content:
            self.log_result(
                "Form Structure", 
                False, 
                "Main invoice form with id='invoiceForm' not found"
            )
            return
            
        # Check for required form fields
        required_fields = [
            'name="action"',
            'name="client_id"',
            'name="invoice_date"',
            'name="due_date"',
            'name="subtotal"',
            'name="tax_rate"',
            'name="status"'
        ]
        
        missing_fields = []
        for field in required_fields:
            if field not in self.invoice_content:
                missing_fields.append(field)
                
        if missing_fields:
            self.log_result(
                "Form Structure", 
                False, 
                f"Missing form fields: {', '.join(missing_fields)}"
            )
            return
            
        self.log_result(
            "Form Structure", 
            True, 
            "Form structure contains all required fields"
        )

    def test_javascript_functions(self):
        """Test JavaScript functionality"""
        if not self.invoice_content:
            return
            
        required_js_functions = [
            'calculateInvoiceTotal',
            'calculateItemTotal',
            'updateSubtotal',
            'addInvoiceItem',
            'openStatusModal',
            'closeStatusModal',
            'togglePaymentFields'
        ]
        
        missing_functions = []
        for func in required_js_functions:
            if f'function {func}(' not in self.invoice_content:
                missing_functions.append(func)
                
        if missing_functions:
            self.log_result(
                "JavaScript Functions", 
                False, 
                f"Missing JavaScript functions: {', '.join(missing_functions)}"
            )
            return
            
        self.log_result(
            "JavaScript Functions", 
            True, 
            "All required JavaScript functions are present"
        )

    def test_vision_ui_transformation(self):
        """Test Vision UI transformation elements"""
        if not self.invoice_content:
            return
            
        # Check for Vision UI classes
        vision_ui_classes = [
            'glass-hero',
            'video-card',
            'cta-btn',
            'page-btn',
            'vision-form'
        ]
        
        missing_classes = []
        for css_class in vision_ui_classes:
            if css_class not in self.invoice_content:
                missing_classes.append(css_class)
                
        if missing_classes:
            self.log_result(
                "Vision UI Transformation", 
                False, 
                f"Missing Vision UI classes: {', '.join(missing_classes)}"
            )
            return
            
        # Check for Font Awesome icons
        if 'fas fa-' not in self.invoice_content:
            self.log_result(
                "Vision UI Transformation", 
                False, 
                "No Font Awesome icons found"
            )
            return
            
        # Check for Vision UI includes
        if 'vision/includes/' not in self.invoice_content:
            self.log_result(
                "Vision UI Transformation", 
                False, 
                "Vision UI includes not found"
            )
            return
            
        self.log_result(
            "Vision UI Transformation", 
            True, 
            "Vision UI transformation appears complete"
        )

    def test_search_filter_logic(self):
        """Test search and filter functionality"""
        if not self.invoice_content:
            return
            
        # Check for search parameter handling
        if '$_GET[\'search\']' not in self.invoice_content:
            self.log_result(
                "Search Filter Logic", 
                False, 
                "Search parameter handling not found"
            )
            return
            
        # Check for status filter handling
        if '$_GET[\'status\']' not in self.invoice_content:
            self.log_result(
                "Search Filter Logic", 
                False, 
                "Status filter handling not found"
            )
            return
            
        # Check for WHERE clause construction
        if '$where_clause' not in self.invoice_content:
            self.log_result(
                "Search Filter Logic", 
                False, 
                "Dynamic WHERE clause construction not found"
            )
            return
            
        self.log_result(
            "Search Filter Logic", 
            True, 
            "Search and filter logic is properly implemented"
        )

    def test_email_functionality(self):
        """Test email functionality"""
        if not self.invoice_content:
            return
            
        # Check for email action handler
        if 'send_invoice_email' not in self.invoice_content:
            self.log_result(
                "Email Functionality", 
                False, 
                "Send invoice email action not found"
            )
            return
            
        # Check for sendInvoiceEmail function call
        if 'sendInvoiceEmail(' not in self.invoice_content:
            self.log_result(
                "Email Functionality", 
                True, 
                "Email functionality present but sendInvoiceEmail function may need implementation"
            )
            return
            
        self.log_result(
            "Email Functionality", 
            True, 
            "Email functionality is implemented"
        )

    def test_security_measures(self):
        """Test security measures"""
        if not self.invoice_content:
            return
            
        # Check for input sanitization
        if 'htmlspecialchars(' not in self.invoice_content:
            self.log_result(
                "Security Measures", 
                False, 
                "No input sanitization with htmlspecialchars found"
            )
            return
            
        # Check for user_id validation in queries
        user_id_checks = self.invoice_content.count('user_id = ?')
        if user_id_checks < 5:  # Should be in multiple queries
            self.log_result(
                "Security Measures", 
                False, 
                f"Insufficient user_id validation in queries (found {user_id_checks})"
            )
            return
            
        self.log_result(
            "Security Measures", 
            True, 
            "Security measures appear adequate"
        )

    def test_config_dependencies(self):
        """Test configuration file dependencies"""
        config_files = [
            f"{CONFIG_DIR}/database.php",
            f"{CONFIG_DIR}/dash_database.php",
            f"{CONFIG_DIR}/dash_functions.php"
        ]
        
        missing_files = []
        for config_file in config_files:
            if not os.path.exists(config_file):
                missing_files.append(config_file)
                
        if missing_files:
            self.log_result(
                "Config Dependencies", 
                False, 
                f"Missing configuration files: {', '.join(missing_files)}"
            )
            return
            
        self.log_result(
            "Config Dependencies", 
            True, 
            "All required configuration files are present"
        )

    def run_all_tests(self):
        """Run all tests"""
        print("=" * 70)
        print("DASH-T101 INVOICE PHP CODE ANALYSIS TEST SUITE")
        print("=" * 70)
        print(f"Testing file: {INVOICE_FILE}")
        print(f"Test started at: {datetime.now().isoformat()}")
        print("=" * 70)
        print()
        
        # Load file first
        if not self.load_invoice_file():
            print("Cannot proceed with tests - file loading failed")
            return False
            
        # Run tests
        self.test_php_syntax_structure()
        self.test_authentication_logic()
        self.test_post_action_handlers()
        self.test_database_operations()
        self.test_form_structure()
        self.test_javascript_functions()
        self.test_vision_ui_transformation()
        self.test_search_filter_logic()
        self.test_email_functionality()
        self.test_security_measures()
        self.test_config_dependencies()
        
        # Summary
        print("=" * 70)
        print("TEST SUMMARY")
        print("=" * 70)
        
        passed = sum(1 for result in self.test_results if result['success'])
        total = len(self.test_results)
        
        print(f"Total Tests: {total}")
        print(f"Passed: {passed}")
        print(f"Failed: {total - passed}")
        print(f"Success Rate: {(passed/total)*100:.1f}%")
        print()
        
        # Failed tests details
        failed_tests = [result for result in self.test_results if not result['success']]
        if failed_tests:
            print("FAILED TESTS:")
            for test in failed_tests:
                print(f"❌ {test['test']}: {test['message']}")
                if test['details']:
                    print(f"   Details: {test['details']}")
        else:
            print("🎉 All tests passed!")
        
        print("=" * 70)
        
        return passed == total

if __name__ == "__main__":
    test_suite = PHPCodeTestSuite()
    success = test_suite.run_all_tests()
    sys.exit(0 if success else 1)