# Repertoire search by instrumentation on emic.ee


## General task and planning
Task: Add search option for EMIC repertoire search according to instrumentation:

- draw instrumentation infor as text from database, analyse it with AI and return structured description of instrumentation (json/database fields, labels)

- go through existing records

- add functionality to data input (text input -> structured data)

- support Estonian and English  

- create UI for detailed search


### Steps

1. Draw test info from emic, for example Toivo Tulev https://www.emic.ee/?sisu=heliloojad&mid=32&id=100&lang=est&action=view&method=teosed

- create a script that pulls divs with class "teose-info" and saves them to a json file
(organized by categoory in <h4>, title from class div.teose-title). Json format:

{ "composer":"", 
"ouvre": [  
    {category: "", 
    works: [{title: "", instrumentation: "", year: ""}]}
]
}

- create list of categories (English)

2. Work out instrumentation structure


3. Create script that goes through test-json records and creates structured data

4. Read and check credibility, update system   

5. Create proof-of-concept UI
