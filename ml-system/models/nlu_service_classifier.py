"""
Multilingual NLU Model for Service Category Classification
Uses XLM-RoBERTa for English and Kinyarwanda support
"""

import json
import torch
import numpy as np
from pathlib import Path
from typing import List, Dict, Tuple
import logging

from sklearn.preprocessing import LabelEncoder
from torch.utils.data import Dataset, DataLoader, train_test_split
from transformers import (
    AutoTokenizer,
    AutoModelForSequenceClassification,
    AdamW,
    get_linear_schedule_with_warmup
)
from tqdm import tqdm

# Setup logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)


class ServiceCategoryDataset(Dataset):
    """Custom Dataset for service category classification"""
    
    def __init__(self, texts: List[str], labels: List[int], tokenizer, max_length: int = 128):
        self.texts = texts
        self.labels = labels
        self.tokenizer = tokenizer
        self.max_length = max_length
    
    def __len__(self):
        return len(self.texts)
    
    def __getitem__(self, idx):
        text = self.texts[idx]
        label = self.labels[idx]
        
        encoding = self.tokenizer(
            text,
            max_length=self.max_length,
            padding='max_length',
            truncation=True,
            return_tensors='pt'
        )
        
        return {
            'input_ids': encoding['input_ids'].squeeze(0),
            'attention_mask': encoding['attention_mask'].squeeze(0),
            'labels': torch.tensor(label, dtype=torch.long)
        }


class MultilingualNLUClassifier:
    """Multilingual NLU classifier using XLM-RoBERTa"""
    
    def __init__(self, 
                 model_name: str = 'xlm-roberta-base',
                 output_dir: str = './nlu_model',
                 device: str = None):
        """
        Initialize the classifier
        
        Args:
            model_name: Base model from Hugging Face
            output_dir: Directory to save trained model
            device: Device to use ('cuda' or 'cpu')
        """
        self.model_name = model_name
        self.output_dir = Path(output_dir)
        self.output_dir.mkdir(parents=True, exist_ok=True)
        
        self.device = device or ('cuda' if torch.cuda.is_available() else 'cpu')
        logger.info(f"Using device: {self.device}")
        
        self.tokenizer = None
        self.model = None
        self.label_encoder = LabelEncoder()
        self.id2label = {}
        self.label2id = {}
    
    def load_dataset(self, json_path: str) -> Tuple[List[str], List[str]]:
        """
        Load dataset from JSON file
        
        Args:
            json_path: Path to JSON dataset file
            
        Returns:
            Tuple of (texts, labels)
        """
        logger.info(f"Loading dataset from {json_path}")
        
        with open(json_path, 'r', encoding='utf-8') as f:
            data = json.load(f)
        
        texts = [item['text'] for item in data]
        labels = [item['label'] for item in data]
        
        logger.info(f"Loaded {len(texts)} examples")
        logger.info(f"Categories: {sorted(set(labels))}")
        
        return texts, labels
    
    def prepare_data(self, 
                    texts: List[str], 
                    labels: List[str],
                    test_size: float = 0.2,
                    random_state: int = 42):
        """
        Prepare data for training
        
        Args:
            texts: List of input texts
            labels: List of category labels
            test_size: Proportion of data for testing
            random_state: Random seed
        """
        # Encode labels
        self.label_encoder.fit(labels)
        label_ids = self.label_encoder.transform(labels)
        
        # Create label mappings
        self.id2label = {i: label for i, label in enumerate(self.label_encoder.classes_)}
        self.label2id = {label: i for i, label in enumerate(self.label_encoder.classes_)}
        
        logger.info(f"Label mappings: {self.label2id}")
        
        # Split data
        train_texts, val_texts, train_labels, val_labels = train_test_split(
            texts, label_ids, test_size=test_size, random_state=random_state, stratify=label_ids
        )
        
        return train_texts, val_texts, train_labels, val_labels
    
    def setup_model(self, num_labels: int):
        """
        Setup tokenizer and model
        
        Args:
            num_labels: Number of classification labels
        """
        logger.info(f"Loading tokenizer and model: {self.model_name}")
        
        self.tokenizer = AutoTokenizer.from_pretrained(self.model_name)
        
        self.model = AutoModelForSequenceClassification.from_pretrained(
            self.model_name,
            num_labels=num_labels,
            id2label=self.id2label,
            label2id=self.label2id
        )
        
        self.model.to(self.device)
        logger.info("Model loaded successfully")
    
    def train(self,
             train_texts: List[str],
             train_labels: List[int],
             val_texts: List[str],
             val_labels: List[int],
             epochs: int = 3,
             batch_size: int = 16,
             learning_rate: float = 2e-5):
        """
        Train the model
        
        Args:
            train_texts: Training texts
            train_labels: Training labels
            val_texts: Validation texts
            val_labels: Validation labels
            epochs: Number of training epochs
            batch_size: Batch size
            learning_rate: Learning rate
        """
        logger.info("Preparing datasets")
        
        # Create datasets
        train_dataset = ServiceCategoryDataset(train_texts, train_labels, self.tokenizer)
        val_dataset = ServiceCategoryDataset(val_texts, val_labels, self.tokenizer)
        
        # Create dataloaders
        train_loader = DataLoader(train_dataset, batch_size=batch_size, shuffle=True)
        val_loader = DataLoader(val_dataset, batch_size=batch_size)
        
        # Setup optimizer and scheduler
        optimizer = AdamW(self.model.parameters(), lr=learning_rate)
        total_steps = len(train_loader) * epochs
        scheduler = get_linear_schedule_with_warmup(
            optimizer,
            num_warmup_steps=0,
            num_training_steps=total_steps
        )
        
        logger.info(f"Starting training for {epochs} epochs")
        
        for epoch in range(epochs):
            logger.info(f"\n=== Epoch {epoch + 1}/{epochs} ===")
            
            # Training phase
            self.model.train()
            train_loss = 0
            
            for batch in tqdm(train_loader, desc="Training"):
                optimizer.zero_grad()
                
                input_ids = batch['input_ids'].to(self.device)
                attention_mask = batch['attention_mask'].to(self.device)
                labels = batch['labels'].to(self.device)
                
                outputs = self.model(
                    input_ids=input_ids,
                    attention_mask=attention_mask,
                    labels=labels
                )
                
                loss = outputs.loss
                train_loss += loss.item()
                
                loss.backward()
                torch.nn.utils.clip_grad_norm_(self.model.parameters(), 1.0)
                optimizer.step()
                scheduler.step()
            
            avg_train_loss = train_loss / len(train_loader)
            logger.info(f"Training loss: {avg_train_loss:.4f}")
            
            # Validation phase
            val_loss, val_accuracy = self.evaluate(val_loader)
            logger.info(f"Validation loss: {val_loss:.4f}, Accuracy: {val_accuracy:.4f}")
    
    def evaluate(self, data_loader) -> Tuple[float, float]:
        """
        Evaluate the model
        
        Args:
            data_loader: DataLoader for evaluation
            
        Returns:
            Tuple of (loss, accuracy)
        """
        self.model.eval()
        total_loss = 0
        correct_predictions = 0
        total_predictions = 0
        
        with torch.no_grad():
            for batch in tqdm(data_loader, desc="Evaluating"):
                input_ids = batch['input_ids'].to(self.device)
                attention_mask = batch['attention_mask'].to(self.device)
                labels = batch['labels'].to(self.device)
                
                outputs = self.model(
                    input_ids=input_ids,
                    attention_mask=attention_mask,
                    labels=labels
                )
                
                loss = outputs.loss
                total_loss += loss.item()
                
                predictions = torch.argmax(outputs.logits, dim=1)
                correct_predictions += (predictions == labels).sum().item()
                total_predictions += labels.size(0)
        
        avg_loss = total_loss / len(data_loader)
        accuracy = correct_predictions / total_predictions
        
        return avg_loss, accuracy
    
    def predict(self, text: str) -> Dict:
        """
        Make prediction for a single text
        
        Args:
            text: Input text
            
        Returns:
            Dictionary with label and confidence score
        """
        self.model.eval()
        
        encoding = self.tokenizer(
            text,
            max_length=128,
            padding='max_length',
            truncation=True,
            return_tensors='pt'
        )
        
        input_ids = encoding['input_ids'].to(self.device)
        attention_mask = encoding['attention_mask'].to(self.device)
        
        with torch.no_grad():
            outputs = self.model(input_ids=input_ids, attention_mask=attention_mask)
        
        logits = outputs.logits
        probabilities = torch.softmax(logits, dim=-1)
        predicted_label_id = torch.argmax(probabilities, dim=-1).item()
        confidence_score = probabilities[0][predicted_label_id].item()
        
        predicted_label = self.id2label[predicted_label_id]
        
        return {
            'label': predicted_label,
            'score': float(confidence_score),
            'text': text
        }
    
    def predict_batch(self, texts: List[str]) -> List[Dict]:
        """
        Make predictions for multiple texts
        
        Args:
            texts: List of input texts
            
        Returns:
            List of predictions
        """
        predictions = []
        for text in texts:
            pred = self.predict(text)
            predictions.append(pred)
        return predictions
    
    def save_model(self):
        """Save the trained model and tokenizer"""
        logger.info(f"Saving model to {self.output_dir}")
        
        self.model.save_pretrained(self.output_dir)
        self.tokenizer.save_pretrained(self.output_dir)
        
        # Save label mappings
        mappings = {
            'id2label': self.id2label,
            'label2id': self.label2id
        }
        with open(self.output_dir / 'label_mappings.json', 'w') as f:
            json.dump(mappings, f, indent=2)
        
        logger.info("Model saved successfully")
    
    def load_model(self):
        """Load a previously trained model"""
        logger.info(f"Loading model from {self.output_dir}")
        
        self.tokenizer = AutoTokenizer.from_pretrained(self.output_dir)
        self.model = AutoModelForSequenceClassification.from_pretrained(self.output_dir)
        self.model.to(self.device)
        
        # Load label mappings
        with open(self.output_dir / 'label_mappings.json', 'r') as f:
            mappings = json.load(f)
            self.id2label = {int(k): v for k, v in mappings['id2label'].items()}
            self.label2id = mappings['label2id']
        
        logger.info("Model loaded successfully")


def main():
    """Main training script"""
    
    # Configuration
    DATASET_PATH = './data/nlu_service_categories.json'
    MODEL_OUTPUT_DIR = './nlu_model'
    BASE_MODEL = 'xlm-roberta-base'
    EPOCHS = 3
    BATCH_SIZE = 16
    LEARNING_RATE = 2e-5
    
    # Initialize classifier
    classifier = MultilingualNLUClassifier(
        model_name=BASE_MODEL,
        output_dir=MODEL_OUTPUT_DIR
    )
    
    # Load dataset
    texts, labels = classifier.load_dataset(DATASET_PATH)
    
    # Prepare data
    train_texts, val_texts, train_labels, val_labels = classifier.prepare_data(
        texts, labels
    )
    
    # Setup model
    num_labels = len(set(labels))
    classifier.setup_model(num_labels)
    
    # Train model
    classifier.train(
        train_texts=train_texts,
        train_labels=train_labels,
        val_texts=val_texts,
        val_labels=val_labels,
        epochs=EPOCHS,
        batch_size=BATCH_SIZE,
        learning_rate=LEARNING_RATE
    )
    
    # Save model
    classifier.save_model()
    
    # Test predictions
    logger.info("\n=== Testing Predictions ===")
    test_inputs = [
        "I need a plumber to fix my pipes",
        "Ndashaka electrician",
        "I need someone to clean my house",
        "Ndashaka umuntu yomurika inzu",
        "Can you send a painter"
    ]
    
    predictions = classifier.predict_batch(test_inputs)
    for pred in predictions:
        logger.info(f"Text: {pred['text']}")
        logger.info(f"Label: {pred['label']}, Score: {pred['score']:.4f}\n")
    
    logger.info("Training completed successfully!")


if __name__ == '__main__':
    main()
