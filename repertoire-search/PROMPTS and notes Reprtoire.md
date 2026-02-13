# Repertoire search by instrumentation on emic.ee


1. tööta välja instrumentatsiooni sturktuur
- kogu testkijed ? kas vaja katergooria või see tabelitest?
- lase läbi Gemini skripti
2. olemasolev andmebaas struktuurikirjetesse
3. Otsingu leht
4. Toimetamise leht (json in textarea)
5. Uue sisestuse süsteem


## 1. Instrumentatsiooni struktuur

1) olemasolevate tabelite uurimine

SQL, et kuvada helilooja, pealkiri, koosseis, zanr vastavalt teose ID-le:

SELECT 
    teosed_tekstid.pealkiri, 
    teosed_tekstid.koosseis, 
    tooted_kategooriad.nimi,
    heliloojad.nimi AS helilooja_nimi
FROM 
    teosed_tekstid
JOIN 
    teosed ON teosed_tekstid.teosed_id = teosed.id
JOIN 
    tooted_kategooriad ON teosed.zanr = tooted_kategooriad.id
JOIN 
    heliloojad_teosed ON teosed.id = heliloojad_teosed.teosed_id
JOIN 
    heliloojad ON heliloojad_teosed.heliloojad_id = heliloojad.id
WHERE 
    teosed_tekstid.keel = 'est' 
        RAND()
LIMIT 100;

 Salvestatud faili test_data2.json
 
 Puhast test_data2.json
 PROMPT: open test_data2_raw.json and clean fields from html tags, white space marking (like \t\n\r), keep only the inner text. Save the result to test_data2.json
 TODO: sarnane puhastus tabelis teosed_tekstid.koosseid ja ka .esiettekanne .cd, .lisainfo, 

-- Rainile: teosed_tekstid väga paljud teksitilised sisestused html tagidena, nt <p>viiul, kitarr</p>
vt skript clean_database_field.py


#### Vaja tabelit/jsoni 'instrumendid': id, lyhend, nimi_est, nimi_eng



#### töö struktuuriga:


// TODO: hiljem, sisestamisvormis on vaja tekitada kategooriate, pillide jm vajaliku lisamise võimalus

  {
  "instrumentation": {
    "total_player_count": 0, // keep 0 if orchestra or choir or other otherwise unknown
    "electronics": {
      "type": "phonogram|live|fixed_media|electronics",
      "details": "optional description"
    }
    "has_vocal": false,
    "ensembles": [
      {
        "ensemble_id": "string_orchestra", // TODO: tabel ansamblitest!
        "player_count": 0,
        "standard": true
        "note": "",
        "note_est": ""
      }
    ]   
    "parts": [
      {
        "instrument_id": "vln",
        "alternative_instruments": [], // e.g. "for flute or oboe or violin"
        "doubles": [], // eg. for flute, piccolo, alto flute, one player
        "count": 1,
        "role": "soloist|obligato|normal|..."
      }
    ],
    // optional, only if for orchestra
    "orchestral_layout": {
      "woodwinds": [2, 2, 2, 2],
      "brass": [4, 2, 3, 1],
      "percussion": {
        "timpani": true,
        "other_players": 2,
        "extra": [
          { "instrument_id": "tamtam", "count": 1 },
          { "instrument_id": "piatti", "count": 1 }
        ]
      }
      "strings": true,
      "other": [
        { "instrument_id": "pno", "count": 1 },
        { "instrument_id": "beatbox", "count": 1 }
      ]
    },
    // optional, only when voices/choir
    "vocal_details": {
       "is_choir": true,
       "choir_type": "mixed|male|female|children|toddlers|boys|other|none", 
       "voices": 3,  
       "voice_distribution": ["S", "S", "A"],
       "soloists": [],
       "other": ""
    },
    "note": "Anything that can needs to be added",
    "note_est": "Ükskõik, mida vaja lisada"
    "scoring_variants": [
      {
        "label": "mixed choir",
        "instrumentation": { ... }
      },
      {
        "label": "male choir",
        "instrumentation": { ... }
      }
    ]
  }
}  

VAJA: tabelid -  ansamblid ()

#### lisa uus tabel teosed_koosseisud
struktuuriga
+---------+---------+------+-----+---------+-------+
| Field   | Type    | Null | Key | Default | Extra |
+---------+---------+------+-----+---------+-------+
| teoseId | int(11) | NO   | MUL | NULL    |       |
| koosseis| JSON    | NO   |     | NULL    |       |
+---------+---------+------+-----+---------+-------+


### Gemini API
Payed Tier 1 -  päevane limiit 1500 päringut päevas - u 21 päeva
Tier 2 - limiit 





## Otsingud:

Otsi, kas pill on:

SELECT title  FROM teosed_koosseisud  WHERE JSON_OVERLAPS(     instrumentation->'$.parts[*].instrument_id',      CAST('["vn"]' AS JSON) );

VÕI (töötab ka mariadb-s):
SELECT title 
FROM teosed_koosseisud 
WHERE JSON_SEARCH(instrumentation, 'all', 'vn', NULL, '$.parts[*].instrument_id') IS NOT NULL;

Veel näiteid (boolean):
SELECT title, JSON_VALUE(instrumentation, "$.total_player_count") AS Players
FROM teosed_koosseisud where !JSON_VALUE(instrumentation, "$.has_vocal");






----------------------------
 
 FOR PROTOTYPE

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

## 4. Create web based search prototype

Prompt: 

Create a web based search ptorotype test-rep.html similar to ../concert-calendar/test.html.

User should be able to search by instrumentation for now. Search fields:
- Category (solo, chamber, ensemble, orchestra, choir, vocal, open)
- Total player count (1-6)
- Has electronics
- Has vocal
- Ensembles (list of ensembles)
- Parts (list of parts)
- Free text search(any of the fields contains)

Display the result as a table with the following columns:
- Title
- Composer
- Category
- Total player count
- Has electronics
- Has vocal
- Ensembles
- Parts



