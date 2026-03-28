#!/usr/bin/env python3
"""
Static Code Analysis for Dash-T101 Projects.php
Performs comprehensive analysis of the transformed projects.php file to verify backend functionality.
"""

import re
import os
from datetime import datetime

class PHPStaticAnalyzer:
    def __init__(self, file_path):
        self.file_path = file_path
        self.test_results = []
        self.content = ""
        
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

    def load_file(self):
        """Load the PHP file content"""
        try:
            with open(self.file_path, 'r', encoding='utf-8') as f:
                self.content = f.read()
            return True
        except Exception as e:
            self.log_result("File Loading", False, f"Failed to load file: {str(e)}")
            return False

    def test_php_syntax_structure(self):
        """Test basic PHP syntax and structure"""
        try:
            # Check for PHP opening tag
            if not self.content.startswith('<?php'):
                self.log_result("PHP Syntax - Opening Tag", False, "File doesn't start with <?php")
                return
            
            # Check for session_start
            if 'session_start()' in self.content:
                self.log_result("PHP Syntax - Session Management", True, "session_start() found")
            else:
                self.log_result("PHP Syntax - Session Management", False, "session_start() not found")
            
            # Check for required includes
            required_includes = [
                'config/database.php',
                'config/dash_database.php'
            ]
            
            missing_includes = []
            for include in required_includes:
                if include not in self.content:
                    missing_includes.append(include)
            
            if not missing_includes:
                self.log_result("PHP Syntax - Required Includes", True, "All required includes present")
            else:
                self.log_result("PHP Syntax - Required Includes", False, f"Missing includes: {', '.join(missing_includes)}")
            
            # Check for basic PHP structure
            if 'if ($_SERVER[\'REQUEST_METHOD\'] === \'POST\')' in self.content:
                self.log_result("PHP Syntax - POST Handling", True, "POST request handling structure found")
            else:
                self.log_result("PHP Syntax - POST Handling", False, "POST request handling structure not found")
                
        except Exception as e:
            self.log_result("PHP Syntax Structure", False, f"Error analyzing syntax: {str(e)}")

    def test_authentication_logic(self):
        """Test authentication and security logic"""
        try:
            # Check for authentication
            if 'isLoggedIn()' in self.content:
                self.log_result("Authentication - Login Check", True, "isLoggedIn() function call found")
            else:
                self.log_result("Authentication - Login Check", False, "isLoggedIn() function call not found")
            
            # Check for redirect on unauthorized access
            if 'header(\'Location: /login.php' in self.content:
                self.log_result("Authentication - Redirect Logic", True, "Login redirect logic found")
            else:
                self.log_result("Authentication - Redirect Logic", False, "Login redirect logic not found")
            
            # Check for user_id validation
            if '$user_id = $_SESSION[\'user_id\']' in self.content:
                self.log_result("Authentication - User ID Validation", True, "User ID from session found")
            else:
                self.log_result("Authentication - User ID Validation", False, "User ID from session not found")
                
        except Exception as e:
            self.log_result("Authentication Logic", False, f"Error analyzing authentication: {str(e)}")

    def test_post_action_handlers(self):
        """Test all POST action handlers"""
        try:
            expected_actions = [
                'add_project',
                'edit_project', 
                'delete_project',
                'complete_project',
                'generate_invoice'
            ]
            
            missing_actions = []
            present_actions = []
            
            for action in expected_actions:
                if f"case '{action}':" in self.content:
                    present_actions.append(action)
                else:
                    missing_actions.append(action)
            
            if not missing_actions:
                self.log_result("POST Action Handlers", True, f"All {len(expected_actions)} action handlers present: {', '.join(present_actions)}")
            else:
                self.log_result("POST Action Handlers", False, f"Missing action handlers: {', '.join(missing_actions)}")
                
        except Exception as e:
            self.log_result("POST Action Handlers", False, f"Error analyzing action handlers: {str(e)}")

    def test_database_operations(self):
        """Test database operations and PDO usage"""
        try:
            # Check for PDO usage
            if '$pdo->prepare(' in self.content:
                self.log_result("Database - PDO Prepared Statements", True, "PDO prepared statements found")
            else:
                self.log_result("Database - PDO Prepared Statements", False, "PDO prepared statements not found")
            
            # Check for SQL operations
            sql_operations = ['INSERT INTO', 'UPDATE', 'DELETE FROM', 'SELECT']
            found_operations = []
            
            for operation in sql_operations:
                if operation in self.content:
                    found_operations.append(operation)
            
            if len(found_operations) >= 3:  # Should have at least INSERT, UPDATE, DELETE
                self.log_result("Database - SQL Operations", True, f"SQL operations found: {', '.join(found_operations)}")
            else:
                self.log_result("Database - SQL Operations", False, f"Insufficient SQL operations found: {', '.join(found_operations)}")
            
            # Check for error handling
            if 'PDOException' in self.content:
                self.log_result("Database - Error Handling", True, "PDO exception handling found")
            else:
                self.log_result("Database - Error Handling", False, "PDO exception handling not found")
                
        except Exception as e:
            self.log_result("Database Operations", False, f"Error analyzing database operations: {str(e)}")

    def test_form_structure_and_fields(self):
        """Test form structure and required fields"""
        try:
            # Check for form tag
            if '<form' in self.content and 'method="POST"' in self.content:
                self.log_result("Form Structure - Form Tag", True, "POST form found")
            else:
                self.log_result("Form Structure - Form Tag", False, "POST form not found")
            
            # Check for required form fields
            required_fields = [
                'name="action"',
                'name="project_name"',
                'name="client_id"',
                'name="source_language"',
                'name="target_language"',
                'name="service_type"',
                'name="word_count"',
                'name="rate_per_word"',
                'name="currency"',
                'name="status"',
                'name="priority"',
                'name="start_date"',
                'name="deadline"',
                'name="daily_word_target"'
            ]
            
            missing_fields = []
            present_fields = []
            
            for field in required_fields:
                if field in self.content:
                    present_fields.append(field)
                else:
                    missing_fields.append(field)
            
            success_rate = len(present_fields) / len(required_fields)
            if success_rate >= 0.8:  # At least 80% of fields should be present
                self.log_result("Form Structure - Required Fields", True, f"Form fields present: {len(present_fields)}/{len(required_fields)}")
            else:
                self.log_result("Form Structure - Required Fields", False, f"Missing critical form fields: {', '.join(missing_fields)}")
                
        except Exception as e:
            self.log_result("Form Structure", False, f"Error analyzing form structure: {str(e)}")

    def test_javascript_functions(self):
        """Test JavaScript calculation functions"""
        try:
            # Look for JavaScript functions
            js_patterns = [
                r'function\s+calculateProjectTotal',
                r'function\s+updateProjectTotal',
                r'function\s+calculateWordCount',
                r'function\s+updateClientCurrency',
                r'calculateProjectTotal\s*\(',
                r'updateProjectTotal\s*\(',
                r'calculateWordCount\s*\('
            ]
            
            found_js_functions = []
            for pattern in js_patterns:
                if re.search(pattern, self.content, re.IGNORECASE):
                    found_js_functions.append(pattern.replace(r'\s+', ' ').replace(r'\s*\(', '()'))
            
            if len(found_js_functions) >= 2:  # At least 2 calculation functions should be present
                self.log_result("JavaScript Functions", True, f"JavaScript calculation functions found: {len(found_js_functions)}")
            else:
                self.log_result("JavaScript Functions", False, f"Insufficient JavaScript calculation functions found: {len(found_js_functions)}")
                
        except Exception as e:
            self.log_result("JavaScript Functions", False, f"Error analyzing JavaScript: {str(e)}")

    def test_vision_ui_transformation(self):
        """Test Vision UI transformation elements"""
        try:
            vision_ui_classes = [
                'glass-hero',
                'hero-content', 
                'video-card',
                'vision-form',
                'form-grid',
                'form-group',
                'cta-btn',
                'alert-success',
                'alert-error'
            ]
            
            missing_classes = []
            present_classes = []
            
            for css_class in vision_ui_classes:
                if css_class in self.content:
                    present_classes.append(css_class)
                else:
                    missing_classes.append(css_class)
            
            # Check for Font Awesome icons
            fa_icons_present = 'fas fa-' in self.content or 'fa fa-' in self.content
            
            # Check for Vision UI includes
            vision_includes = 'vision/includes/' in self.content
            
            success_rate = len(present_classes) / len(vision_ui_classes)
            if success_rate >= 0.7 and fa_icons_present and vision_includes:
                self.log_result("Vision UI Transformation", True, f"Vision UI components: {len(present_classes)}/{len(vision_ui_classes)}, FA icons: {fa_icons_present}, Vision includes: {vision_includes}")
            else:
                self.log_result("Vision UI Transformation", False, f"Incomplete Vision UI transformation. Classes: {len(present_classes)}/{len(vision_ui_classes)}, FA icons: {fa_icons_present}, Vision includes: {vision_includes}")
                
        except Exception as e:
            self.log_result("Vision UI Transformation", False, f"Error analyzing Vision UI: {str(e)}")

    def test_search_filter_logic(self):
        """Test search and filter functionality"""
        try:
            # Check for search parameter handling
            search_logic = '$search = $_GET[\'search\']' in self.content or '$_GET[\'search\']' in self.content
            
            # Check for status filter handling  
            status_filter_logic = '$status_filter = $_GET[\'status\']' in self.content or '$_GET[\'status\']' in self.content
            
            # Check for WHERE clause construction
            where_clause_logic = 'WHERE' in self.content and 'LIKE' in self.content
            
            if search_logic and status_filter_logic and where_clause_logic:
                self.log_result("Search and Filter Logic", True, "Search and filter functionality properly implemented")
            else:
                self.log_result("Search and Filter Logic", False, f"Incomplete search/filter logic. Search: {search_logic}, Status filter: {status_filter_logic}, WHERE clause: {where_clause_logic}")
                
        except Exception as e:
            self.log_result("Search and Filter Logic", False, f"Error analyzing search/filter logic: {str(e)}")

    def test_security_measures(self):
        """Test security measures"""
        try:
            # Check for input sanitization
            sanitization_functions = ['htmlspecialchars', 'intval', 'floatval', 'str_replace']
            found_sanitization = []
            
            for func in sanitization_functions:
                if func in self.content:
                    found_sanitization.append(func)
            
            # Check for user_id validation in queries
            user_id_validation = 'user_id = ?' in self.content or 'AND user_id = ?' in self.content
            
            if len(found_sanitization) >= 2 and user_id_validation:
                self.log_result("Security Measures", True, f"Security measures found: {', '.join(found_sanitization)}, User ID validation: {user_id_validation}")
            else:
                self.log_result("Security Measures", False, f"Insufficient security measures. Sanitization: {', '.join(found_sanitization)}, User ID validation: {user_id_validation}")
                
        except Exception as e:
            self.log_result("Security Measures", False, f"Error analyzing security: {str(e)}")

    def test_timeline_and_productivity_logic(self):
        """Test timeline visualization and productivity calculation logic"""
        try:
            # Check for timeline variables
            timeline_vars = [
                'display_range_days',
                'today_display_offset_days',
                'min_display_date',
                'max_display_date',
                'today_pos_on_global_line_percent'
            ]
            
            found_timeline_vars = []
            for var in timeline_vars:
                if var in self.content:
                    found_timeline_vars.append(var)
            
            # Check for date calculations
            date_calculations = 'strtotime(' in self.content and 'date(' in self.content
            
            # Check for productivity estimation
            productivity_logic = 'daily_word_target' in self.content
            
            if len(found_timeline_vars) >= 3 and date_calculations and productivity_logic:
                self.log_result("Timeline and Productivity Logic", True, f"Timeline vars: {len(found_timeline_vars)}/{len(timeline_vars)}, Date calculations: {date_calculations}, Productivity: {productivity_logic}")
            else:
                self.log_result("Timeline and Productivity Logic", False, f"Incomplete timeline/productivity logic. Timeline vars: {len(found_timeline_vars)}/{len(timeline_vars)}, Date calculations: {date_calculations}, Productivity: {productivity_logic}")
                
        except Exception as e:
            self.log_result("Timeline and Productivity Logic", False, f"Error analyzing timeline/productivity: {str(e)}")

    def test_calculation_functions(self):
        """Test word count and rate calculation functions"""
        try:
            # Check for calculation logic
            calculation_elements = [
                'word_count',
                'rate_per_word', 
                'total_amount',
                'calculateProjectTotal',
                'negotiated_amount'
            ]
            
            found_calculations = []
            for element in calculation_elements:
                if element in self.content:
                    found_calculations.append(element)
            
            # Check for currency handling
            currency_handling = 'currency' in self.content and ('$_POST[\'currency\']' in self.content or 'default_currency' in self.content)
            
            if len(found_calculations) >= 4 and currency_handling:
                self.log_result("Calculation Functions", True, f"Calculation elements: {len(found_calculations)}/{len(calculation_elements)}, Currency handling: {currency_handling}")
            else:
                self.log_result("Calculation Functions", False, f"Incomplete calculation logic. Elements: {len(found_calculations)}/{len(calculation_elements)}, Currency: {currency_handling}")
                
        except Exception as e:
            self.log_result("Calculation Functions", False, f"Error analyzing calculations: {str(e)}")

    def run_all_tests(self):
        """Run all static analysis tests"""
        print("=" * 70)
        print("DASH-T101 PROJECTS.PHP STATIC CODE ANALYSIS")
        print("=" * 70)
        print(f"Analyzing file: {self.file_path}")
        print(f"Analysis started at: {datetime.now().isoformat()}")
        print("=" * 70)
        print()
        
        if not self.load_file():
            return False
        
        # Run all tests
        self.test_php_syntax_structure()
        self.test_authentication_logic()
        self.test_post_action_handlers()
        self.test_database_operations()
        self.test_form_structure_and_fields()
        self.test_javascript_functions()
        self.test_vision_ui_transformation()
        self.test_search_filter_logic()
        self.test_security_measures()
        self.test_timeline_and_productivity_logic()
        self.test_calculation_functions()
        
        # Summary
        print("=" * 70)
        print("STATIC ANALYSIS SUMMARY")
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
            print("🎉 All static analysis tests passed!")
        
        print("=" * 70)
        
        return passed == total

if __name__ == "__main__":
    analyzer = PHPStaticAnalyzer("/app/public_html/dash-t101/projects.php")
    success = analyzer.run_all_tests()
    exit(0 if success else 1)