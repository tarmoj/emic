import json
import re
import mysql.connector

# --- Configuration ---
ORIGINAL_DATA_FILE = "teosed_koik.json"
BATCH_RESULTS_FILE = "gemini_results_final.jsonl"
DB_CONFIG = {
    "host": "localhost",
    "user": "emic",
    "password": "tobias",
    "database": "emic"
}


def _extract_json_candidates(raw_text):
    text = (raw_text or "").strip()
    if not text:
        return []

    fenced = re.findall(r"```(?:json)?\s*(.*?)\s*```", text, flags=re.DOTALL | re.IGNORECASE)
    if fenced:
        return [block.strip() for block in fenced if block and block.strip()]
    return [text]


def _repair_json_text(text):
    repaired = []
    in_string = False
    escaped = False
    length = len(text)

    for index, char in enumerate(text):
        if in_string:
            if escaped:
                repaired.append(char)
                escaped = False
                continue

            if char == "\\":
                repaired.append(char)
                escaped = True
                continue

            if char == "\n":
                repaired.append("\\n")
                continue

            if char == '"':
                lookahead = index + 1
                while lookahead < length and text[lookahead] in " \t\r\n":
                    lookahead += 1
                next_char = text[lookahead] if lookahead < length else ""

                if next_char in {",", "}", "]", ":", ""}:
                    repaired.append('"')
                    in_string = False
                else:
                    repaired.append('\\"')
                continue

            repaired.append(char)
            continue

        repaired.append(char)
        if char == '"':
            in_string = True

    return "".join(repaired)


def _parse_instrumentation_response(raw_text):
    decoder = json.JSONDecoder()

    for candidate in _extract_json_candidates(raw_text):
        try:
            return json.loads(candidate)
        except json.JSONDecodeError:
            pass

        try:
            parsed, _ = decoder.raw_decode(candidate)
            return parsed
        except json.JSONDecodeError:
            pass

        repaired = _repair_json_text(candidate)
        try:
            return json.loads(repaired)
        except json.JSONDecodeError:
            pass

        try:
            parsed, _ = decoder.raw_decode(repaired)
            return parsed
        except json.JSONDecodeError:
            pass

    raise json.JSONDecodeError("Could not parse model JSON", raw_text or "", 0)

def insert_results():
    # 1. Load original data into a lookup dictionary {id: {pealkiri, koosseis}}
    print("Loading original data for lookup...")
    with open(ORIGINAL_DATA_FILE, 'r', encoding='utf-8') as f:
        original_list = json.load(f)
        # Convert list to dict for fast ID lookup
        lookup = {str(item['id']): item for item in original_list}

    # 2. Connect to Database
    try:
        conn = mysql.connector.connect(**DB_CONFIG)
        cursor = conn.cursor()
    except mysql.connector.Error as e:
        print(f"Error connecting to MariaDB: {e}")
        return

    # 3. Process Batch Results
    inserted_count = 0
    print("Processing batch results and inserting to DB...")
    
    with open(BATCH_RESULTS_FILE, 'r', encoding='utf-8') as f:
        for line in f:
            batch_item = json.loads(line)
            work_id = batch_item.get("key") # This is our ID
            
            # Get the raw string response from Gemini
            try:
                raw_response = batch_item['response']['candidates'][0]['content']['parts'][0]['text']
                instrumentation_json = _parse_instrumentation_response(raw_response)
            except (KeyError, json.JSONDecodeError) as e:
                print(f"Error parsing Gemini response for ID {work_id}: {e}")
                continue

            # Look up missing info from our original data
            original_work = lookup.get(work_id)
            if not original_work:
                print(f"Warning: ID {work_id} not found in original JSON.")
                continue

            title = original_work.get('pealkiri')
            original_text = original_work.get('koosseis')

            # 4. Insert into MariaDB
            query = """
                INSERT INTO teosed_koosseisud 
                (teosed_id, pealkiri, koosseis_tekst, intrumentatsioon)
                VALUES (%s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                pealkiri = VALUES(pealkiri),
                koosseis_tekst = VALUES(koosseis_tekst),
                intrumentatsioon = VALUES(intrumentatsioon)
            """
            
            try:
                cursor.execute(query, (
                    work_id, 
                    title, 
                    original_text, 
                    json.dumps(instrumentation_json, ensure_ascii=False)
                ))
                inserted_count += 1
            except mysql.connector.Error as e:
                print(f"DB Error for ID {work_id}: {e}")

    conn.commit()
    cursor.close()
    conn.close()
    print(f"Finished! Successfully updated {inserted_count} rows in 'teosed_koosseisud'.")

if __name__ == "__main__":
    insert_results()