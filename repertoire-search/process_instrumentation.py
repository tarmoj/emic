import os
import sys
import json
import time
import re
import random
from pathlib import Path
import google.generativeai as genai
import mysql.connector

# Configuration
INPUT_FILE = "test_data2.json"
OUTPUT_FILE = "test-instrumentations2.json"
FAILED_FILE = "failed-instrumentations2.json"
# Database configuration
DB_CONFIG = {
    "host": "localhost",
    "user": "emic",
    "password": "tobias",
    "database": "emic"
}
DB_TABLE = "teosed_koosseisud"
# Free tier limit is often 15 RPM (1.5 Flash) or 5 RPM (newer models). 
# 15s delay = 4 RPM, which is safe for the 5 RPM limit.
DELAY_BETWEEN_REQUESTS = 0.5 #15

# Test mode: if True, only process first 10 items
TEST_MODE = True
TEST_LIMIT = 2
START_FROM = 1

# System prompt
SYSTEM_PROMPT = Path(__file__).with_name("system_prompt.txt").read_text(encoding="utf-8")

def save_intermediate(results, failed):
    """Save results to file immediately."""
    try:
        with open(OUTPUT_FILE, 'w', encoding='utf-8') as f:
            json.dump(results, f, ensure_ascii=False, indent=2)
        with open(FAILED_FILE, 'w', encoding='utf-8') as f:
            json.dump(failed, f, ensure_ascii=False, indent=2)
    except Exception as e:
        print(f"Warning: Could not save intermediate results: {e}")

def main():
    api_key = os.environ.get("GEMINI_API_KEY")
    if not api_key:
        print("GEMINI_API_KEY not found.")
        sys.exit(1)
        
    genai.configure(api_key=api_key, transport='rest')
    
    model = genai.GenerativeModel(
        'gemini-2.5-flash-lite',
        system_instruction=SYSTEM_PROMPT
    )
    chat = model.start_chat(history=[])

    
    try:
        with open(INPUT_FILE, 'r', encoding='utf-8') as f:
            data = json.load(f)
    except Exception as e:
        print(f"Error reading {INPUT_FILE}: {e}")
        sys.exit(1)
        
    results = []
    failed = []

    try:
        db_conn = mysql.connector.connect(**DB_CONFIG)
        db_cursor = db_conn.cursor()
    except mysql.connector.Error as e:
        print(f"Database connection error: {e}")
        sys.exit(1)

    def finalize_db():
        try:
            db_conn.commit()
            db_cursor.close()
            db_conn.close()
        except mysql.connector.Error as e:
            print(f"Database commit/close error: {e}")
    
    # Load existing results if valid to resume?
    # User didn't explicitly ask for resume, but "output was not saved" implies fresh start or overwrite.
    # We will overwrite for now as per "Save the succeeded results".
    
    print(f"Total works: {len(data)}")
    
    # We iterate composers, ignore category grouping for total count, but iterate carefully
    processed_count = 0 
    attempt_count = 0
    
    for work in data:
        current_work_index = processed_count
        processed_count += 1

        if current_work_index < START_FROM:
            continue

        if TEST_MODE and attempt_count >= TEST_LIMIT:
            print(f"\nTest limit ({TEST_LIMIT}) reached. Stopping.")
            save_intermediate(results, failed)
            finalize_db()
            sys.exit(0)

        attempt_count += 1

        work_id = work.get('id')
        title = work.get('pealkiri')
        instr_text = work.get('koosseis')

        print(f"Processing {current_work_index}: id={work_id}")

        if not instr_text:
            print("  -> No instrumentation text found. Skipping API call.")
            failed.append({
                "id": work_id,
                "title": title,
                "error": "No instrumentation text provided"
            })
            save_intermediate(results, failed)
            continue

        print(f"  -> Input text: {instr_text[:50]}...")

        try:
            max_retries = 3
            for attempt in range(max_retries):
                try:
                    response = chat.send_message(
                        f"Input: {instr_text}",
                        generation_config={"response_mime_type": "application/json"}
                    )
                    break
                except Exception as e:
                    if "429" in str(e) and attempt < max_retries - 1:
                        # Exponential backoff: 2, 4, 8 seconds...
                        wait_time = (2 ** attempt) + random.random()
                        print(f"  -> Rate limit hit. Waiting {wait_time:.2f}s...")
                        time.sleep(wait_time)
                    else:
                        raise e

            resp_text = response.text.strip()
            if resp_text.startswith("```"):
                resp_text = re.sub(r'^```(json)?\n?', '', resp_text)
                resp_text = re.sub(r'\n?```$', '', resp_text)

            parsed = json.loads(resp_text)

            result_entry = {
                "id": work_id,
                "title": title,
                "original_text": instr_text,
                "instrumentation": parsed
            }
            results.append(result_entry)

            try:
                insert_query = (
                    f"INSERT INTO {DB_TABLE} (id, title, original_text, instrumentation) "
                    "VALUES (%s, %s, %s, %s) "
                    "ON DUPLICATE KEY UPDATE "
                    "title = VALUES(title), "
                    "original_text = VALUES(original_text), "
                    "instrumentation = VALUES(instrumentation)"
                )
                db_cursor.execute(
                    insert_query,
                    (work_id, title, instr_text, json.dumps(parsed, ensure_ascii=False))
                )
            except mysql.connector.Error as e:
                print(f"  -> Database insert error: {e}")
                failed.append({
                    "id": work_id,
                    "title": title,
                    "original_text": instr_text,
                    "error": f"Database insert error: {e}"
                })
                save_intermediate(results, failed)
            print("  -> Success")
            save_intermediate(results, failed)

        except Exception as e:
            print(f"  -> API/Parse Error: {e}")
            failed.append({
                "id": work_id,
                "title": title,
                "original_text": instr_text,
                "error": str(e)
            })
            save_intermediate(results, failed)

        time.sleep(DELAY_BETWEEN_REQUESTS)

    # Save results
    with open(OUTPUT_FILE, 'w', encoding='utf-8') as f:
        json.dump(results, f, ensure_ascii=False, indent=2)
        
    with open(FAILED_FILE, 'w', encoding='utf-8') as f:
        json.dump(failed, f, ensure_ascii=False, indent=2)

    finalize_db()
        
    print(f"\nDone. Saved {len(results)} successes to {OUTPUT_FILE} and {len(failed)} failures to {FAILED_FILE}.")

if __name__ == "__main__":
    main()
