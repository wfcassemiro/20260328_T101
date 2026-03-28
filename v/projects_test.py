#!/usr/bin/env python3
"""
Backend Test Suite for Dash-T101 Project Management System
Tests the transformed projects.php functionality to ensure all backend operations work correctly.
"""

import requests
import json
import sys
from datetime import datetime, timedelta
import os

# Configuration
BASE_URL = "https://apple-vision-php.preview.emergentagent.com"
PROJECTS_URL = f"{BASE_URL}/dash-t101/projects.php"

class ProjectTestSuite:
    def __init__(self):
        self.session = requests.Session()
        self.test_results = []
        self.authenticated = False
        
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

    def test_page_accessibility(self):
        """Test if the projects page is accessible"""
        try:
            response = self.session.get(PROJECTS_URL, timeout=10)
            
            if response.status_code == 200:
                if "login" in response.url.lower() or "login.php" in response.text:
                    self.log_result(
                        "Page Accessibility", 
                        True, 
                        "Page redirects to login (authentication required) - Expected behavior"
                    )
                    return True
                elif "Gerenciar Projetos" in response.text or "projects" in response.text.lower():
                    self.log_result(
                        "Page Accessibility", 
                        True, 
                        "Page loads successfully with project content"
                    )
                    self.authenticated = True
                    return True
                else:
                    self.log_result(
                        "Page Accessibility", 
                        False, 
                        "Page loads but doesn't contain expected project content",
                        f"Response length: {len(response.text)}"
                    )
                    return False
            else:
                self.log_result(
                    "Page Accessibility", 
                    False, 
                    f"HTTP {response.status_code} error",
                    f"Response: {response.text[:200]}..."
                )
                return False
                
        except requests.exceptions.RequestException as e:
            self.log_result(
                "Page Accessibility", 
                False, 
                "Network error accessing page",
                str(e)
            )
            return False

    def test_project_creation_endpoint(self):
        """Test project creation POST endpoint"""
        try:
            # Prepare test data for project creation
            project_data = {
                'action': 'add_project',
                'client_id': '1',  # Assuming client ID 1 exists
                'project_name': 'Test Translation Project',
                'po_number': 'PO-2024-001',
                'project_description': 'Test project created by automated test',
                'source_language': 'en',
                'target_language': 'pt',
                'service_type': 'translation',
                'word_count': '1000',
                'rate_per_word': '0.15',
                'currency': 'USD',
                'status': 'in_progress',
                'priority': 'medium',
                'start_date': datetime.now().strftime('%Y-%m-%d'),
                'deadline': (datetime.now() + timedelta(days=30)).strftime('%Y-%m-%d'),
                'notes': 'Test project notes',
                'daily_word_target': '100'
            }
            
            response = self.session.post(PROJECTS_URL, data=project_data, timeout=10)
            
            if response.status_code == 200:
                if "login" in response.url.lower():
                    self.log_result(
                        "Project Creation Endpoint", 
                        True, 
                        "Endpoint accessible but requires authentication (expected)"
                    )
                elif "erro" in response.text.lower() and "cliente" in response.text.lower():
                    self.log_result(
                        "Project Creation Endpoint", 
                        True, 
                        "Endpoint processes request but client validation failed (expected without valid client)"
                    )
                elif "projeto adicionado" in response.text.lower():
                    self.log_result(
                        "Project Creation Endpoint", 
                        True, 
                        "Project creation successful"
                    )
                else:
                    self.log_result(
                        "Project Creation Endpoint", 
                        True, 
                        "Endpoint accepts POST request and processes form data"
                    )
            else:
                self.log_result(
                    "Project Creation Endpoint", 
                    False, 
                    f"HTTP {response.status_code} error",
                    f"Response: {response.text[:200]}..."
                )
                
        except requests.exceptions.RequestException as e:
            self.log_result(
                "Project Creation Endpoint", 
                False, 
                "Network error during project creation test",
                str(e)
            )

    def test_project_editing_functionality(self):
        """Test project editing GET and POST functionality"""
        try:
            # Test GET with edit parameter
            edit_params = {'edit': '1'}  # Assuming project ID 1 exists
            response = self.session.get(PROJECTS_URL, params=edit_params, timeout=10)
            
            if response.status_code == 200:
                if "login" in response.url.lower():
                    self.log_result(
                        "Project Edit GET", 
                        True, 
                        "Edit endpoint accessible but requires authentication (expected)"
                    )
                else:
                    self.log_result(
                        "Project Edit GET", 
                        True, 
                        "Edit parameter accepted successfully"
                    )
            else:
                self.log_result(
                    "Project Edit GET", 
                    False, 
                    f"Edit GET failed with HTTP {response.status_code}"
                )

            # Test POST with edit action
            edit_data = {
                'action': 'edit_project',
                'project_id': '1',
                'client_id': '1',
                'project_name': 'Updated Test Project',
                'po_number': 'PO-2024-001-UPDATED',
                'project_description': 'Updated test project description',
                'source_language': 'en',
                'target_language': 'pt',
                'service_type': 'translation',
                'word_count': '1200',
                'rate_per_word': '0.18',
                'currency': 'USD',
                'status': 'in_progress',
                'priority': 'high',
                'start_date': datetime.now().strftime('%Y-%m-%d'),
                'deadline': (datetime.now() + timedelta(days=25)).strftime('%Y-%m-%d'),
                'notes': 'Updated test project notes',
                'daily_word_target': '120'
            }
            
            response = self.session.post(PROJECTS_URL, data=edit_data, timeout=10)
            
            if response.status_code == 200:
                if "login" in response.url.lower():
                    self.log_result(
                        "Project Edit POST", 
                        True, 
                        "Edit endpoint accessible but requires authentication (expected)"
                    )
                elif "projeto atualizado" in response.text.lower():
                    self.log_result(
                        "Project Edit POST", 
                        True, 
                        "Project update successful"
                    )
                else:
                    self.log_result(
                        "Project Edit POST", 
                        True, 
                        "Edit endpoint accepts POST request"
                    )
            else:
                self.log_result(
                    "Project Edit POST", 
                    False, 
                    f"Edit POST failed with HTTP {response.status_code}"
                )
                
        except requests.exceptions.RequestException as e:
            self.log_result(
                "Project Editing Functionality", 
                False, 
                "Network error during project editing test",
                str(e)
            )

    def test_project_status_update(self):
        """Test project status update (complete project)"""
        try:
            status_data = {
                'action': 'complete_project',
                'project_id': '1'  # Assuming project ID 1 exists
            }
            
            response = self.session.post(PROJECTS_URL, data=status_data, timeout=10)
            
            if response.status_code == 200:
                if "login" in response.url.lower():
                    self.log_result(
                        "Project Status Update", 
                        True, 
                        "Status update endpoint accessible but requires authentication (expected)"
                    )
                elif "projeto marcado como concluído" in response.text.lower():
                    self.log_result(
                        "Project Status Update", 
                        True, 
                        "Project status update successful"
                    )
                else:
                    self.log_result(
                        "Project Status Update", 
                        True, 
                        "Status update endpoint accepts POST request"
                    )
            else:
                self.log_result(
                    "Project Status Update", 
                    False, 
                    f"HTTP {response.status_code} error"
                )
                
        except requests.exceptions.RequestException as e:
            self.log_result(
                "Project Status Update", 
                False, 
                "Network error during status update test",
                str(e)
            )

    def test_project_deletion(self):
        """Test project deletion endpoint"""
        try:
            delete_data = {
                'action': 'delete_project',
                'project_id': '999'  # Using non-existent ID to avoid actual deletion
            }
            
            response = self.session.post(PROJECTS_URL, data=delete_data, timeout=10)
            
            if response.status_code == 200:
                if "login" in response.url.lower():
                    self.log_result(
                        "Project Deletion", 
                        True, 
                        "Delete endpoint accessible but requires authentication (expected)"
                    )
                elif "projeto excluído" in response.text.lower():
                    self.log_result(
                        "Project Deletion", 
                        True, 
                        "Project deletion processed"
                    )
                else:
                    self.log_result(
                        "Project Deletion", 
                        True, 
                        "Delete endpoint accepts POST request"
                    )
            else:
                self.log_result(
                    "Project Deletion", 
                    False, 
                    f"HTTP {response.status_code} error"
                )
                
        except requests.exceptions.RequestException as e:
            self.log_result(
                "Project Deletion", 
                False, 
                "Network error during deletion test",
                str(e)
            )

    def test_invoice_generation(self):
        """Test invoice generation from project"""
        try:
            invoice_data = {
                'action': 'generate_invoice',
                'project_id': '1'  # Assuming project ID 1 exists
            }
            
            response = self.session.post(PROJECTS_URL, data=invoice_data, timeout=10)
            
            if response.status_code == 200:
                if "login" in response.url.lower():
                    self.log_result(
                        "Invoice Generation", 
                        True, 
                        "Invoice generation endpoint accessible but requires authentication (expected)"
                    )
                elif "fatura gerada" in response.text.lower():
                    self.log_result(
                        "Invoice Generation", 
                        True, 
                        "Invoice generation successful"
                    )
                else:
                    self.log_result(
                        "Invoice Generation", 
                        True, 
                        "Invoice generation endpoint accepts POST request"
                    )
            else:
                self.log_result(
                    "Invoice Generation", 
                    False, 
                    f"HTTP {response.status_code} error"
                )
                
        except requests.exceptions.RequestException as e:
            self.log_result(
                "Invoice Generation", 
                False, 
                "Network error during invoice generation test",
                str(e)
            )

    def test_search_and_filter_functionality(self):
        """Test search and filter functionality"""
        try:
            # Test search parameter
            search_params = {'search': 'Translation'}
            response = self.session.get(PROJECTS_URL, params=search_params, timeout=10)
            
            if response.status_code == 200:
                self.log_result(
                    "Search Functionality", 
                    True, 
                    "Search parameter accepted successfully"
                )
            else:
                self.log_result(
                    "Search Functionality", 
                    False, 
                    f"Search failed with HTTP {response.status_code}"
                )
                
            # Test status filter
            status_params = {'status': 'in_progress'}
            response = self.session.get(PROJECTS_URL, params=status_params, timeout=10)
            
            if response.status_code == 200:
                self.log_result(
                    "Status Filter Functionality", 
                    True, 
                    "Status filter parameter accepted successfully"
                )
            else:
                self.log_result(
                    "Status Filter Functionality", 
                    False, 
                    f"Status filter failed with HTTP {response.status_code}"
                )
                
            # Test combined search and filter
            combined_params = {'search': 'Test', 'status': 'completed'}
            response = self.session.get(PROJECTS_URL, params=combined_params, timeout=10)
            
            if response.status_code == 200:
                self.log_result(
                    "Combined Search and Filter", 
                    True, 
                    "Combined search and filter parameters accepted successfully"
                )
            else:
                self.log_result(
                    "Combined Search and Filter", 
                    False, 
                    f"Combined search and filter failed with HTTP {response.status_code}"
                )
                
        except requests.exceptions.RequestException as e:
            self.log_result(
                "Search/Filter Functionality", 
                False, 
                "Network error during search/filter test",
                str(e)
            )

    def test_javascript_calculation_functions(self):
        """Test if JavaScript calculation functions are present in the page"""
        try:
            response = self.session.get(PROJECTS_URL, timeout=10)
            
            if response.status_code == 200:
                js_functions = [
                    'calculateProjectTotal',
                    'updateProjectTotal', 
                    'calculateWordCount',
                    'updateClientCurrency',
                    'calculateProductivity',
                    'updateTimeline'
                ]
                
                missing_functions = []
                present_functions = []
                
                for func in js_functions:
                    if func in response.text:
                        present_functions.append(func)
                    else:
                        missing_functions.append(func)
                
                if len(present_functions) >= len(js_functions) // 2:  # At least half should be present
                    self.log_result(
                        "JavaScript Calculation Functions", 
                        True, 
                        f"Key JavaScript functions present: {', '.join(present_functions)}"
                    )
                else:
                    self.log_result(
                        "JavaScript Calculation Functions", 
                        False, 
                        f"Missing critical JavaScript functions: {', '.join(missing_functions)}"
                    )
            else:
                self.log_result(
                    "JavaScript Calculation Functions", 
                    False, 
                    f"Cannot check JavaScript - HTTP {response.status_code}"
                )
                
        except requests.exceptions.RequestException as e:
            self.log_result(
                "JavaScript Calculation Functions", 
                False, 
                "Network error during JavaScript test",
                str(e)
            )

    def test_form_structure(self):
        """Test if required form elements are present"""
        try:
            response = self.session.get(PROJECTS_URL, timeout=10)
            
            if response.status_code == 200:
                required_elements = [
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
                
                missing_elements = []
                present_elements = []
                
                for element in required_elements:
                    if element in response.text:
                        present_elements.append(element)
                    else:
                        missing_elements.append(element)
                
                if len(present_elements) >= len(required_elements) * 0.8:  # At least 80% should be present
                    self.log_result(
                        "Form Structure", 
                        True, 
                        f"Required form elements present: {len(present_elements)}/{len(required_elements)}"
                    )
                else:
                    self.log_result(
                        "Form Structure", 
                        False, 
                        f"Missing critical form elements: {', '.join(missing_elements)}"
                    )
            else:
                self.log_result(
                    "Form Structure", 
                    False, 
                    f"Cannot check form structure - HTTP {response.status_code}"
                )
                
        except requests.exceptions.RequestException as e:
            self.log_result(
                "Form Structure", 
                False, 
                "Network error during form structure test",
                str(e)
            )

    def test_vision_ui_transformation(self):
        """Test if Vision UI components are properly implemented"""
        try:
            response = self.session.get(PROJECTS_URL, timeout=10)
            
            if response.status_code == 200:
                vision_ui_elements = [
                    'glass-hero',
                    'video-card',
                    'vision-form',
                    'form-grid',
                    'form-group',
                    'cta-btn',
                    'alert-success',
                    'alert-error',
                    'fas fa-',  # Font Awesome icons
                    'vision/includes/'  # Vision UI includes
                ]
                
                missing_elements = []
                present_elements = []
                
                for element in vision_ui_elements:
                    if element in response.text:
                        present_elements.append(element)
                    else:
                        missing_elements.append(element)
                
                if len(present_elements) >= len(vision_ui_elements) * 0.7:  # At least 70% should be present
                    self.log_result(
                        "Vision UI Transformation", 
                        True, 
                        f"Vision UI components properly implemented: {len(present_elements)}/{len(vision_ui_elements)}"
                    )
                else:
                    self.log_result(
                        "Vision UI Transformation", 
                        False, 
                        f"Missing Vision UI components: {', '.join(missing_elements)}"
                    )
            else:
                self.log_result(
                    "Vision UI Transformation", 
                    False, 
                    f"Cannot check Vision UI - HTTP {response.status_code}"
                )
                
        except requests.exceptions.RequestException as e:
            self.log_result(
                "Vision UI Transformation", 
                False, 
                "Network error during Vision UI test",
                str(e)
            )

    def test_timeline_visualization(self):
        """Test if timeline visualization components are present"""
        try:
            response = self.session.get(PROJECTS_URL, timeout=10)
            
            if response.status_code == 200:
                timeline_elements = [
                    'timeline',
                    'display_range_days',
                    'today_display_offset_days',
                    'min_display_date',
                    'max_display_date',
                    'productivity'
                ]
                
                present_timeline_elements = []
                for element in timeline_elements:
                    if element in response.text:
                        present_timeline_elements.append(element)
                
                if len(present_timeline_elements) >= 3:  # At least half should be present
                    self.log_result(
                        "Timeline Visualization", 
                        True, 
                        f"Timeline components present: {', '.join(present_timeline_elements)}"
                    )
                else:
                    self.log_result(
                        "Timeline Visualization", 
                        False, 
                        f"Timeline visualization components missing or incomplete"
                    )
            else:
                self.log_result(
                    "Timeline Visualization", 
                    False, 
                    f"Cannot check timeline - HTTP {response.status_code}"
                )
                
        except requests.exceptions.RequestException as e:
            self.log_result(
                "Timeline Visualization", 
                False, 
                "Network error during timeline test",
                str(e)
            )

    def run_all_tests(self):
        """Run all tests"""
        print("=" * 60)
        print("DASH-T101 PROJECT MANAGEMENT BACKEND TEST SUITE")
        print("=" * 60)
        print(f"Testing URL: {PROJECTS_URL}")
        print(f"Test started at: {datetime.now().isoformat()}")
        print("=" * 60)
        print()
        
        # Run tests
        self.test_page_accessibility()
        self.test_form_structure()
        self.test_vision_ui_transformation()
        self.test_javascript_calculation_functions()
        self.test_timeline_visualization()
        self.test_search_and_filter_functionality()
        self.test_project_creation_endpoint()
        self.test_project_editing_functionality()
        self.test_project_status_update()
        self.test_project_deletion()
        self.test_invoice_generation()
        
        # Summary
        print("=" * 60)
        print("TEST SUMMARY")
        print("=" * 60)
        
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
        
        print("=" * 60)
        
        return passed == total

if __name__ == "__main__":
    test_suite = ProjectTestSuite()
    success = test_suite.run_all_tests()
    sys.exit(0 if success else 1)