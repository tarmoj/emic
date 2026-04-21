import sys
import time
import json
from google import genai
import os

# --- Configuration ---
API_KEY = os.environ.get("GEMINI_API_KEY")
if not API_KEY:
    print("GEMINI_API_KEY not found.")
    sys.exit(1)
        
INPUT_FILE_PATH = "gemini_batch_cached.jsonl"
MODEL_ID = "gemini-2.5-flash-lite"

client = genai.Client(api_key=API_KEY)

def run_batch_process():
    # 1. Upload your JSONL to the Gemini File API
    print(f"Uploading {INPUT_FILE_PATH}...")
    uploaded_file = client.files.upload(
        file=INPUT_FILE_PATH,
        config={'mime_type': 'application/jsonl'}
    )
    print(f"File uploaded successfully: {uploaded_file.name}")

    # 2. Create the Batch Job
    print(f"Submitting batch job to {MODEL_ID}...")
    batch_job = client.batches.create(
        model=MODEL_ID,
        src=uploaded_file.name,
        config={'display_name': 'Musicology_Data_Analysis'}
    )
    
    job_id = batch_job.name
    print(f"Batch job created. ID: {job_id}")

    # 3. Monitor the Job
    while True:
        status = client.batches.get(name=job_id)
        state = status.state.name
        
        if state == 'JOB_STATE_SUCCEEDED':
            print("\n✅ Batch job completed!")
            
            # 4. Download Results
            output_file_name = status.dest.file_name
            print(f"Downloading results from {output_file_name}...")
            
            content_bytes = client.files.download(file=output_file_name)
            with open("gemini_batch_output.jsonl", "wb") as f:
                f.write(content_bytes)
            
            print("Done! Results saved to: gemini_batch_output.jsonl")
            break
            
        elif state in ['JOB_STATE_FAILED', 'JOB_STATE_CANCELLED']:
            print(f"\n❌ Job failed or was cancelled. State: {state}")
            if hasattr(status, 'error'):
                print(f"Error details: {status.error}")
            break
        else:
            # Poll every 60 seconds (Batch jobs are not instantaneous)
            print(f"Status: {state}... checking again in 60s", end="\r")
            time.sleep(60)

if __name__ == "__main__":
    run_batch_process()