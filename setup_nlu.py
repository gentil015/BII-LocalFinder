#!/usr/bin/env python
"""
Master Setup Script for Multilingual NLU Service
Automates the entire setup process
"""

import os
import sys
import subprocess
from pathlib import Path

# Color codes
GREEN = '\033[92m'
RED = '\033[91m'
YELLOW = '\033[93m'
BLUE = '\033[94m'
END = '\033[0m'

class NLUSetup:
    def __init__(self):
        self.project_root = Path(__file__).parent
        self.ml_system_dir = self.project_root / 'ml-system'
        self.steps_completed = []
        self.errors = []
    
    def print_header(self, text):
        print(f"\n{BLUE}{'='*70}{END}")
        print(f"{BLUE}{text.center(70)}{END}")
        print(f"{BLUE}{'='*70}{END}\n")
    
    def print_step(self, step_num, text):
        print(f"{BLUE}[Step {step_num}]{END} {text}")
    
    def print_success(self, text):
        print(f"{GREEN}✓ {text}{END}")
    
    def print_error(self, text):
        print(f"{RED}✗ {text}{END}")
    
    def print_warning(self, text):
        print(f"{YELLOW}⚠ {text}{END}")
    
    def print_info(self, text):
        print(f"{BLUE}ℹ {text}{END}")
    
    def run_command(self, command, description=""):
        """Run a shell command and return success status"""
        try:
            if description:
                self.print_info(description)
            
            process = subprocess.Popen(
                command,
                stdout=subprocess.PIPE,
                stderr=subprocess.PIPE,
                shell=True,
                cwd=str(self.ml_system_dir)
            )
            
            stdout, stderr = process.communicate()
            
            if process.returncode == 0:
                return True, stdout.decode('utf-8', errors='ignore')
            else:
                error_msg = stderr.decode('utf-8', errors='ignore')
                self.errors.append(error_msg)
                return False, error_msg
        
        except Exception as e:
            self.errors.append(str(e))
            return False, str(e)
    
    def step_1_verify_environment(self):
        """Verify Python environment"""
        self.print_step(1, "Verifying Python Environment")
        
        # Check Python version
        python_version = f"{sys.version_info.major}.{sys.version_info.minor}"
        if sys.version_info.major >= 3 and sys.version_info.minor >= 8:
            self.print_success(f"Python {python_version} ✓")
        else:
            self.print_error(f"Python 3.8+ required (you have {python_version})")
            return False
        
        # Check pip
        success, output = self.run_command("pip --version", "Checking pip...")
        if success:
            self.print_success("pip is available ✓")
        else:
            self.print_error("pip is not available")
            return False
        
        return True
    
    def step_2_check_directories(self):
        """Check required directories exist"""
        self.print_step(2, "Checking Directory Structure")
        
        required_dirs = [
            self.ml_system_dir,
            self.ml_system_dir / 'data',
            self.ml_system_dir / 'models',
            self.ml_system_dir / 'api',
        ]
        
        for directory in required_dirs:
            if directory.exists():
                self.print_success(f"{directory.name} exists ✓")
            else:
                self.print_warning(f"{directory.name} does not exist")
                return False
        
        return True
    
    def step_3_install_requirements(self):
        """Install Python requirements"""
        self.print_step(3, "Installing Python Requirements")
        self.print_warning("This may take 5-10 minutes on first install...")
        
        success, output = self.run_command(
            "pip install -r requirements.txt",
            "Installing packages from requirements.txt..."
        )
        
        if success:
            self.print_success("All packages installed ✓")
            return True
        else:
            self.print_error("Failed to install requirements")
            self.print_warning("Error output:")
            print(output)
            return False
    
    def step_4_verify_dataset(self):
        """Verify training dataset exists"""
        self.print_step(4, "Verifying Training Dataset")
        
        dataset_path = self.ml_system_dir / 'data' / 'nlu_service_categories.json'
        
        if dataset_path.exists():
            self.print_success("Dataset found ✓")
            
            # Show dataset summary
            import json
            try:
                with open(dataset_path, 'r') as f:
                    data = json.load(f)
                
                categories = set(item['label'] for item in data)
                languages = set(item.get('language', 'unknown') for item in data)
                
                self.print_info(f"Total examples: {len(data)}")
                self.print_info(f"Categories: {', '.join(sorted(categories))}")
                self.print_info(f"Languages: {', '.join(sorted(languages))}")
                
                return True
            except Exception as e:
                self.print_error(f"Error reading dataset: {str(e)}")
                return False
        else:
            self.print_error("Dataset not found")
            return False
    
    def step_5_train_model(self):
        """Train the NLU model"""
        self.print_step(5, "Training NLU Model")
        self.print_warning("This will take 5-10 minutes depending on your hardware...")
        
        success, output = self.run_command(
            "python models/nlu_service_classifier.py",
            "Starting model training..."
        )
        
        if success:
            self.print_success("Model training completed ✓")
            
            # Check if model was saved
            model_dir = self.ml_system_dir / 'nlu_model'
            if model_dir.exists():
                self.print_success("Model saved successfully ✓")
                return True
            else:
                self.print_error("Model directory not found after training")
                return False
        else:
            self.print_error("Model training failed")
            print(output)
            return False
    
    def step_6_start_api_server(self, start_server=False):
        """Start the API server"""
        self.print_step(6, "API Server Configuration")
        
        api_file = self.ml_system_dir / 'api' / 'nlu_service.py'
        
        if api_file.exists():
            self.print_success("API server file found ✓")
        else:
            self.print_error("API server file not found")
            return False
        
        if start_server:
            self.print_info("Starting API server...")
            self.print_info("Server will be available at: http://localhost:8001")
            
            success, output = self.run_command(
                "python api/nlu_service.py",
                "Starting FastAPI server..."
            )
            
            if not success:
                self.print_error("Failed to start API server")
                print(output)
                return False
        else:
            self.print_info("To start the API server manually, run:")
            self.print_info("  cd ml-system")
            self.print_info("  python api/nlu_service.py")
        
        return True
    
    def step_7_test_api(self):
        """Test the API if it's running"""
        self.print_step(7, "Testing API (Optional)")
        
        try:
            import requests
            
            self.print_info("Attempting to connect to API...")
            response = requests.get('http://localhost:8001/health', timeout=5)
            
            if response.status_code == 200:
                self.print_success("API is running and responding ✓")
                
                data = response.json()
                if data.get('model_loaded'):
                    self.print_success("Model is loaded and ready ✓")
                else:
                    self.print_warning("Model is not loaded yet")
                
                return True
            else:
                self.print_warning("API returned unexpected status code")
                return False
        
        except requests.exceptions.ConnectionError:
            self.print_warning("API server not running (start it manually)")
            return True  # Not a setup failure
        except Exception as e:
            self.print_warning(f"Could not test API: {str(e)}")
            return True  # Not a setup failure
    
    def step_8_integration_setup(self):
        """Setup PHP integration"""
        self.print_step(8, "PHP Integration Setup")
        
        php_files = [
            self.project_root / 'includes' / 'NLUClient.php',
            self.project_root / 'includes' / 'NLUIntegration.php',
        ]
        
        all_exist = True
        for php_file in php_files:
            if php_file.exists():
                self.print_success(f"{php_file.name} exists ✓")
            else:
                self.print_warning(f"{php_file.name} not found")
                all_exist = False
        
        if all_exist:
            self.print_success("PHP integration files ready ✓")
            self.print_info("Usage in PHP:")
            self.print_info("  require_once 'includes/NLUClient.php';")
            self.print_info("  $nlu = new NLUClient('http://localhost:8001');")
            self.print_info("  $result = $nlu->classify('user text');")
        
        return all_exist
    
    def step_9_final_checks(self):
        """Final verification"""
        self.print_step(9, "Final Verification")
        
        model_dir = self.ml_system_dir / 'nlu_model'
        config_file = model_dir / 'config.json'
        label_mappings = model_dir / 'label_mappings.json'
        
        checks_passed = True
        
        if model_dir.exists():
            self.print_success("Model directory exists ✓")
        else:
            self.print_error("Model directory missing")
            checks_passed = False
        
        if config_file.exists():
            self.print_success("Model config exists ✓")
        else:
            self.print_warning("Model config file missing")
        
        if label_mappings.exists():
            self.print_success("Label mappings exist ✓")
        else:
            self.print_warning("Label mappings file missing")
        
        return checks_passed
    
    def print_completion_summary(self, success):
        """Print setup completion summary"""
        self.print_header("Setup Summary")
        
        if success:
            print(f"{GREEN}✓ Setup completed successfully!{END}\n")
            
            print(f"{YELLOW}Next Steps:{END}")
            print(f"1. Start the API server (if not already running):")
            print(f"   cd ml-system")
            print(f"   python api/nlu_service.py\n")
            
            print(f"2. Verify the API is running:")
            print(f"   curl http://localhost:8001/health\n")
            
            print(f"3. Test a prediction:")
            print(f"   curl -X POST http://localhost:8001/nlu \\")
            print(f"     -H \"Content-Type: application/json\" \\")
            print(f"     -d '{{\"text\": \"I need a plumber\"}}'\n")
            
            print(f"4. Use in PHP:")
            print(f"   require_once 'includes/NLUClient.php';")
            print(f"   $nlu = new NLUClient('http://localhost:8001');")
            print(f"   $result = $nlu->classify('user text');\n")
            
            print(f"5. View API documentation:")
            print(f"   http://localhost:8001/docs\n")
            
            print(f"{YELLOW}Documentation:{END}")
            print(f"• Setup Guide: QUICKSTART_NLU.md")
            print(f"• Full Docs: ml-system/NLU_README.md")
            print(f"• Deployment: DEPLOYMENT_NLU.md")
        else:
            print(f"{RED}✗ Setup encountered errors.{END}\n")
            
            if self.errors:
                print(f"{YELLOW}Errors:{END}")
                for error in self.errors[-5:]:  # Show last 5 errors
                    print(f"  {error}\n")
            
            print(f"{YELLOW}Troubleshooting:{END}")
            print(f"1. Check Python version: python --version")
            print(f"2. Check requirements: pip list")
            print(f"3. Review setup steps above")
    
    def run_full_setup(self, start_api=True):
        """Run complete setup"""
        self.print_header("NLU System Automatic Setup")
        print(f"Project Root: {self.project_root}\n")
        
        steps = [
            (1, self.step_1_verify_environment),
            (2, self.step_2_check_directories),
            (3, self.step_3_install_requirements),
            (4, self.step_4_verify_dataset),
            (5, self.step_5_train_model),
            (6, lambda: self.step_6_start_api_server(start_api)),
            (7, self.step_7_test_api),
            (8, self.step_8_integration_setup),
            (9, self.step_9_final_checks),
        ]
        
        success = True
        for step_num, step_func in steps:
            try:
                if not step_func():
                    success = False
                    # Continue with other steps
                else:
                    self.steps_completed.append(step_num)
            except KeyboardInterrupt:
                print(f"\n{YELLOW}Setup interrupted by user{END}")
                return False
            except Exception as e:
                self.print_error(f"Error in step {step_num}: {str(e)}")
                self.errors.append(str(e))
                success = False
        
        self.print_completion_summary(success)
        return success


def main():
    """Main entry point"""
    
    # Check if running in correct directory
    if not Path('ml-system').exists():
        print(f"{RED}Error: ml-system directory not found.{END}")
        print(f"Please run this script from the Bii_localFinder project root.")
        sys.exit(1)
    
    setup = NLUSetup()
    
    # Check for command line arguments
    start_api = '--no-api' not in sys.argv
    
    success = setup.run_full_setup(start_api=start_api)
    
    sys.exit(0 if success else 1)


if __name__ == '__main__':
    main()
