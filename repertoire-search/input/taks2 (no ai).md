Work out an alternative input system that is based on dynamic html elements and does not use AI. The aim is to get an insrumentation JSON in format like:

{"total_player_count": 0, "electronics": null, "has_vocal": true, "ensembles": [{"ensemble_id": "early_music_consort", "player_count": 0, "standard": false, "note": "", "note_est": ""}, {"ensemble_id": "symphony_orchestra", "player_count": 0, "standard": true, "note": "1011, 1100, 1+0, strings", "note_est": ""}], "parts": [{"instrument_id": "S", "alternative_instruments": [], "doubles": [], "count": 1, "role": "normal"}, {"instrument_id": "T", "alternative_instruments": [], "doubles": [], "count": 2, "role": "normal"}, {"instrument_id": "Bs", "alternative_instruments": [], "doubles": [], "count": 1, "role": "normal"}], "orchestral_layout": {"woodwinds": [1, 0, 1, 1], "brass": [1, 1, 0, 0], "percussion": {"timpani": true, "other_players": 0, "extra": []}, "strings": true, "other": []}, "vocal_details": {"is_choir": false, "choir_type": "none", "voices": 4, "voice_distribution": ["S", "T", "T", "Bs"], "soloists": [], "other": ""}}

Take into account that the insrumentation can be very different and there are optional parts in the JSON that do need to be filled in. 

Many of the elements are probably select-options dropdown filled with data from tables "ansamblid" and "Instrumendid"

Add also possibility to append new ensembles and instruments to the tables, if necessary.

Create a separate page, if it does not seem reasonable and cleaner not to add it to the index.php

Add a textedit, where the constructed JSON can be seen and edited.


Example settings to consider:
soprano, 2 tenors, bass; early music consort; symphony orchestra: 1011, 1100, 1+0, strings
violin, vibraphone, guitar
piano, symphony orchestra: 2222, 0220, percussion, strings
voices, oriental instruments, electronics
voice, accompaniment
alto, male choir, English handbell ensemble
keyboards, electronic and acoustic percussion
bamboo flute (recommended Japanese uta-shinobue), chamber orchestra: percussion, string orchestra
3 brass ensembles
taiko-ensemble, symphony orchestra: 3333, 4331, 1+2, celesta, harp, strings
mixed choir, uta-shinobue bamboo flute (can also be performed with other flutes)
violin, symphony orchestra: 2201, 0331, 0+4, harpsichord, strings
harpsichord, chamber orchestra: 0000, 0100, timpani (ad libitum), strings
flute, violin
symphony orchestra: 3323, 4331, 1+4, strings
violin, synthesizer, percussion
piano
flutes, English handbell ensemble, electronics
symphony orchestra: 3030, 0331 1+5, strings
flute, trombone, percussion, electronics
voice, accompaniment
soprano saxophone, chamber orchestra: 0000, 0000, percussion, strings
voice, Indian musical instruments, electronics
early music ensemble: tenor, bariton, bass, discant shalmey (or oboe), bass dulcian (or bassoon), tenor trombone, bell, drum, positive organ, treble gamba (or violin), bass gamba (or cello), violone (or double bass)
symphony orchestra: 3322, 4231, 1+2, strings
harpsichord or piano
keyboards, electronic and acoustic percussion
voice, accompaniment
