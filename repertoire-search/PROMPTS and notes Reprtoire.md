# Repertoire search by instrumentation on emic.ee


## General task and planning
Task: Add search option for EMIC repertoire search according to instrumentation:

- draw instrumentation infor as text from database, analyse it with AI and return structured description of instrumentation (json/database fields, labels)

- go through existing records

- add functionality to data input (text input -> structured data)

- support Estonian and English  

- create UI for detailed search


### Steps

1. Draw test info from emic, store it in json format.

 for example Toivo Tulev https://www.emic.ee/?sisu=heliloojad&mid=32&id=100&lang=est&action=view&method=teosed

- create a script that pulls divs with class "teose-info" and saves them to a json file
(organized by categoory in <h4>, title from class div.teose-title). Json format:


- create list of categories (English)

2. Work out instrumentation structure

3. Create script that goes through test-json records and creates structured data. Mark problematic records. AI ? Independent algorithm?

4. Read and check credibility, update system   

5. Create proof-of-concept UI


## Prompts and notes

### 1. Gather test data 

Create a python script that pulls infomration from given page, for example Toivo Tulev https://www.emic.ee/?sisu=heliloojad&mid=32&id=100&lang=est&action=view&method=teosed

Store the result in json format, file test-data.json:

```json

[{ "composer":"", 
"compositions": [  
    { "category": "", 
    "works": [{title: "", description: ""}]
    }
]
}]
```

You can find the data on the page as follows 

composer: h1 with class "entry-title"
category: h4
title: div with class "teose-title"
description: div with class "teose-info"

## 2. Work out instrumentation structure

Some resources:

ISMLP tagging system: https://imslp.org/wiki/imslp%3Atagging
Music Encoding Initiative: https://music-encoding.org 
Instrument abbreviations list: https://www.loc.gov/aba/publications/FreeLCMPT/MEDIUM.pdf
Orhestral abbreviations:https://daniels-orchestral.com/other-resources/abbreviations/
Musicbrainz instrument list: https://musicbrainz.org/doc/MusicBrainz_Database/Tables/Instrument 


Principle: see below (prompt for AI)

## 3. Create script: test-data -> Gemini -> structured data

TODO: figure out Gemini rate limits, cost for billing.

PROMPT:
Create a python script that reads instrumentation descriptions from test-data.json and creates a chat with Gemini API chat with the following background and then feeds intrumentation desciriptions form test-data.json to that and returnes structured data. Mark problematic records. Save the succeeded results in 'test-instrumentations.json', failed fields in 'failed-instrumentations.json'.

For reading info from test-data, the instrumentation is usually in 'description' field of 'works' list, when the string is split by '\n\n', the instrumentations is usually the third element of the array.

Read the API key from environments as GEMINI_API_KEY. See concert-calendar/events_to_json.py for example.   


Background info for Gemini chat:


'''
System Instruction

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

'''

