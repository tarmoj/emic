import json

# Configuration
INPUT_FILE = 'teosed_koik.json'
SYSTEM_PROMPT_FILE = 'system_prompt.txt'
OUTPUT_FILE = 'gemini_batch_input.jsonl'

def prepare_batch_file():
    # 1. Load your system prompt
    with open(SYSTEM_PROMPT_FILE, 'r', encoding='utf-8') as f:
        system_instructions = f.read().strip()

    # 2. Load your dataset
    with open(INPUT_FILE, 'r', encoding='utf-8') as f:
        data = json.load(f)

    with open(OUTPUT_FILE, 'w', encoding='utf-8') as f:
        for entry in data:
            # We use the 'id' from your JSON as the unique 'key'
            # This allows you to match the results back to your database later
            request_id = str(entry.get('id'))
            
            # Construct the specific input for the model
            # We provide the instrumentation string (koosseis) as the primary task
            instrumentation_text = entry.get('koosseis', '')
            user_query = f"Parse the following instrumentation: {instrumentation_text}"

            # Create the Batch API structure
            batch_line = {
                "key": request_id,
                "request": {
                    "system_instruction": {
                        "parts": [{"text": system_instructions}]
                    },
                    "contents": [
                        {
                            "role": "user",
                            "parts": [{"text": user_query}]
                        }
                    ],
                    "generationConfig": {
                        "response_mime_type": "application/json"
                    }
                }
            }
            
            # Write as a single line in the JSONL file
            f.write(json.dumps(batch_line) + '\n')

    print(f"Success! Created {OUTPUT_FILE} with {len(data)} requests.")

if __name__ == "__main__":
    prepare_batch_file()