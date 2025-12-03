#!/usr/bin/env python3
"""
Script to scrape concert events from EMIC website.
Collects data from years 2014-2025 and saves to text files.
"""

import requests
from bs4 import BeautifulSoup
import time
import sys
from typing import List, Tuple


BASE_URL = "https://www.emic.ee/"
CALENDAR_URL = BASE_URL + "muusikasundmuste-kalender&year={year}"
YEARS = range(2014, 2026)  # 2014 to 2025 inclusive
DELAY_BETWEEN_REQUESTS = 0.25  # seconds, to be polite to the server


def get_page_content(url: str) -> str:
    """Fetch page content from URL."""
    try:
        response = requests.get(url, timeout=10)
        response.raise_for_status()
        return response.text
    except requests.RequestException as e:
        print(f"Error fetching {url}: {e}", file=sys.stderr)
        return ""


def extract_event_links(html_content: str) -> List[Tuple[str, str]]:
    """
    Extract event links from calendar page.
    Returns list of tuples: (event_url, event_title)
    """
    soup = BeautifulSoup(html_content, 'html.parser')
    event_links = []
    
    # Find all event excerpts
    event_divs = soup.find_all('div', class_='post-item-excerpt')
    
    for div in event_divs:
        # Find the h2 with post-title class
        h2 = div.find('h2', class_='post-title')
        if h2:
            link = h2.find('a')
            if link and link.get('href'):
                event_url = BASE_URL + link['href']
                event_title = link.get_text(strip=True)
                event_links.append((event_url, event_title))
    
    return event_links


def extract_event_content(html_content: str) -> str:
    """
    Extract event content from individual event page.
    Returns the text content from main-content div, excluding h1 and 'Tagasi' link.
    Links are converted to format: link_text (url)
    """
    soup = BeautifulSoup(html_content, 'html.parser')
    
    # Find main-content div
    main_content = soup.find('div', id='main-content')
    if not main_content:
        return ""
    
    # Remove the h1 "Muusikasündmuste kalender"
    h1 = main_content.find('h1', class_='entry-title')
    if h1:
        h1.decompose()
    
    # Process links: replace <a> tags with "text (url)" format, but remove "Tagasi" link
    for link in main_content.find_all('a'):
        link_text = link.get_text(strip=True)
        if link_text == 'Tagasi':
            link.decompose()
        else:
            href = link.get('href', '')
            if href:
                # Replace the link element with text that includes the URL
                link.replace_with(f"{link_text} ({href})")
            else:
                # No href, just keep the text
                link.replace_with(link_text)
    
    # Get the text content only (no HTML tags)
    content = main_content.get_text(separator='\n', strip=True)
    return content.strip()


def scrape_year(year: int, limit: int = None) -> List[str]:
    """
    Scrape all events for a given year.
    Returns list of event contents.
    
    Args:
        year: The year to scrape
        limit: Maximum number of events to scrape (for testing)
    """
    print(f"Scraping events for year {year}...")
    
    # Get calendar page for the year
    calendar_url = CALENDAR_URL.format(year=year)
    html_content = get_page_content(calendar_url)
    
    if not html_content:
        print(f"  Failed to fetch calendar for {year}")
        return []
    
    # Extract event links
    event_links = extract_event_links(html_content)
    print(f"  Found {len(event_links)} events")
    
    # Apply limit if specified
    if limit:
        event_links = event_links[:limit]
        print(f"  Processing only first {limit} events (test mode)")
    
    events_content = []
    
    # Fetch each event page
    for i, (event_url, event_title) in enumerate(event_links, 1):
        print(f"  Fetching event {i}/{len(event_links)}: {event_title}")
        
        event_html = get_page_content(event_url)
        if event_html:
            content = extract_event_content(event_html)
            if content:
                events_content.append(content)
        
        # Be polite to the server
        time.sleep(DELAY_BETWEEN_REQUESTS)
    
    return events_content


def save_events_to_file(year: int, events: List[str], filename: str = "events.txt", mode: str = 'a') -> None:
    """
    Save events to a text file.
    
    Args:
        year: The year of the events
        events: List of event contents
        filename: Output filename
        mode: File open mode ('w' for write, 'a' for append)
    """
    try:
        with open(filename, mode, encoding='utf-8') as f:
            # Add year header
            f.write(f"\n---------------- {year} ---------------------\n\n")
            # Join events with delimiter
            content = '\n####\n'.join(events)
            f.write(content)
            f.write('\n')
        
        print(f"  Saved {len(events)} events for year {year}")
    except IOError as e:
        print(f"  Error saving to file {filename}: {e}", file=sys.stderr)


def main():
    """Main function to scrape all years and save to a single file."""
    print("Starting EMIC concert events scraper...")
    print(f"Scraping years: {YEARS.start} to {YEARS.stop - 1}")
    print(f"Output file: events.txt")
    print()
    
    output_file = "events.txt"
    
    # Remove existing file if it exists
    try:
        import os
        if os.path.exists(output_file):
            os.remove(output_file)
    except Exception:
        pass
    
    for year in YEARS:
        events = scrape_year(year)
        
        if events:
            save_events_to_file(year, events, filename=output_file, mode='a')
        else:
            print(f"  No events found for {year}")
        
        print()
        
        # Delay between years
        if year != YEARS.stop - 1:
            time.sleep(DELAY_BETWEEN_REQUESTS)
    
    print(f"Scraping completed! All events saved to {output_file}")


if __name__ == "__main__":
    main()
