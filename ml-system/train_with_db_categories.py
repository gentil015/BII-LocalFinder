"""
Updated NLU Training Script that uses Database Categories
Trains on admin-created categories from MySQL database
"""

import json
import sys
from pathlib import Path

# Add parent directory to path
sys.path.insert(0, str(Path(__file__).parent))

from db_config import get_db_instance
from models.nlu_service_classifier import MultilingualNLUClassifier
import logging

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)


def train_nlu_with_db_categories(output_dir: str = './nlu_model'):
    """
    Train NLU model using categories from database
    
    Args:
        output_dir: Directory to save trained model
    """
    try:
        # Connect to database
        logger.info("Connecting to database...")
        db = get_db_instance()
        
        # Get categories from database
        logger.info("Fetching categories from database...")
        categories = db.get_categories(ai_enabled_only=True)
        
        if not categories:
            logger.error("No AI-enabled categories found in database!")
            db.close()
            return False
        
        category_names = [cat['name'] for cat in categories]
        logger.info(f"Found {len(category_names)} AI-enabled categories: {category_names}")
        
        # Generate training dataset from database
        logger.info("Generating training dataset from categories...")
        training_data, _ = db.generate_training_dataset(
            output_path=str(Path(__file__).parent / 'data' / 'nlu_service_categories.json')
        )
        
        if not training_data:
            logger.error("No training data generated!")
            db.close()
            return False
        
        db.close()
        
        # Extract texts and labels
        texts = [item['text'] for item in training_data]
        labels = [item['label'] for item in training_data]
        
        # Initialize classifier
        logger.info("Initializing NLU classifier...")
        classifier = MultilingualNLUClassifier(output_dir=output_dir)
        
        # Prepare data
        logger.info("Preparing training data...")
        train_texts, val_texts, train_labels, val_labels = classifier.prepare_data(texts, labels)
        
        # Setup model
        num_labels = len(set(labels))
        logger.info(f"Setting up model with {num_labels} labels...")
        classifier.setup_model(num_labels=num_labels)
        
        # Train model
        logger.info("Starting model training...")
        classifier.train(train_texts, train_labels, val_texts, val_labels, 
                        epochs=3, batch_size=16, learning_rate=2e-5)
        
        # Save model
        logger.info("Saving trained model...")
        classifier.save_model()
        
        logger.info("✓ Model training completed successfully!")
        
        # Test predictions
        logger.info("\nTesting predictions...")
        test_samples = [
            "I need an electrician to fix my wiring",
            "Ndashaka umuntu yishyura inzira z'amazi",
            "Can you find me a good plumber?"
        ]
        
        for sample in test_samples:
            prediction = classifier.predict(sample)
            logger.info(f"  '{sample}' -> {prediction['label']} ({prediction['score']:.2%})")
        
        return True
        
    except Exception as e:
        logger.error(f"Error during training: {e}")
        import traceback
        traceback.print_exc()
        return False


if __name__ == '__main__':
    success = train_nlu_with_db_categories()
    sys.exit(0 if success else 1)
