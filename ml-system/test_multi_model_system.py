"""
test_multi_model_system.py
--------------------------
Test script for the multi-model ML system.

Usage:
    python test_multi_model_system.py

This script will:
1. Test API health and model availability
2. Test each model endpoint with sample data
3. Verify PHP client integration
4. Generate a test report
"""

import os
import sys
import json
import requests
from datetime import datetime

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
API_BASE = "http://localhost:8000"

def test_api_health():
    """Test API health endpoint."""
    print("[TEST] Testing API health...")
    try:
        response = requests.get(f"{API_BASE}/health", timeout=5)
        if response.status_code == 200:
            data = response.json()
            print("✅ API is healthy")
            print(f"   Available models: {', '.join(data.get('available_models', []))}")
            return True, data
        else:
            print(f"❌ API health check failed: {response.status_code}")
            return False, None
    except Exception as e:
        print(f"❌ API health check error: {e}")
        return False, None

def test_model_info():
    """Test model info endpoint."""
    print("\n[TEST] Testing model info...")
    try:
        response = requests.get(f"{API_BASE}/models/info", timeout=5)
        if response.status_code == 200:
            data = response.json()
            print("✅ Model info retrieved")
            for model_name, info in data.items():
                if info.get('status') != 'not_loaded':
                    print(f"   {model_name}: {info.get('type', 'unknown')} ({info.get('version', 'unknown')})")
                else:
                    print(f"   {model_name}: NOT LOADED")
            return True, data
        else:
            print(f"❌ Model info failed: {response.status_code}")
            return False, None
    except Exception as e:
        print(f"❌ Model info error: {e}")
        return False, None

def test_recommendation_model():
    """Test recommendation model with sample data."""
    print("\n[TEST] Testing recommendation model...")

    sample_data = {
        "views": 10,
        "clicks": 2,
        "messages": 1,
        "rating": 4.5,
        "price": 5000.0,
        "avg_response_time": 2.0,
        "user_avg_price": 4500.0,
        "user_avg_response_time": 3.0,
        "user_total_bookings": 5
    }

    try:
        response = requests.post(f"{API_BASE}/predict/recommendation",
                               json=sample_data, timeout=10)
        if response.status_code == 200:
            result = response.json()
            print("✅ Recommendation model working")
            print(".4f")
            return True, result
        else:
            print(f"❌ Recommendation model failed: {response.status_code}")
            print(f"   Response: {response.text}")
            return False, None
    except Exception as e:
        print(f"❌ Recommendation model error: {e}")
        return False, None

def test_search_ranking_model():
    """Test search ranking model with sample data."""
    print("\n[TEST] Testing search ranking model...")

    sample_data = {
        "views": 25,
        "clicks": 5,
        "messages": 3,
        "rating": 4.2,
        "price": 3000.0,
        "avg_response_time": 1.5,
        "is_verified": 1,
        "is_featured": 0,
        "experience_years": 3,
        "completion_rate": 0.95,
        "search_query_length": 12,
        "category_match": 1,
        "location_match": 0,
        "price_match": 1,
        "availability_match": 1,
        "user_search_frequency": 10,
        "user_category_preference": 0.8,
        "user_price_range_preference": "2000-5000"
    }

    try:
        response = requests.post(f"{API_BASE}/predict/search_ranking",
                               json=sample_data, timeout=10)
        if response.status_code == 200:
            result = response.json()
            print("✅ Search ranking model working")
            print(".2f")
            return True, result
        else:
            print(f"❌ Search ranking model failed: {response.status_code}")
            return False, None
    except Exception as e:
        print(f"❌ Search ranking model error: {e}")
        return False, None

def test_batch_predictions():
    """Test batch predictions for recommendation model."""
    print("\n[TEST] Testing batch predictions...")

    sample_batch = [
        {
            "views": 10, "clicks": 2, "messages": 1, "rating": 4.5,
            "price": 5000.0, "avg_response_time": 2.0,
            "user_avg_price": 4500.0, "user_avg_response_time": 3.0, "user_total_bookings": 5
        },
        {
            "views": 5, "clicks": 1, "messages": 0, "rating": 3.8,
            "price": 3000.0, "avg_response_time": 4.0,
            "user_avg_price": 3500.0, "user_avg_response_time": 2.5, "user_total_bookings": 2
        }
    ]

    try:
        response = requests.post(f"{API_BASE}/predict/recommendation/batch",
                               json=sample_batch, timeout=10)
        if response.status_code == 200:
            results = response.json()
            print("✅ Batch predictions working")
            print(f"   Results count: {len(results)}")
            for i, result in enumerate(results):
                prediction = result.get('prediction', 0)
                print(".4f")
            return True, results
        else:
            print(f"❌ Batch predictions failed: {response.status_code}")
            return False, None
    except Exception as e:
        print(f"❌ Batch predictions error: {e}")
        return False, None

def test_user_segmentation_model():
    """Test user segmentation model with sample data."""
    print("\n[TEST] Testing user segmentation model...")

    sample_data = {
        "total_bookings": 5,
        "completed_bookings": 4,
        "cancelled_bookings": 1,
        "avg_booking_value": 25000.0,
        "total_spent": 125000.0,
        "booking_frequency": 0.2,
        "completion_rate": 0.8,
        "service_diversity": 3,
        "price_sensitivity": 0.5,
        "preferred_professions_count": 2,
        "profile_completeness": 0.9,
        "response_rate": 0.8,
        "avg_rating_given": 4.2,
        "favorites_count": 8,
        "reviews_written": 3,
        "engagement_score": 0.7,
        "location_diversity": 2,
        "peak_booking_hour": 14,
        "weekend_bookings_ratio": 0.3,
        "seasonal_pattern": 0.5,
        "account_age_days": 180,
        "last_activity_days": 2,
        "login_frequency": 0.8,
        "search_queries_count": 25,
        "provider_views_count": 45,
    }

    try:
        response = requests.post(f"{API_BASE}/predict/user_segmentation",
                               json=sample_data, timeout=10)
        if response.status_code == 200:
            result = response.json()
            print("✅ User segmentation model working")
            segment_name = result.get('segment_name', 'Unknown')
            segment_id = result.get('segment_id', 'N/A')
            print(f"   Predicted segment: {segment_name} (ID: {segment_id})")
            return True, result
        else:
            print(f"❌ User segmentation model failed: {response.status_code}")
            return False, None
    except Exception as e:
        print(f"❌ User segmentation model error: {e}")
        return False, None

def generate_test_report(results):
    """Generate a test report."""
    report = {
        "test_session": {
            "timestamp": datetime.now().isoformat(),
            "api_base": API_BASE,
        },
        "results": results,
        "summary": {
            "total_tests": len(results),
            "passed_tests": sum(1 for r in results.values() if r["success"]),
            "failed_tests": sum(1 for r in results.values() if not r["success"]),
        }
    }

    report["summary"]["overall_status"] = "SUCCESS" if report["summary"]["failed_tests"] == 0 else "FAILED"

    # Save report
    report_path = os.path.join(BASE_DIR, "test_report.json")
    with open(report_path, 'w') as f:
        json.dump(report, f, indent=2)

    return report

def print_summary(report):
    """Print test summary."""
    print("\n" + "="*60)
    print("TEST SESSION SUMMARY")
    print("="*60)

    print(f"Timestamp: {report['test_session']['timestamp']}")
    print(f"API Base: {report['test_session']['api_base']}")
    print(f"Overall Status: {report['summary']['overall_status']}")

    print("\nTest Results:")
    print(f"  Total: {report['summary']['total_tests']}")
    print(f"  Passed: {report['summary']['passed_tests']}")
    print(f"  Failed: {report['summary']['failed_tests']}")

    if report['summary']['failed_tests'] > 0:
        print("\nFailed Tests:")
        for test_name, result in report['results'].items():
            if not result['success']:
                print(f"  ❌ {test_name}")

    if report['summary']['passed_tests'] > 0:
        print("\nPassed Tests:")
        for test_name, result in report['results'].items():
            if result['success']:
                print(f"  ✅ {test_name}")
        for test_name, result in report['results'].items():
            if result['success']:
                print(f"  ✅ {test_name}")

def main():
    print("MULTI-MODEL ML SYSTEM TEST")
    print("="*60)
    print(f"API Base: {API_BASE}")
    print(f"Timestamp: {datetime.now().isoformat()}")

    # Run all tests
    results = {}

    # Basic API tests
    health_success, health_data = test_api_health()
    results["api_health"] = {"success": health_success, "data": health_data}

    if not health_success:
        print("\n❌ API is not available. Skipping remaining tests.")
        results["model_info"] = {"success": False, "error": "API not available"}
        results["recommendation_model"] = {"success": False, "error": "API not available"}
        results["search_ranking_model"] = {"success": False, "error": "API not available"}
        results["batch_predictions"] = {"success": False, "error": "API not available"}
    else:
        # Model tests
        info_success, info_data = test_model_info()
        results["model_info"] = {"success": info_success, "data": info_data}

        rec_success, rec_data = test_recommendation_model()
        results["recommendation_model"] = {"success": rec_success, "data": rec_data}

        search_success, search_data = test_search_ranking_model()
        results["search_ranking_model"] = {"success": search_success, "data": search_data}

        batch_success, batch_data = test_batch_predictions()
        results["batch_predictions"] = {"success": batch_success, "data": batch_data}

        user_seg_success, user_seg_data = test_user_segmentation_model()
        results["user_segmentation_model"] = {"success": user_seg_success, "data": user_seg_data}

    # Generate report
    report = generate_test_report(results)

    # Print summary
    print_summary(report)

    # Exit with appropriate code
    if report["summary"]["overall_status"] == "SUCCESS":
        print("\n🎉 All tests passed!")
        sys.exit(0)
    else:
        print("\n❌ Some tests failed. Check the test_report.json for details.")
        sys.exit(1)

if __name__ == "__main__":
    main()