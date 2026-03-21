from google import genai
from google.genai import types
import datetime


API_KEY = os.environ.get("GEMINI_API_KEY")
if not API_KEY:
    print("GEMINI_API_KEY not found.")
    sys.exit(1)

client = genai.Client(api_key=API_KEY)

# Read your existing 9,000-token prompt
with open("system_prompt.txt", "r", encoding="utf-8") as f:
    instructions = f.read()

# Create the cache using Gemini 2.5 Flash
# Note: On 2.5 models, caching gives a 90% discount on input tokens!
cache = client.caches.create(
    model="models/gemini-2.5-flash", 
    config=types.CreateCachedContentConfig(
        display_name="orchestral_parser_v1",
        system_instruction=instructions,
        ttl=datetime.timedelta(hours=24), # Cache stays alive for 24h
    )
)

print(f"Cache created! Resource name: {cache.name}")
# Save this name! It looks like: 'projects/.../locations/.../cachedContents/123456'