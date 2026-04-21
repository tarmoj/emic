import json

CACHE_NAME = "cachedContents/ayo8l0lus1c89w5ppl0ypr1284n9gsuzz7fxvhxa" # From Step 1

def create_cached_batch_file(input_data_path, output_jsonl_path):
    with open(input_data_path, 'r', encoding='utf-8') as f:
        data = json.load(f)

    with open(output_jsonl_path, 'w', encoding='utf-8') as out:
        for item in data:
            # Each request is now tiny because instructions are in the CACHE
            batch_request = {
                "key": str(item['id']),
                "request": {
                    "model": "models/gemini-2.5-flash-lite",
                    "cached_content": CACHE_NAME,
                    "contents": [
                        {"parts": [{"text": item['koosseis']}]}
                    ]
                }
            }
            out.write(json.dumps(batch_request) + '\n')

create_cached_batch_file("teosed_koik.json", "gemini_batch_cached.jsonl")