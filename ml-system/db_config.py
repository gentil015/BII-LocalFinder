"""
Database configuration for NLU system
Connects to MySQL database to load categories and training data
"""

import mysql.connector
from mysql.connector import Error
import json
from typing import List, Dict, Tuple
import logging

logger = logging.getLogger(__name__)


class NLUDatabase:
    """Database handler for NLU categories and data"""
    
    def __init__(self, 
                 host: str = '127.0.0.1',
                 user: str = 'gentil',
                 password: str = 'Dushime330805',
                 database: str = 'bii_localfinder',
                 port: int = 3306):
        """
        Initialize database connection
        
        Args:
            host: MySQL host
            user: MySQL user
            password: MySQL password
            database: Database name
            port: MySQL port
        """
        self.host = host
        self.user = user
        self.password = password
        self.database = database
        self.port = port
        self.connection = None
        self.connect()
    
    def connect(self):
        """Establish database connection"""
        try:
            self.connection = mysql.connector.connect(
                host=self.host,
                user=self.user,
                password=self.password,
                database=self.database,
                port=self.port
            )
            logger.info(f"Connected to database: {self.database}")
        except Error as e:
            logger.error(f"Database connection failed: {e}")
            raise
    
    def get_categories(self, ai_enabled_only: bool = True) -> List[Dict]:
        """
        Get all categories from database
        
        Args:
            ai_enabled_only: Only get AI-enabled categories
            
        Returns:
            List of category dictionaries
        """
        try:
            cursor = self.connection.cursor(dictionary=True)
            
            if ai_enabled_only:
                query = "SELECT id, name, icon, description, ai_keywords FROM categories WHERE is_ai_enabled = 1 ORDER BY name"
            else:
                query = "SELECT id, name, icon, description, ai_keywords FROM categories ORDER BY name"
            
            cursor.execute(query)
            categories = cursor.fetchall()
            cursor.close()
            
            logger.info(f"Loaded {len(categories)} categories from database")
            return categories
        except Error as e:
            logger.error(f"Error fetching categories: {e}")
            return []
    
    def get_training_data(self, category_id: int = None) -> List[Dict]:
        """
        Get training data from nlu_classifications table
        
        Args:
            category_id: Optional category filter
            
        Returns:
            List of training examples
        """
        try:
            cursor = self.connection.cursor(dictionary=True)
            
            if category_id:
                query = """
                    SELECT DISTINCT query as text, service_category as label
                    FROM nlu_classifications
                    WHERE service_category IN (SELECT name FROM categories WHERE is_ai_enabled = 1)
                    AND query IS NOT NULL
                    AND query != ''
                    LIMIT 500
                """
            else:
                query = """
                    SELECT DISTINCT query as text, service_category as label
                    FROM nlu_classifications
                    WHERE service_category IN (SELECT name FROM categories WHERE is_ai_enabled = 1)
                    AND query IS NOT NULL
                    AND query != ''
                    LIMIT 500
                """
            
            cursor.execute(query)
            data = cursor.fetchall()
            cursor.close()
            
            logger.info(f"Loaded {len(data)} training examples from database")
            return data
        except Error as e:
            logger.error(f"Error fetching training data: {e}")
            return []
    
    def generate_training_dataset(self, output_path: str = None) -> Tuple[List[Dict], List[str]]:
        """
        Generate training dataset from categories and their keywords
        
        Args:
            output_path: Optional path to save dataset as JSON
            
        Returns:
            Tuple of (training_data, categories)
        """
        categories = self.get_categories(ai_enabled_only=True)
        
        if not categories:
            logger.warning("No AI-enabled categories found")
            return [], []
        
        training_data = []
        category_names = [cat['name'] for cat in categories]
        
        # Generate training examples from category keywords and names
        example_templates = {
            'en': [
                "I need a {category} to {action}",
                "Can you help me find a {category}?",
                "I'm looking for a {category} specialist",
                "Do you know any good {category}?",
                "I need someone to {action} - a {category}",
                "Could you recommend a {category}?",
                "I want to hire a {category}",
                "Can you fix this - I need a {category}",
                "Looking for professional {category} services",
                "Need emergency {category} assistance"
            ],
            'rw': [
                "Ndashaka {category} kugira ngo {action}",
                "Urashaka {category}?",
                "Ndashaka {category} uzamufata",
                "Hari {category} mwiza hano?",
                "Kugira ngo {action}, ndashaka {category}",
                "Wambwira {category} meza",
                "Nifuza guha mwumbe {category}",
                "Ndashaka {category} yo kwibwira",
                "Gusaba ubwiyunge {category} bwimfatire",
                "Ndashaka imigire y'umuntu w'{category}"
            ]
        }
        
        # Common actions for templates
        actions = {
            'electrician': ['fix my wiring', 'install lights', 'repair my power'],
            'plumber': ['fix my pipes', 'unclog my drain', 'fix my tap'],
            'carpenter': ['repair my door', 'fix my furniture', 'build shelves'],
            'cleaner': ['clean my house', 'clean my office', 'do deep cleaning'],
            'painter': ['paint my walls', 'refresh my interior', 'paint my room'],
            'handyman': ['fix multiple issues', 'repair various things', 'help with maintenance']
        }
        
        # Generate examples for each category
        for category in categories:
            cat_name = category['name'].lower()
            keywords = category.get('ai_keywords', cat_name).split(',')
            keywords = [k.strip() for k in keywords if k.strip()]
            
            # Add category name as first keyword if not present
            if cat_name not in keywords:
                keywords.insert(0, cat_name)
            
            # Generate examples from templates
            for lang, templates in example_templates.items():
                action_list = actions.get(cat_name, ['help me', 'assist me'])
                
                for action in action_list:
                    for template in templates:
                        if '{action}' in template:
                            text = template.format(category=cat_name, action=action)
                        else:
                            text = template.format(category=cat_name)
                        
                        training_data.append({
                            'text': text,
                            'label': category['name'],
                            'language': lang
                        })
            
            # Add keyword-based examples
            for keyword in keywords:
                keyword_clean = keyword.strip()
                if keyword_clean and keyword_clean != cat_name:
                    training_data.append({
                        'text': f"I need a {keyword_clean}",
                        'label': category['name'],
                        'language': 'en'
                    })
                    training_data.append({
                        'text': f"Ndashaka {keyword_clean}",
                        'label': category['name'],
                        'language': 'rw'
                    })
        
        # Save to file if path provided
        if output_path:
            with open(output_path, 'w', encoding='utf-8') as f:
                json.dump(training_data, f, ensure_ascii=False, indent=2)
            logger.info(f"Training dataset saved to {output_path}")
        
        logger.info(f"Generated {len(training_data)} training examples")
        return training_data, category_names
    
    def save_classification(self, query: str, service_category: str, confidence: float, language: str = 'en'):
        """
        Save classification result to database
        
        Args:
            query: Original query text
            service_category: Classified category
            confidence: Confidence score
            language: Language used
        """
        try:
            cursor = self.connection.cursor()
            
            query_sql = """
                INSERT INTO nlu_classifications (query, service_category, confidence, language, created_at)
                VALUES (%s, %s, %s, %s, NOW())
            """
            
            cursor.execute(query_sql, (query, service_category, confidence, language))
            self.connection.commit()
            cursor.close()
            
            logger.debug(f"Saved classification: {query} -> {service_category}")
        except Error as e:
            logger.error(f"Error saving classification: {e}")
    
    def close(self):
        """Close database connection"""
        if self.connection and self.connection.is_connected():
            self.connection.close()
            logger.info("Database connection closed")


def get_db_instance(host: str = '127.0.0.1',
                    user: str = 'root',
                    password: str = '',
                    database: str = 'bii_localfinder',
                    port: int = 3306) -> NLUDatabase:
    """
    Factory function to get database instance
    
    Returns:
        NLUDatabase instance
    """
    return NLUDatabase(host=host, user=user, password=password, database=database, port=port)
