"""
train_all_models.py
-------------------
Master script to train all ML models in the multi-model system.

Usage:
    python train_all_models.py

This script will:
1. Export fresh training data for all models
2. Train each model with its respective data
3. Save model bundles and evaluation results
4. Generate a training report
"""

import os
import sys
import json
import subprocess
from datetime import datetime
from pathlib import Path

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
ROOT_DIR = os.path.dirname(BASE_DIR)

def run_command(cmd, description):
    """Run a command and return success status."""
    print(f"\n[INFO] {description}")
    print(f"[CMD] {cmd}")

    try:
        env = os.environ.copy()
        env['PYTHONPATH'] = BASE_DIR
        result = subprocess.run(cmd, shell=True, cwd=BASE_DIR, capture_output=True, text=True, env=env)
        if result.returncode == 0:
            print(f"[SUCCESS] {description}")
            return True, result.stdout
        else:
            print(f"[ERROR] {description} failed:")
            print(result.stderr)
            return False, result.stderr
    except Exception as e:
        print(f"[ERROR] {description} failed: {e}")
        return False, str(e)

def export_data():
    """Export training data for all models."""
    print("\n" + "="*60)
    print("EXPORTING TRAINING DATA")
    print("="*60)

    data_exports = [
        ("data/export_recommendation_data.py", "Recommendation model data"),
        ("data/export_search_ranking_data.py", "Search ranking model data"),
        ("data/export_personalization_data.py", "Personalization model data"),
        ("data/export_provider_performance_data.py", "Provider performance model data"),
        ("data/export_service_performance_data.py", "Service performance model data"),
        ("data/export_user_engagement_data.py", "User engagement model data"),
        ("data/export_user_segmentation_data.py", "User segmentation model data"),
    ]

    results = {}
    for script, description in data_exports:
        success, output = run_command(f"python {script}", f"Exporting {description}")
        results[script] = {"success": success, "description": description}

    return results

def train_models():
    """Train all ML models."""
    print("\n" + "="*60)
    print("TRAINING ML MODELS")
    print("="*60)

    model_training = [
        ("models/recommendation/train_recommendation_model.py", "Recommendation Model"),
        ("models/search_ranking/train_search_ranking_model.py", "Search Ranking Model"),
        ("models/personalization/train_personalization_model.py", "Personalization Model"),
        ("models/provider_performance/train_provider_performance_model.py", "Provider Performance Model"),
        ("models/service_performance/train_service_performance_model.py", "Service Performance Model"),
        ("models/user_engagement/train_user_engagement_model.py", "User Engagement Model"),
        ("models/user_segmentation/train_user_segmentation_model.py", "User Segmentation Model"),
    ]

    results = {}
    for script, description in model_training:
        success, output = run_command(f"python {script}", f"Training {description}")
        results[script] = {"success": success, "description": description}

    return results

def generate_report(data_results, training_results):
    """Generate a training report."""
    report = {
        "training_session": {
            "timestamp": datetime.now().isoformat(),
            "base_directory": BASE_DIR,
        },
        "data_export": {
            "total_scripts": len(data_results),
            "successful_exports": sum(1 for r in data_results.values() if r["success"]),
            "failed_exports": sum(1 for r in data_results.values() if not r["success"]),
            "details": data_results
        },
        "model_training": {
            "total_models": len(training_results),
            "successful_training": sum(1 for r in training_results.values() if r["success"]),
            "failed_training": sum(1 for r in training_results.values() if not r["success"]),
            "details": training_results
        }
    }

    # Overall status
    data_success = report["data_export"]["failed_exports"] == 0
    training_success = report["model_training"]["failed_training"] == 0
    report["overall_status"] = "SUCCESS" if (data_success and training_success) else "PARTIAL_SUCCESS" if (data_success or training_success) else "FAILED"

    # Save report
    report_path = os.path.join(BASE_DIR, "training_report.json")
    with open(report_path, 'w') as f:
        json.dump(report, f, indent=2)

    print(f"\n[INFO] Training report saved to: {report_path}")

    return report

def print_summary(report):
    """Print a summary of the training session."""
    print("\n" + "="*60)
    print("TRAINING SESSION SUMMARY")
    print("="*60)

    print(f"Timestamp: {report['training_session']['timestamp']}")
    print(f"Overall Status: {report['overall_status']}")

    print("\nData Export:")
    print(f"  Total: {report['data_export']['total_scripts']}")
    print(f"  Successful: {report['data_export']['successful_exports']}")
    print(f"  Failed: {report['data_export']['failed_exports']}")

    print("\nModel Training:")
    print(f"  Total: {report['model_training']['total_models']}")
    print(f"  Successful: {report['model_training']['successful_training']}")
    print(f"  Failed: {report['model_training']['failed_training']}")

    if report['data_export']['failed_exports'] > 0:
        print("\nFailed Data Exports:")
        for script, result in report['data_export']['details'].items():
            if not result['success']:
                print(f"  - {result['description']}")

    if report['model_training']['failed_training'] > 0:
        print("\nFailed Model Training:")
        for script, result in report['model_training']['details'].items():
            if not result['success']:
                print(f"  - {result['description']}")

def main():
    print("MULTI-MODEL ML SYSTEM TRAINING")
    print("="*60)
    print(f"Base Directory: {BASE_DIR}")
    print(f"Timestamp: {datetime.now().isoformat()}")

    # Check if we're in the right directory
    if not os.path.exists(os.path.join(BASE_DIR, "requirements.txt")):
        sys.exit("[ERROR] Please run this script from the ml-system directory")

    # Export training data
    data_results = export_data()

    # Train models
    training_results = train_models()

    # Generate report
    report = generate_report(data_results, training_results)

    # Print summary
    print_summary(report)

    # Exit with appropriate code
    if report["overall_status"] == "SUCCESS":
        print("\n🎉 All models trained successfully!")
        sys.exit(0)
    elif report["overall_status"] == "PARTIAL_SUCCESS":
        print("\n⚠️  Some models trained successfully, but there were failures.")
        sys.exit(1)
    else:
        print("\n❌ Training failed. Check the logs above for details.")
        sys.exit(1)

if __name__ == "__main__":
    main()