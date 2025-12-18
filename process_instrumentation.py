import os
import sys
import json
import time
import re
import google.generativeai as genai

# Configuration
INPUT_FILE = "test-data.json"
OUTPUT_FILE = "test-instrumentations.json"
FAILED_FILE = "failed-instrumentations.json"
# Free tier limit is often 15 RPM (1.5 Flash) or 5 RPM (newer models). 
# 15s delay = 4 RPM, which is safe for the 5 RPM limit.
DELAY_BETWEEN_REQUESTS = 5 #15

# Test mode: if True, only process first 10 items
TEST_MODE = False
TEST_LIMIT = 5
START_FROM =  0

# System prompt
SYSTEM_PROMPT = """
Role: You are an expert Musicologist and Data Structuring Agent. Your task is to parse text descriptions of musical instrumentation (input in Estonian) and convert them into a standardized, query-optimized JSON structure.

Objective: Create a JSON output that allows a database to answer queries like "Find works for 2-6 players," "Find works with a Flute soloist," or "Find works including electronics."
1. JSON Schema Definition

You must adhere strictly to this JSON structure:
JSON

{
  "instrumentation": {
    "original_text": "String",
    "category": "String (options: solo, chamber, ensemble, orchestra, choir, vocal, open)",
    "total_player_count": "Integer (or null for infinite/variable groups like orchestras/choirs)",
    "has_electronics": "Boolean",
    "has_vocal": "Boolean",
    "ensembles": ["String (e.g., 'keelpillikvartett', 'vaskpillikvintett')"],
    "parts": [
      {
        "instrument_id": "String (standard abbr, e.g., 'fl', 'vln', 'pf')",
        "name_et": "String (Estonian name)",
        "name_en": "String (English name)",
        "count": "Integer",
        "doubles": ["String (instruments played by same player)"],
        "role": "String (options: 'normal', 'soloist', 'obbligato')",
        "family": "String (woodwind, brass, percussion, keyboard, string, voice, electronic)"
      }
    ],
    "orchestral_layout": {
      "woodwinds": "[Array of 4 Ints: Fl, Ob, Cl, Bn]",
      "brass": "[Array of 4 Ints: Hn, Trp, Tbn, Tba]",
      "percussion_players": "Integer (count of players, not instruments)",
      "timpani": "Boolean",
      "strings": "Boolean",
      "other": ["String (list of aux instruments)"]
    }
  }
}

2. Parsing Rules

A. Language & Normalization

    Input is in Estonian. Translate terms internally to English for categorization but keep Estonian names in name_et.

    Common Translations:

        Keelpillid = Strings

        Vaskpillid = Brass

        Puu or Puupillid = Woodwinds

        Helilint = Tape/Electronics

B. Player Counting (total_player_count)

    Chamber/Solo: Sum the count of all parts.

    Orchestra/Choir: Set to null. These are considered "scalable" groups.

    Doubling: "Flööt/Pikolo" counts as 1 player. The second instrument goes into the doubles array.

C. Roles

    If the text says solistid (soloists) or lists an instrument separately before an orchestra (e.g., "Flööt, Kammerorkester"), mark the Flute role as "soloist".

    All others default to "normal".

D. Orchestral Shorthand If the input contains numeric shorthand (e.g., 2222, 4231), populate the orchestral_layout object:

    Woodwinds: 4 digits representing Flute, Oboe, Clarinet, Bassoon.

    Brass: 4 digits representing Horn, Trumpet, Trombone, Tuba.

    Percussion: Usually "1" or "2" denoting player count. 1+2 usually means 1 Timpani + 2 Percussion.

    Strings: If "keelpillid" is present, set strings: true.

3. Examples

Input: flööt/pikolo, klaver

Output:
JSON

{
  "instrumentation": {
    "original_text": "flööt/pikolo, klaver",
    "category": "chamber",
    "total_player_count": 2,
    "has_electronics": false,
    "has_vocal": false,
    "ensembles": [],
    "parts": [
      { "instrument_id": "fl", "name_et": "flööt", "name_en": "flute", "count": 1, "doubles": ["piccolo"], "role": "normal", "family": "woodwind" },
      { "instrument_id": "pf", "name_et": "klaver", "name_en": "piano", "count": 1, "doubles": [], "role": "normal", "family": "keyboard" }
    ],
    "orchestral_layout": null
  }
}

Input: sümfooniaorkester: 2222, 4231, 1+2, süntesaator, keelpillid

Output:
JSON

{
  "instrumentation": {
    "original_text": "sümfooniaorkester: 2222, 4231, 1+2, süntesaator, keelpillid",
    "category": "orchestra",
    "total_player_count": null,
    "has_electronics": true,
    "has_vocal": false,
    "ensembles": ["sümfooniaorkester"],
    "parts": [],
    "orchestral_layout": {
      "woodwinds": [2, 2, 2, 2],
      "brass": [4, 2, 3, 1],
      "percussion_players": 2,
      "timpani": true,
      "strings": true,
      "other": ["synthesizer"]
    }
  }
}
"""

def save_intermediate(results, failed):
    """Save results to file immediately."""
    try:
        with open(OUTPUT_FILE, 'w', encoding='utf-8') as f:
            json.dump(results, f, ensure_ascii=False, indent=2)
        with open(FAILED_FILE, 'w', encoding='utf-8') as f:
            json.dump(failed, f, ensure_ascii=False, indent=2)
    except Exception as e:
        print(f"Warning: Could not save intermediate results: {e}")

def extract_instrumentation(description):
    lines = [l.strip() for l in description.split('\n') if l.strip()]
    
    # Heuristics to skip non-instrumentation lines
    # 1. Type (usually 1st line, sometimes just a word like "Ooper")
    # 2. Year (usually 4 digits, e.g. "2010" or "2010-2012")
    # 3. Duration (usually digits + ', e.g. "12'")
    
    # We will iterate and picking the first line that is NOT:
    # - Just a year/range
    # - Just duration
    # - Starts with known labels
    
    candidate_lines = []
    
    idx = 0
    # Skip likely Type/Year/Duration at the beginning
    # But be careful not to skip instrumentation if it's the first thing
    
    # Let's try to detect the "middle" block
    # Usually: Type -> Year -> Duration -> Instrumentation -> Labels
    
    for i, line in enumerate(lines):
        # Check if line seems to be metadata
        is_metadata = False
        
        # Year check: 19xx, 20xx, 19xx-20xx
        if re.match(r'^(\d{4}|\d{4}-\d{4})$', line):
            is_metadata = True
            
        # Duration check: digits' or digits min
        elif re.match(r'^\d+(\'?| min)$', line):
            is_metadata = True
            
        # Label check
        elif any(line.startswith(p) for p in ["Libreto:", "Esiettekanne:", "Tellija:", "Kirjastaja:", "Tekst:", "CD", "Levitaja:"]):
            # Once we hit labels, the rest are usually labels too
            # So stop collecting candidates?
            # actually we can just stop
            break
            
        # Type check is hard because "Ooper" is a valid word. 
        # But Type usually appears at index 0. 
        # Instrumentation usually appears after Year/Duration.
        
        # If it's NOT metadata, add to candidates
        if not is_metadata:
            candidate_lines.append(line)
            
    # Now we have candidates. 
    # Usually the first candidate is Type (e.g. "Ooper") if it wasn't filtered.
    # The second might be instrumentation.
    # If the user says "usually 3rd element", that includes Type and Year.
    # So if we have [Type, Instrumentation], we want the 2nd one.
    # But sometimes Type is missing?
    
    # Let's filter out candidates that look like Types?
    # Common types: "Ooper", "Balletid", "Teosed...", "Sümfoonia", "Kontsert"
    # But "Sümfoonia" could be a title? No, title is separate.
    
    # Let's rely on the user's hint: "usually the third element when split by \n"
    # Assuming Type \n Year \n Instrumentation
    
    # If we strictly look at the original lines:
    # 0: Type
    # 1: Year
    # 2: Instrumentation (Duration might be combined with year or separate?)
    
    # Let's try to use index 2 if available and looks good. 
    # If len < 3, use the last non-label line?
    
    # Better approach:
    # Filter out year/duration lines.
    # Then take the remaining lines.
    # If > 1 remaining line, the FIRST one is Type, the SECOND is Instrumentation.
    # If only 1 remaining, maybe it's just Type (no instrumentation listed)? Or just Instrumentation?
    # Most entries have a Type line.
    
    filtered = []
    for line in lines:
         if re.match(r'^(\d{4}|\d{4}-\d{4})$', line): continue
         if re.match(r'^\d+(\'?| min)$', line): continue
         # Added "Tekst:" and "I " (movement markers) to skip check? No, movements shouldn't be skipped but might confuse logic.
         if any(line.startswith(p) for p in ["Libreto:", "Esiettekanne:", "Tellija:", "Kirjastaja:", "Tekst:", "CD", "Levitaja:", "Eestikeelne tõlge"]):
             break # Stop at first label
         filtered.append(line)
         
    # Debug print
    # print(f"DEBUG: {filtered}")

    if len(filtered) >= 2:
        # If we have [Type, Instrumentation], return Instrumentation.
        # But for "Deux": ['flöödikontsert', 'I Un', 'II Deux', 'flööt, sopran...']
        # The instrumentation is actually the last element of filtered?
        # Or specifically standard logic: 
        # Usually: Type -> (Movements?) -> Instrumentation
        
        # Heuristic: the line with instrumentation usually contains commas and instrument names.
        # Let's verify candidates.
        best_candidate = None
        max_score = 0
        
        # Simple scoring
        keywords = ["flööt", "klaver", "orkester", "viiul", "sopran", "koor", "keelpillid", "löökpillid"]
        
        for cand in filtered:
            score = 0
            # Does it have comma?
            if ',' in cand: score += 1
            # Does it have digits (shorthand 2222)?
            if re.search(r'\d{4}', cand): score += 2
            # Does it have keywords?
            if any(k in cand.lower() for k in keywords): score += 3
            
            if score > max_score:
                max_score = score
                best_candidate = cand
        
        if best_candidate and max_score > 0:
            return best_candidate
            
        # Fallback to index 1 if available
        return filtered[1]
        
    elif len(filtered) == 1:
        val = filtered[0]
        # Allow if it contains comma or spaces or known keywords, but exclude simple Type words
        # "keelpilliorkester" is a single word but valid instrumentation.
        keywords = ["orkester", "ansambel", "koor", "kvartett", "kvintett", "trio", "duo"]
        if ' ' in val or ',' in val or any(k in val.lower() for k in keywords):
            return val
        else:
            return None 
            
    return None

def main():
    api_key = os.environ.get("GEMINI_API_KEY")
    if not api_key:
        print("GEMINI_API_KEY not found.")
        sys.exit(1)
        
    genai.configure(api_key=api_key, transport='rest')
    
    # Using gemini-1.5-flash-latest as requested/found to be available
    model = genai.GenerativeModel('gemini-2.5-flash-lite')

    
    try:
        with open(INPUT_FILE, 'r', encoding='utf-8') as f:
            data = json.load(f)
    except Exception as e:
        print(f"Error reading {INPUT_FILE}: {e}")
        sys.exit(1)
        
    results = []
    failed = []
    
    # Load existing results if valid to resume?
    # User didn't explicitly ask for resume, but "output was not saved" implies fresh start or overwrite.
    # We will overwrite for now as per "Save the succeeded results".
    
    total_works = sum(len(c.get('compositions', [])) for c in data)
    print(f"Total composers: {len(data)}")
    
    # We iterate composers, ignore category grouping for total count, but iterate carefully
    processed_count = 0 
    attempt_count = 0
    
    for composer_entry in data:
        composer_name = composer_entry.get('composer', 'Unknown')
        for cat_entry in composer_entry.get('compositions', []):
            category = cat_entry.get('category', '')
            for work in cat_entry.get('works', []):
                
                current_work_index = processed_count
                processed_count += 1 # Increment for every work encountered
                
                # Check if we should skip this item based on START_FROM
                if current_work_index < START_FROM:
                    # print(f"  -> Skipping item {current_work_index} (START_FROM={START_FROM})")
                    continue
                
                # Test mode check (limit relative to attempts made in this run)
                if TEST_MODE and attempt_count >= TEST_LIMIT:
                    print(f"\nTest limit ({TEST_LIMIT}) reached. Stopping.")
                    save_intermediate(results, failed)
                    sys.exit(0)
                
                # If we reach here, this item is being processed (or attempted)
                attempt_count += 1
                
                title = work.get('title', 'Untitled')
                description = work.get('description', '')
                
                print(f"Processing {current_work_index}: {composer_name} - {title}")
                
                instr_text = extract_instrumentation(description)
                if not instr_text:
                    print("  -> No instrumentation text found (heuristic). Skipping API call.")
                    failed.append({
                        "composer": composer_name,
                        "title": title,
                        "description": description,
                        "error": "No instrumentation text extracted"
                    })
                    # We save even on failure to keep track
                    save_intermediate(results, failed)
                    continue
                
                # Increment attempt count only when we actually try to process (or have text to process)
                attempt_count += 1
                
                print(f"  -> Extracted text: {instr_text[:50]}...")
                
                try:
                    prompt = f"{SYSTEM_PROMPT}\n\nInput: {instr_text}"
                    
                    # Simple retry logic for 429
                    max_retries = 3
                    for attempt in range(max_retries):
                        try:
                            response = model.generate_content(prompt)
                            break
                        except Exception as e:
                            if "429" in str(e) and attempt < max_retries - 1:
                                print(f"  -> Rate limit hit. Waiting 60s before retry {attempt+1}...")
                                time.sleep(60)
                            else:
                                raise e
                    
                    # Clean response
                    resp_text = response.text.strip()
                    if resp_text.startswith("```"):
                         resp_text = re.sub(r'^```(json)?\n', '', resp_text)
                         resp_text = re.sub(r'\n```$', '', resp_text)
                    
                    parsed = json.loads(resp_text)
                    
                    # Add metadata
                    result_entry = {
                        "composer": composer_name,
                        "title": title,
                        "work_id": f"{composer_name}_{title}", # Simple ID
                        "extracted_instrumentation": instr_text,
                        "analysis": parsed
                    }
                    results.append(result_entry)
                    print("  -> Success")
                    save_intermediate(results, failed)
                    
                except Exception as e:
                    print(f"  -> API/Parse Error: {e}")
                    failed.append({
                        "composer": composer_name,
                        "title": title,
                        "extracted_text": instr_text,
                        "error": str(e)
                    })
                    save_intermediate(results, failed)
                
                time.sleep(DELAY_BETWEEN_REQUESTS)

    # Save results
    with open(OUTPUT_FILE, 'w', encoding='utf-8') as f:
        json.dump(results, f, ensure_ascii=False, indent=2)
        
    with open(FAILED_FILE, 'w', encoding='utf-8') as f:
        json.dump(failed, f, ensure_ascii=False, indent=2)
        
    print(f"\nDone. Saved {len(results)} successes to {OUTPUT_FILE} and {len(failed)} failures to {FAILED_FILE}.")

if __name__ == "__main__":
    main()
