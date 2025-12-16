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

