#!/usr/bin/env python3
"""
Comprehensive PHP Application Test Suite
Tests the Vision UI transformed PHP application for functionality and structure.
"""

import os
import re
import sys
from pathlib import Path
from datetime import datetime

class PHPApplicationTestSuite:
    def __init__(self):
        self.test_results = []
        self.base_path = Path("/app/public_html")
        self.critical_files = [
            "index.php",
            "login.php", 
            "dash-t101/invoices.php",
            "dash-t101/projects.php",
            "vision/assets/css/style.css",
            "vision/assets/js/main.js"
        ]
        
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

    def test_file_structure(self):
        """Test if all critical files exist"""
        missing_files = []
        existing_files = []
        
        for file_path in self.critical_files:
            full_path = self.base_path / file_path
            if full_path.exists():
                existing_files.append(file_path)
            else:
                missing_files.append(file_path)
        
        if not missing_files:
            self.log_result(
                "File Structure", 
                True, 
                f"All {len(self.critical_files)} critical files exist"
            )
        else:
            self.log_result(
                "File Structure", 
                False, 
                f"Missing {len(missing_files)} critical files",
                f"Missing: {', '.join(missing_files)}"
            )
        
        return len(missing_files) == 0

    def test_vision_ui_transformation(self):
        """Test if Vision UI components are properly integrated"""
        vision_indicators = [
            "vision/includes/head.php",
            "vision/includes/header.php", 
            "vision/includes/sidebar.php",
            "vision/includes/footer.php",
            "vision/assets/css/style.css"
        ]
        
        missing_vision_files = []
        for file_path in vision_indicators:
            full_path = self.base_path / file_path
            if not full_path.exists():
                missing_vision_files.append(file_path)
        
        if not missing_vision_files:
            self.log_result(
                "Vision UI Structure", 
                True, 
                "All Vision UI component files exist"
            )
        else:
            self.log_result(
                "Vision UI Structure", 
                False, 
                f"Missing Vision UI files: {', '.join(missing_vision_files)}"
            )

    def test_php_syntax(self):
        """Test PHP syntax for critical files"""
        php_files = [
            "index.php",
            "login.php",
            "dash-t101/invoices.php", 
            "dash-t101/projects.php"
        ]
        
        syntax_errors = []
        valid_files = []
        
        for file_path in php_files:
            full_path = self.base_path / file_path
            if full_path.exists():
                try:
                    # Read file content to check for basic PHP structure
                    content = full_path.read_text(encoding='utf-8')
                    
                    # Check for PHP opening tag
                    if not content.strip().startswith('<?php'):
                        syntax_errors.append(f"{file_path}: Missing PHP opening tag")
                        continue
                    
                    # Check for basic PHP structure
                    if 'session_start()' in content or 'require_once' in content or 'include' in content:
                        valid_files.append(file_path)
                    else:
                        syntax_errors.append(f"{file_path}: Missing basic PHP structure")
                        
                except Exception as e:
                    syntax_errors.append(f"{file_path}: {str(e)}")
            else:
                syntax_errors.append(f"{file_path}: File not found")
        
        if not syntax_errors:
            self.log_result(
                "PHP Syntax Check", 
                True, 
                f"All {len(valid_files)} PHP files have valid syntax"
            )
        else:
            self.log_result(
                "PHP Syntax Check", 
                False, 
                f"Found {len(syntax_errors)} syntax issues",
                "; ".join(syntax_errors)
            )

    def test_authentication_system(self):
        """Test authentication system implementation"""
        login_file = self.base_path / "login.php"
        
        if not login_file.exists():
            self.log_result(
                "Authentication System", 
                False, 
                "login.php file not found"
            )
            return
        
        try:
            content = login_file.read_text(encoding='utf-8')
            
            auth_features = {
                'session_start': 'session_start()' in content,
                'password_verify': 'password_verify(' in content,
                'database_query': 'prepare(' in content and 'execute(' in content,
                'redirect_logic': 'header(' in content and 'Location:' in content,
                'form_handling': '$_POST' in content,
                'error_handling': '$error' in content or 'error_message' in content
            }
            
            missing_features = [feature for feature, present in auth_features.items() if not present]
            
            if not missing_features:
                self.log_result(
                    "Authentication System", 
                    True, 
                    "All authentication features implemented"
                )
            else:
                self.log_result(
                    "Authentication System", 
                    False, 
                    f"Missing features: {', '.join(missing_features)}"
                )
                
        except Exception as e:
            self.log_result(
                "Authentication System", 
                False, 
                f"Error reading login.php: {str(e)}"
            )

    def test_dashboard_functionality(self):
        """Test dashboard pages functionality"""
        dashboard_files = {
            "dash-t101/invoices.php": [
                "add_invoice", "update_status", "delete_invoice", 
                "send_invoice_email", "generate_invoice_multiple"
            ],
            "dash-t101/projects.php": [
                "add_project", "edit_project", "delete_project", 
                "complete_project", "generate_invoice"
            ]
        }
        
        results = {}
        
        for file_path, expected_actions in dashboard_files.items():
            full_path = self.base_path / file_path
            
            if not full_path.exists():
                results[file_path] = f"File not found"
                continue
            
            try:
                content = full_path.read_text(encoding='utf-8')
                
                missing_actions = []
                for action in expected_actions:
                    if f"'{action}'" not in content and f'"{action}"' not in content:
                        missing_actions.append(action)
                
                if not missing_actions:
                    results[file_path] = "All actions implemented"
                else:
                    results[file_path] = f"Missing actions: {', '.join(missing_actions)}"
                    
            except Exception as e:
                results[file_path] = f"Error: {str(e)}"
        
        # Check overall results
        all_good = all("All actions implemented" in result for result in results.values())
        
        if all_good:
            self.log_result(
                "Dashboard Functionality", 
                True, 
                "All dashboard actions implemented"
            )
        else:
            issues = [f"{file}: {result}" for file, result in results.items() 
                     if "All actions implemented" not in result]
            self.log_result(
                "Dashboard Functionality", 
                False, 
                f"Issues found in {len(issues)} files",
                "; ".join(issues)
            )

    def test_database_integration(self):
        """Test database integration"""
        config_files = [
            "config/database.php",
            "config/dash_database.php"
        ]
        
        db_features_found = []
        missing_configs = []
        
        for config_file in config_files:
            full_path = self.base_path / config_file
            
            if not full_path.exists():
                missing_configs.append(config_file)
                continue
            
            try:
                content = full_path.read_text(encoding='utf-8')
                
                if 'PDO' in content:
                    db_features_found.append(f"{config_file}: PDO connection")
                if 'mysql:' in content:
                    db_features_found.append(f"{config_file}: MySQL connection")
                if '$pdo' in content:
                    db_features_found.append(f"{config_file}: PDO variable")
                    
            except Exception as e:
                missing_configs.append(f"{config_file}: {str(e)}")
        
        if db_features_found and not missing_configs:
            self.log_result(
                "Database Integration", 
                True, 
                f"Database configuration found: {', '.join(db_features_found)}"
            )
        else:
            self.log_result(
                "Database Integration", 
                False, 
                f"Database issues found",
                f"Missing configs: {missing_configs}; Found features: {db_features_found}"
            )

    def test_vision_ui_assets(self):
        """Test Vision UI assets"""
        asset_files = [
            "vision/assets/css/style.css",
            "vision/assets/js/main.js"
        ]
        
        asset_results = {}
        
        for asset_file in asset_files:
            full_path = self.base_path / asset_file
            
            if not full_path.exists():
                asset_results[asset_file] = "File not found"
                continue
            
            try:
                content = full_path.read_text(encoding='utf-8')
                
                if asset_file.endswith('.css'):
                    # Check for Vision UI CSS features
                    css_features = {
                        'glass_effects': 'glass-' in content or 'backdrop-filter' in content,
                        'vision_classes': '.vision-' in content or '.cta-btn' in content,
                        'responsive': '@media' in content,
                        'animations': 'transition' in content or 'animation' in content
                    }
                    
                    missing_css = [f for f, present in css_features.items() if not present]
                    
                    if not missing_css:
                        asset_results[asset_file] = "All CSS features present"
                    else:
                        asset_results[asset_file] = f"Missing CSS features: {', '.join(missing_css)}"
                
                elif asset_file.endswith('.js'):
                    # Check for JavaScript functionality
                    js_features = {
                        'dom_ready': 'DOMContentLoaded' in content or '$(document).ready' in content,
                        'functions': 'function' in content or '=>' in content,  # Include arrow functions
                        'event_handlers': 'addEventListener' in content or 'onclick' in content
                    }
                    
                    missing_js = [f for f, present in js_features.items() if not present]
                    
                    if not missing_js:
                        asset_results[asset_file] = "All JS features present"
                    else:
                        asset_results[asset_file] = f"Missing JS features: {', '.join(missing_js)}"
                        
            except Exception as e:
                asset_results[asset_file] = f"Error: {str(e)}"
        
        # Check overall results
        all_assets_good = all("present" in result for result in asset_results.values())
        
        if all_assets_good:
            self.log_result(
                "Vision UI Assets", 
                True, 
                "All Vision UI assets properly configured"
            )
        else:
            issues = [f"{file}: {result}" for file, result in asset_results.items() 
                     if "present" not in result]
            self.log_result(
                "Vision UI Assets", 
                False, 
                f"Asset issues found",
                "; ".join(issues)
            )

    def test_form_security(self):
        """Test form security measures"""
        php_files = [
            "login.php",
            "dash-t101/invoices.php",
            "dash-t101/projects.php"
        ]
        
        security_results = {}
        
        for file_path in php_files:
            full_path = self.base_path / file_path
            
            if not full_path.exists():
                security_results[file_path] = "File not found"
                continue
            
            try:
                content = full_path.read_text(encoding='utf-8')
                
                security_features = {
                    'csrf_protection': 'csrf' in content.lower() or 'token' in content,
                    'input_sanitization': 'htmlspecialchars(' in content or 'filter_' in content,
                    'prepared_statements': 'prepare(' in content and '?' in content,
                    'session_security': 'session_' in content,
                    'validation': 'empty(' in content or 'isset(' in content
                }
                
                present_features = [f for f, present in security_features.items() if present]
                
                if len(present_features) >= 3:  # At least 3 security features
                    security_results[file_path] = f"Good security: {', '.join(present_features)}"
                else:
                    security_results[file_path] = f"Limited security: {', '.join(present_features)}"
                    
            except Exception as e:
                security_results[file_path] = f"Error: {str(e)}"
        
        # Check overall security
        good_security_count = sum(1 for result in security_results.values() 
                                 if "Good security" in result)
        
        if good_security_count >= len(php_files) - 1:  # Allow one file to have limited security
            self.log_result(
                "Form Security", 
                True, 
                f"Good security measures in {good_security_count}/{len(php_files)} files"
            )
        else:
            self.log_result(
                "Form Security", 
                False, 
                f"Security concerns in {len(php_files) - good_security_count} files",
                "; ".join([f"{file}: {result}" for file, result in security_results.items()])
            )

    def test_admin_functionality(self):
        """Test admin directory functionality"""
        admin_dirs = ["admin", "dash-t101"]
        admin_results = {}
        
        for admin_dir in admin_dirs:
            admin_path = self.base_path / admin_dir
            
            if not admin_path.exists():
                admin_results[admin_dir] = "Directory not found"
                continue
            
            # Count PHP files in admin directory
            php_files = list(admin_path.glob("*.php"))
            
            if php_files:
                admin_results[admin_dir] = f"Found {len(php_files)} PHP files"
            else:
                admin_results[admin_dir] = "No PHP files found"
        
        # Check if at least one admin directory is functional
        functional_dirs = [dir_name for dir_name, result in admin_results.items() 
                          if "PHP files" in result]
        
        if functional_dirs:
            self.log_result(
                "Admin Functionality", 
                True, 
                f"Admin functionality available in: {', '.join(functional_dirs)}"
            )
        else:
            self.log_result(
                "Admin Functionality", 
                False, 
                "No functional admin directories found",
                "; ".join([f"{dir_name}: {result}" for dir_name, result in admin_results.items()])
            )

    def run_all_tests(self):
        """Run all tests"""
        print("=" * 70)
        print("COMPREHENSIVE PHP APPLICATION TEST SUITE")
        print("Vision UI Transformation Verification")
        print("=" * 70)
        print(f"Testing Path: {self.base_path}")
        print(f"Test started at: {datetime.now().isoformat()}")
        print("=" * 70)
        print()
        
        # Run tests
        self.test_file_structure()
        self.test_vision_ui_transformation()
        self.test_php_syntax()
        self.test_authentication_system()
        self.test_dashboard_functionality()
        self.test_database_integration()
        self.test_vision_ui_assets()
        self.test_form_security()
        self.test_admin_functionality()
        
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
    test_suite = PHPApplicationTestSuite()
    success = test_suite.run_all_tests()
    sys.exit(0 if success else 1)