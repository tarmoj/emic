#!/usr/bin/env python3
"""
Script to analyze concert events from text file and convert to JSON format.
Uses Gemini API for natural language processing of Estonian text.
"""

import os
import sys
import json
import google.generativeai as genai
from typing import Dict, List, Optional
import time


# Configuration
INPUT_FILE = "test-events.txt"
OUTPUT_FILE = "test-events.json"
PROBLEMS_FILE = "problems.txt"
DELAY_BETWEEN_REQUESTS = 1  # seconds, to avoid rate limiting

# Test mode
TEST_MODE = True
TEST_EVENT = """Neujahrskonzert
01.01.2025
Koht: Theater Krefeld – Große Bühne, Saksamaa
Kell: 11:00
Esitajad:
Niederrheinische Sinfoniker
dirigent Mihkel Kütson
Kava:
Johann Strauss II
"Künstlerleben", op. 316
"Kaiserwalzer", op. 437
"An der schönen blauen Donau", op. 314
"Die Fledermaus"
Pilet ja lisainfo:
theater-kr-mg.de (https://theater-kr-mg.de/spielplan/neujahrskonzert/)"""

# Gemini API configuration
GEMINI_API_KEY = os.environ.get("GEMINI_API_KEY")
if not GEMINI_API_KEY:
    print("Error: GEMINI_API_KEY environment variable not set", file=sys.stderr)
    sys.exit(1)

genai.configure(api_key=GEMINI_API_KEY)

# System prompt for Gemini
SYSTEM_PROMPT = """Analyse given musical event, that is mostly a concert, and return the information in json format:  

{
    "title": "",
    "date": "",
    "time": "",
    "location": "",
    "performers": "",
    "program": "",
    "description": "",
    "tickets": "",
    "link": "",
    "other_info": ""
}

For the date and time format use something that will be easy to convert MySQL date format later.

The input is text in Estonian. Keep the language for the entries. 
The information is presented mostly as follows:
title:  first row
date: second row
location: third row
The other fields can come in different order.
info: is usually a link, preceded by 'Lisainfo:' in Estonian

All fields do not need to be filled. If there is doubt, return string that starts with "PROBLEMS FOUND:\\n", add the event and comments about problematic bits.

IMPORTANT: Return ONLY valid JSON or the PROBLEMS FOUND message. Do not include any markdown formatting, code blocks, or explanatory text."""


def read_events_from_file(filename: str) -> List[str]:
    """
    Read events from text file, split by #### delimiter.
    """
    try:
        with open(filename, 'r', encoding='utf-8') as f:
            content = f.read()
        
        # Split by delimiter and filter out empty entries
        events = [event.strip() for event in content.split('####') if event.strip()]
        return events
    except FileNotFoundError:
        print(f"Error: File '{filename}' not found", file=sys.stderr)
        sys.exit(1)
    except IOError as e:
        print(f"Error reading file '{filename}': {e}", file=sys.stderr)
        sys.exit(1)


def analyze_event_with_gemini(event_text: str, model) -> str:
    """
    Send event text to Gemini API for analysis.
    Returns the response as string.
    """
    try:
        prompt = f"{SYSTEM_PROMPT}\n\nEvent text:\n{event_text}"
        response = model.generate_content(prompt)
        return response.text.strip()
    except Exception as e:
        print(f"Error calling Gemini API: {e}", file=sys.stderr)
        return f"PROBLEMS FOUND:\nAPI Error: {str(e)}\n\nOriginal event:\n{event_text}"


def parse_gemini_response(response: str) -> Optional[Dict]:
    """
    Parse Gemini response. 
    Returns dict if valid JSON, None if problems found.
    """
    # Check if response indicates problems
    if response.startswith("PROBLEMS FOUND:"):
        return None
    
    # Try to extract JSON from response (in case there's markdown formatting)
    response = response.strip()
    
    # Remove markdown code blocks if present
    if response.startswith("```"):
        lines = response.split('\n')
        # Remove first line (```json or ```)
        lines = lines[1:]
        # Remove last line if it's ```
        if lines and lines[-1].strip() == "```":
            lines = lines[:-1]
        response = '\n'.join(lines).strip()
    
    # Try to parse JSON
    try:
        event_data = json.loads(response)
        return event_data
    except json.JSONDecodeError as e:
        print(f"Warning: Failed to parse JSON response: {e}", file=sys.stderr)
        return None


def append_problem_to_file(problem_text: str) -> None:
    """
    Append problem event to problems.txt file.
    """
    try:
        with open(PROBLEMS_FILE, 'a', encoding='utf-8') as f:
            f.write(problem_text)
            f.write('\n####\n')
    except IOError as e:
        print(f"Error writing to problems file: {e}", file=sys.stderr)


def save_events_to_json(events: List[Dict], filename: str) -> None:
    """
    Save events to JSON file.
    """
    try:
        output = {"events": events}
        with open(filename, 'w', encoding='utf-8') as f:
            json.dump(output, f, ensure_ascii=False, indent=2)
        print(f"Successfully saved {len(events)} events to {filename}")
    except IOError as e:
        print(f"Error writing to JSON file: {e}", file=sys.stderr)
        sys.exit(1)


def main():
    """Main function to process events."""
    print("Starting event analysis with Gemini API...")
    print(f"Input file: {INPUT_FILE}")
    print(f"Output file: {OUTPUT_FILE}")
    print()
    
    # Initialize Gemini model
    model = genai.GenerativeModel('gemini-2.5-flash')
    
    # Clear problems file if it exists
    if os.path.exists(PROBLEMS_FILE):
        os.remove(PROBLEMS_FILE)
    
    # Read events from file
    events = read_events_from_file(INPUT_FILE)
    print(f"Found {len(events)} events to process")
    print()
    
    successful_events = []
    problem_count = 0
    
    # Process each event
    for i, event_text in enumerate(events, 1):
        print(f"Processing event {i}/{len(events)}...")
        
        # Get analysis from Gemini
        response = analyze_event_with_gemini(event_text, model)
        
        # Parse response
        event_data = parse_gemini_response(response)
        
        if event_data:
            # Successfully parsed
            successful_events.append(event_data)
            print(f"  ✓ Successfully analyzed")
        else:
            # Problem found
            problem_count += 1
            append_problem_to_file(response)
            print(f"  ✗ Problem found, saved to {PROBLEMS_FILE}")
        
        # Delay between requests
        if i < len(events):
            time.sleep(DELAY_BETWEEN_REQUESTS)
    
    print()
    print(f"Processing completed!")
    print(f"  Successful: {len(successful_events)}")
    print(f"  Problems: {problem_count}")
    
    # Save successful events to JSON
    if successful_events:
        save_events_to_json(successful_events, OUTPUT_FILE)
    else:
        print("No events to save to JSON file")
    
    if problem_count > 0:
        print(f"  Problem events saved to {PROBLEMS_FILE}")


if __name__ == "__main__":
    main()
