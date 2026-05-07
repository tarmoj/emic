# Prompt for input prototype

Make a php page that takes input for instrumentation as text and makes an Gemini API call to convert that into instrumentation json format. 
Support receiving the instrumentation text also via API call and return JSON received from Gemini API call to the caller.

## UI

Language:  Estonian, style: very simple
Title: EMIC instrumentatsiooni sisestamine

Input elements:

- "Teose ID" (number 1..50000)
- "Intrumentatsioon" (text area, 4 rows)
- submit button "Teisenda" 
- button "Tühjenda"

Output:
 - "Väljund" (text area to show the returned JSON)

 ## GEMINI API

 Similar to ../py/process_instrumentation.py but realize it in php.

 API key is in api-key.txt. Take care that it is not revealed for ecternal user.

 Use system prompt from ./system_prompt.txt

 Ask for response in valid json format.


## Logic

When user inputs a text or the data is received via API call, display the input text from POST/GET data, call Gemini API to convert it into JSON structure, display the result and return to caller, if JSON is valid.

## Examples

Teose ID: 42
Instrumentation: "soprano, 2 tenors, bass; early music consort; symphony orchestra: 1011, 1100, 1+0, strings"

Expected response:

{"total_player_count": 0, "electronics": null, "has_vocal": true, "ensembles": [{"ensemble_id": "early_music_consort", "player_count": 0, "standard": false, "note": "", "note_est": ""}, {"ensemble_id": "symphony_orchestra", "player_count": 0, "standard": true, "note": "1011, 1100, 1+0, strings", "note_est": ""}], "parts": [{"instrument_id": "S", "alternative_instruments": [], "doubles": [], "count": 1, "role": "normal"}, {"instrument_id": "T", "alternative_instruments": [], "doubles": [], "count": 2, "role": "normal"}, {"instrument_id": "Bs", "alternative_instruments": [], "doubles": [], "count": 1, "role": "normal"}], "orchestral_layout": {"woodwinds": [1, 0, 1, 1], "brass": [1, 1, 0, 0], "percussion": {"timpani": true, "other_players": 0, "extra": []}, "strings": true, "other": []}, "vocal_details": {"is_choir": false, "choir_type": "none", "voices": 4, "voice_distribution": ["S", "T", "T", "Bs"], "soloists": [], "other": ""}}


## Test page

Create similar test page that reads user input for work ID and instrumentation, calls the host php page and displays returned value.