#!/usr/bin/env python3
"""
Script to clean HTML tags and whitespace from a MySQL/MariaDB database field.
"""

import mysql.connector
import re
from html.parser import HTMLParser


# Configuration
DB_CONFIG = {
    'host': 'localhost',
    'user': 'emic',
    'password': 'tobias',
    'database': 'emic'
}

TABLE_NAME = 'teosed_tekstid'
FIELD_NAME = 'koosseis'
ID_FIELD = 'id'  # Primary key field name

# Test mode: if True, only display changes without updating database
TEST_MODE = False


class HTMLTextExtractor(HTMLParser):
    """Extract text content from HTML."""
    def __init__(self):
        super().__init__()
        self.text = []
    
    def handle_data(self, data):
        self.text.append(data)
    
    def get_text(self):
        return ''.join(self.text)


def clean_html(text):
    """
    Remove HTML tags and clean whitespace from text.
    
    Args:
        text: String to clean
        
    Returns:
        Cleaned string
    """
    if not text or not isinstance(text, str):
        return text
    
    # Parse HTML and extract text
    parser = HTMLTextExtractor()
    try:
        parser.feed(text)
        cleaned = parser.get_text()
    except Exception as e:
        print(f"Warning: Failed to parse HTML: {e}")
        cleaned = text
    
    # Clean whitespace characters
    cleaned = re.sub(r'[\t\n\r]+', ' ', cleaned)
    cleaned = cleaned.strip()
    
    # Collapse multiple spaces
    cleaned = re.sub(r'\s+', ' ', cleaned)
    
    return cleaned


def main():
    """Main function to clean database field."""
    print("=" * 60)
    print("Database Field Cleaner")
    print("=" * 60)
    print(f"Database: {DB_CONFIG['database']}")
    print(f"Table: {TABLE_NAME}")
    print(f"Field: {FIELD_NAME}")
    print(f"Mode: {'TEST (no changes will be made)' if TEST_MODE else 'LIVE (database will be updated)'}")
    print("=" * 60)
    print()
    
    # Connect to database
    try:
        conn = mysql.connector.connect(**DB_CONFIG)
        cursor = conn.cursor()
        print("✓ Connected to database")
        print()
    except mysql.connector.Error as e:
        print(f"✗ Error connecting to database: {e}")
        return
    
    try:
        # Fetch all records with the field
        query = f"SELECT {ID_FIELD}, {FIELD_NAME} FROM {TABLE_NAME}"
        cursor.execute(query)
        records = cursor.fetchall()
        
        print(f"Found {len(records)} records to process")
        print()
        
        updated_count = 0
        unchanged_count = 0
        
        # Process each record
        for record_id, original_value in records:
            if original_value is None:
                unchanged_count += 1
                continue
            
            # Clean the value
            cleaned_value = clean_html(original_value)
            
            # Check if value changed
            if cleaned_value != original_value:
                updated_count += 1
                
                print(f"Record ID: {record_id}")
                print(f"  Original: {original_value[:100]}{'...' if len(original_value) > 100 else ''}")
                print(f"  Cleaned:  {cleaned_value[:100]}{'...' if len(cleaned_value) > 100 else ''}")
                print()
                
                # Update database if not in test mode
                if not TEST_MODE:
                    update_query = f"UPDATE {TABLE_NAME} SET {FIELD_NAME} = %s WHERE {ID_FIELD} = %s"
                    cursor.execute(update_query, (cleaned_value, record_id))
            else:
                unchanged_count += 1
        
        # Commit changes if not in test mode
        if not TEST_MODE:
            conn.commit()
            print("✓ Changes committed to database")
        
        print()
        print("=" * 60)
        print("Summary:")
        print(f"  Total records: {len(records)}")
        print(f"  Updated: {updated_count}")
        print(f"  Unchanged: {unchanged_count}")
        
        if TEST_MODE:
            print()
            print("NOTE: Test mode was enabled - no changes were made to the database.")
            print("      Set TEST_MODE = False to apply changes.")
        
        print("=" * 60)
        
    except mysql.connector.Error as e:
        print(f"✗ Database error: {e}")
        conn.rollback()
    
    finally:
        cursor.close()
        conn.close()
        print()
        print("✓ Database connection closed")


if __name__ == "__main__":
    main()
