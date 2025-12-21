import requests
from bs4 import BeautifulSoup
import json
import sys

def scrape_emic():
    # URL provided in the prompt
    url = "https://www.emic.ee/?sisu=heliloojad&mid=32&id=100&lang=est&action=view&method=teosed"
    
    print(f"Fetching {url}...")
    try:
        response = requests.get(url)
        response.raise_for_status()
    except Exception as e:
        print(f"Error fetching URL: {e}")
        sys.exit(1)

    soup = BeautifulSoup(response.content, 'html.parser')

    # 1. Composer Name
    # composer: h1 with class "entry-title"
    composer_tag = soup.find('h1', class_='entry-title')
    composer_name = composer_tag.get_text(strip=True) if composer_tag else "Unknown Composer"

    print(f"Composer found: {composer_name}")

    # 2. Compositions
    # category: h4
    # title: div with class "teose-title"
    # description: div with class "teose-info"
    
    # We want to process these in order of appearance. 
    # We will search for all relevant tags and iterate through them.
    
    relevant_tags = soup.find_all(lambda tag: (tag.name == 'h4') or 
                                              (tag.name == 'div' and ('teose-title' in tag.get('class', []) or 
                                                                    'teos-title' in tag.get('class', []) or 
                                                                    'teose-info' in tag.get('class', []))))

    compositions_list = []
    
    current_category_name = ""
    current_works_list = []
    pending_title = None

    for tag in relevant_tags:
        # Ignore tags that might be in header/footer if they appear before main content?
        # A simple heuristic: if we haven't found h1 yet? 
        # But h1 might be separate. 
        # Let's hope the document order is correct. 
        
        if tag.name == 'h4':
            # Identify if this h4 is actually part of the content.
            # Usually strict scraper logic requires a specific container. 
            # We will assume all h4s after the composer name are categories.
            
            # Save previous category
            if current_category_name or current_works_list:
                # If we have a pending title from the previous category (unlikely but possible), add it
                if pending_title:
                    current_works_list.append({"title": pending_title, "description": ""})
                    pending_title = None
                
                compositions_list.append({
                    "category": current_category_name,
                    "works": current_works_list
                })
            
            current_category_name = tag.get_text(strip=True)
            current_works_list = []
            pending_title = None
            
        elif 'teose-title' in tag.get('class', []) or 'teos-title' in tag.get('class', []):
            # If there was a pending title without description, save it now
            if pending_title:
                current_works_list.append({"title": pending_title, "description": ""})
            
            pending_title = tag.get_text(strip=True)
            
        elif 'teose-info' in tag.get('class', []):
            # Iterate children to properly handle <br> and strip text
            desc_parts = []
            for child in tag.children:
                if child.name == 'br':
                    desc_parts.append('\n')
                elif child.string:
                    s = child.string.strip()
                    if s:
                        desc_parts.append(s)
            
            description = "".join(desc_parts)
            
            if pending_title:
                current_works_list.append({
                    "title": pending_title,
                    "description": description
                })
                pending_title = None
            else:
                # Found info without a title immediately preceding? 
                # Could append to previous work or treat as orphan.
                # For now, ignore or add as untitled?
                # Let's assume strict title-desc pairs.
                pass

    # Append the final category/works
    if current_category_name or current_works_list or pending_title:
        if pending_title:
            current_works_list.append({"title": pending_title, "description": ""})
            
        compositions_list.append({
            "category": current_category_name,
            "works": current_works_list
        })

    result_data = [{
        "composer": composer_name,
        "compositions": compositions_list
    }]

    output_filename = 'test-data.json'
    with open(output_filename, 'w', encoding='utf-8') as f:
        json.dump(result_data, f, ensure_ascii=False, indent=2)
    
    print(f"Successfully saved data to {output_filename}")

if __name__ == "__main__":
    scrape_emic()
