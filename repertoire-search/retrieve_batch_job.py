import sys
import os

from google import genai

# --- Configuration ---
API_KEY = os.environ.get("GEMINI_API_KEY")
if not API_KEY:
    print("GEMINI_API_KEY not found.")
    sys.exit(1)
    
BATCH_JOB_ID = "batches/z943d40ijhs0172fi6fkoxkm1yr05736liqz"

client = genai.Client(api_key=API_KEY)

def check_and_download():
    print(f"Checking status for {BATCH_JOB_ID}...")
    
    # Fetch the job status
    job = client.batches.get(name=BATCH_JOB_ID)
    state = job.state.name
    
    print(f"Current Status: {state}")

    if state == 'JOB_STATE_SUCCEEDED':
        # The job finished! Now we get the filename for the output
        output_file_name = job.dest.file_name
        print(f"✅ Job Complete! Downloading results from: {output_file_name}")
        
        # Download the actual data
        content_bytes = client.files.download(file=output_file_name)
        
        output_local_path = "gemini_results_final.jsonl"
        with open(output_local_path, "wb") as f:
            f.write(content_bytes)
            
        print(f"🚀 Success! Data saved to {output_local_path}")
    
    elif state in ['JOB_STATE_FAILED', 'JOB_STATE_CANCELLED']:
        print(f"❌ The job stopped. Reason: {state}")
        if hasattr(job, 'error'):
            print(f"Error Details: {job.error}")
            
    else:
        print("⏳ Still working... Check back in a bit.")

if __name__ == "__main__":
    check_and_download()