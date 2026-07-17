#!/usr/bin/env python
"""
Comprehensive Testing Suite for Multilingual NLU Service
Tests training, inference, and API endpoints
"""

import sys
import json
import time
import requests
from pathlib import Path

# Color codes for terminal output
GREEN = '\033[92m'
RED = '\033[91m'
YELLOW = '\033[93m'
BLUE = '\033[94m'
END = '\033[0m'

class NLUTester:
    def __init__(self, api_url='http://localhost:8001'):
        self.api_url = api_url
        self.passed = 0
        self.failed = 0
        self.results = []
    
    def print_header(self, text):
        print(f"\n{BLUE}{'='*60}{END}")
        print(f"{BLUE}{text}{END}")
        print(f"{BLUE}{'='*60}{END}")
    
    def print_success(self, message):
        print(f"{GREEN}✓ {message}{END}")
        self.passed += 1
    
    def print_failure(self, message):
        print(f"{RED}✗ {message}{END}")
        self.failed += 1
    
    def print_info(self, message):
        print(f"{YELLOW}ℹ {message}{END}")
    
    def test_connection(self):
        """Test if API server is running"""
        self.print_header("1. Testing API Connection")
        
        try:
            response = requests.get(f"{self.api_url}/health", timeout=5)
            if response.status_code == 200:
                data = response.json()
                if data.get('status') == 'healthy':
                    self.print_success("API server is running and healthy")
                    if data.get('model_loaded'):
                        self.print_success("Model is loaded")
                    else:
                        self.print_info("Model not loaded - run training first")
                    print(f"  Device: {data.get('device', 'unknown')}")
                    return True
            else:
                self.print_failure(f"API returned status code {response.status_code}")
                return False
        except requests.exceptions.ConnectionError:
            self.print_failure(f"Cannot connect to API at {self.api_url}")
            self.print_info("Make sure FastAPI server is running: python api/nlu_service.py")
            return False
        except Exception as e:
            self.print_failure(f"Connection test failed: {str(e)}")
            return False
    
    def test_single_classification(self):
        """Test single text classification"""
        self.print_header("2. Testing Single Classification")
        
        test_cases = [
            ("I need a plumber to fix my pipes", "plumber", "en"),
            ("Can you send an electrician", "electrician", "en"),
            ("I need someone to clean my house", "cleaner", "en"),
            ("Ndashaka electrician", "electrician", "rw"),
            ("Ndashaka umuntu yomurika inzu", "cleaner", "rw"),
        ]
        
        for text, expected_label, language in test_cases:
            try:
                payload = {
                    "text": text,
                    "language": language
                }
                
                response = requests.post(
                    f"{self.api_url}/nlu",
                    json=payload,
                    timeout=30
                )
                
                if response.status_code == 200:
                    data = response.json()
                    predicted_label = data.get('label')
                    score = data.get('score', 0)
                    
                    if predicted_label == expected_label:
                        self.print_success(
                            f'"{text}" → {predicted_label} ({score:.2%})'
                        )
                    else:
                        self.print_failure(
                            f'"{text}" → Expected: {expected_label}, Got: {predicted_label}'
                        )
                else:
                    self.print_failure(f"Request failed with status {response.status_code}")
            
            except requests.exceptions.Timeout:
                self.print_failure(f'Request timeout for: "{text}"')
            except Exception as e:
                self.print_failure(f'Error testing "{text}": {str(e)}')
    
    def test_batch_classification(self):
        """Test batch classification endpoint"""
        self.print_header("3. Testing Batch Classification")
        
        texts = [
            "I need a plumber",
            "Can you send a painter",
            "Ndashaka carpenter",
        ]
        
        try:
            payload = {
                "texts": texts
            }
            
            response = requests.post(
                f"{self.api_url}/nlu/batch",
                json=payload,
                timeout=30
            )
            
            if response.status_code == 200:
                data = response.json()
                processed = data.get('processed', 0)
                total = data.get('total', 0)
                
                if processed == len(texts):
                    self.print_success(f"Processed {processed}/{total} texts")
                    
                    for pred in data.get('predictions', []):
                        print(f"  • {pred['text']} → {pred['label']} ({pred['score']:.2%})")
                else:
                    self.print_failure(f"Only processed {processed}/{total} texts")
            else:
                self.print_failure(f"Batch request failed with status {response.status_code}")
        
        except Exception as e:
            self.print_failure(f"Batch classification error: {str(e)}")
    
    def test_categories_endpoint(self):
        """Test getting available categories"""
        self.print_header("4. Testing Categories Endpoint")
        
        try:
            response = requests.get(
                f"{self.api_url}/categories",
                timeout=10
            )
            
            if response.status_code == 200:
                data = response.json()
                categories = data.get('categories', [])
                
                if len(categories) > 0:
                    self.print_success(f"Found {len(categories)} service categories")
                    for cat in categories:
                        print(f"  • {cat}")
                else:
                    self.print_failure("No categories found")
            else:
                self.print_failure(f"Categories request failed with status {response.status_code}")
        
        except Exception as e:
            self.print_failure(f"Categories endpoint error: {str(e)}")
    
    def test_model_info(self):
        """Test model info endpoint"""
        self.print_header("5. Testing Model Info Endpoint")
        
        try:
            response = requests.get(
                f"{self.api_url}/model/info",
                timeout=10
            )
            
            if response.status_code == 200:
                data = response.json()
                self.print_success(f"Model: {data.get('model_name')}")
                self.print_info(f"Device: {data.get('device')}")
                self.print_info(f"Labels: {data.get('num_labels')}")
            else:
                self.print_failure(f"Model info request failed with status {response.status_code}")
        
        except Exception as e:
            self.print_failure(f"Model info error: {str(e)}")
    
    def test_performance(self):
        """Test inference performance"""
        self.print_header("6. Testing Inference Performance")
        
        test_text = "I need a plumber to fix my pipes"
        iterations = 5
        times = []
        
        try:
            self.print_info(f"Running {iterations} inference iterations...")
            
            for i in range(iterations):
                start = time.time()
                
                response = requests.post(
                    f"{self.api_url}/nlu",
                    json={"text": test_text},
                    timeout=30
                )
                
                elapsed = (time.time() - start) * 1000  # Convert to ms
                times.append(elapsed)
                
                if response.status_code == 200:
                    print(f"  Iteration {i+1}: {elapsed:.1f}ms")
                else:
                    self.print_failure(f"Inference failed on iteration {i+1}")
            
            if times:
                avg_time = sum(times) / len(times)
                min_time = min(times)
                max_time = max(times)
                
                self.print_success(f"Average inference time: {avg_time:.1f}ms")
                self.print_info(f"Min: {min_time:.1f}ms, Max: {max_time:.1f}ms")
                
                # Performance assessment
                if avg_time < 100:
                    self.print_success("Excellent performance (< 100ms)")
                elif avg_time < 200:
                    self.print_success("Good performance (< 200ms)")
                elif avg_time < 500:
                    self.print_info("Acceptable performance (< 500ms)")
                else:
                    self.print_failure("Slow inference (> 500ms)")
        
        except Exception as e:
            self.print_failure(f"Performance test error: {str(e)}")
    
    def test_error_handling(self):
        """Test error handling"""
        self.print_header("7. Testing Error Handling")
        
        # Test empty text
        try:
            response = requests.post(
                f"{self.api_url}/nlu",
                json={"text": ""},
                timeout=10
            )
            
            if response.status_code == 400:
                self.print_success("Empty text validation works")
            else:
                self.print_failure("Empty text should return 400 error")
        except Exception as e:
            self.print_failure(f"Empty text test error: {str(e)}")
        
        # Test invalid request
        try:
            response = requests.post(
                f"{self.api_url}/nlu",
                json={"invalid": "data"},
                timeout=10
            )
            
            if response.status_code in [400, 422]:
                self.print_success("Invalid request validation works")
            else:
                self.print_failure("Invalid request should return 400/422")
        except Exception as e:
            self.print_failure(f"Invalid request test error: {str(e)}")
        
        # Test batch limit
        try:
            payload = {
                "texts": ["test"] * 101  # More than 100
            }
            response = requests.post(
                f"{self.api_url}/nlu/batch",
                json=payload,
                timeout=10
            )
            
            if response.status_code == 400:
                self.print_success("Batch size limit is enforced")
            else:
                self.print_failure("Batch limit should be enforced")
        except Exception as e:
            self.print_failure(f"Batch limit test error: {str(e)}")
    
    def print_summary(self):
        """Print test summary"""
        self.print_header("Test Summary")
        
        total = self.passed + self.failed
        
        print(f"{GREEN}Passed: {self.passed}/{total}{END}")
        print(f"{RED}Failed: {self.failed}/{total}{END}")
        
        if self.failed == 0:
            print(f"\n{GREEN}All tests passed! ✓{END}")
        else:
            print(f"\n{YELLOW}Some tests failed. Please check the errors above.{END}")
        
        success_rate = (self.passed / total * 100) if total > 0 else 0
        print(f"\nSuccess Rate: {success_rate:.1f}%")
    
    def run_all_tests(self):
        """Run all tests"""
        self.print_header("NLU Service Test Suite")
        print(f"API URL: {self.api_url}")
        
        # Check connection first
        if not self.test_connection():
            self.print_failure("API is not running. Cannot proceed with tests.")
            return
        
        # Run all tests
        self.test_single_classification()
        self.test_batch_classification()
        self.test_categories_endpoint()
        self.test_model_info()
        self.test_performance()
        self.test_error_handling()
        
        # Print summary
        self.print_summary()


if __name__ == '__main__':
    api_url = sys.argv[1] if len(sys.argv) > 1 else 'http://localhost:8001'
    
    tester = NLUTester(api_url)
    tester.run_all_tests()
