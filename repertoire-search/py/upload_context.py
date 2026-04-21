from google import genai
import os
import sys

API_KEY = os.environ.get("GEMINI_API_KEY")
if not API_KEY:
    print("GEMINI_API_KEY not found.")
    sys.exit(1)

client = genai.Client(api_key=API_KEY)

with open("system_prompt.txt", "r", encoding="utf-8") as f:
    instructions = f.read()

cache = client.caches.create(
    model="models/gemini-2.5-flash-lite",
    config={
        "display_name": "orchestral_parser_v1",
        "system_instruction": instructions,
        "ttl": "86400s",
    },
)

print(f"Cache created! Resource name: {cache.name}")

