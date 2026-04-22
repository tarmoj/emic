# Create a web page to search for compositions

## General

Create a web based UI written in php. Data is in in mysql database 'emic'.
The page should be later in Estonian and English, make the first prototype in Estonian.

The aim is to extend this page: https://www.emic.ee/?sisu=otsing&mid=218&lang=est&normal=1&type=2&otsing=

so it has similar features to this: https://www.mic.lt/en/database/classical/find-works/

Keep the style simple as on emic.ee, use style defined in style.css that is from emic.ee.

User fills the necessary fields and gets output as list of works displaying:
Composer -  Title - Intrumentation_text. And it links to according work on emic.ee like this URL: https://www.emic.ee/?sisu=heliloojad&mid=58&id=18&lang=eng&action=view&method=teosed#536 

## Search fields

- Žanr (Genre)  - Dropdown
- Helilooja (Composer) - Dropdown ( in form 'surname, name' ordered by family name, ascending)
- Pealkiri (Title) - Textfield
- Otsingusõna (Keyword) - Textfield
- Helilooja sünniaasta (Born in) - range from 1845 to current year
- Loomisaasta (Year of composition) - range from 1845 to current year
- Kestus (Duration) -  range 0..480 minutes
- Esitajaid (Number of performers) - range 0..100
- Solistid (Soloists) - range (spinbox) 0..20
- Koosseis (Instrumentation) -  Text field, see below
-- Ainult valitud instrumendid/hääled (The selected instruments only) - checkbox

The "Koosseis / Instrumentation" should have a smart search -  when user types, it tries to look for suitable instrument from the instruments table (description below) and inserts its abbreviation as tag to the search field or above it. There should be also an optional dropdown with instruments (when an entry selected, add the tag).

Žanr (Genre) (or category) corresponds to field tooted_kategooriad.nimi and the linking table between composition and category/genre is in teosed_zanrid. 


## Database

Database (local): emic, user: emic, password:tobias


### Structures of tables


--
-- Table structure for table `ansamblid`
--

DROP TABLE IF EXISTS `ansamblid`;
CREATE TABLE `ansamblid` (
  `id` varchar(120) NOT NULL,
  `nimi` varchar(255) NOT NULL,
  `nimi_eng` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `heliloojad`
--

DROP TABLE IF EXISTS `heliloojad`;
CREATE TABLE `heliloojad` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `nimi` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_estonian_ci NOT NULL,
  `sunnikuupaev` varchar(100) NOT NULL,
  `surmakuupaev` varchar(100) NOT NULL,
  `sunnikoht` varchar(255) NOT NULL,
  `surmakoht` varchar(255) NOT NULL,
  `foto_autor` varchar(255) NOT NULL,
  `fail` varchar(255) NOT NULL,
  `fail_nimi_est` varchar(255) NOT NULL,
  `fail_nimi_eng` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin NOT NULL,
  `staatus` int(1) NOT NULL DEFAULT 1,
  `video` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin NOT NULL,
  `youtube` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin NOT NULL,
  `video_nimi_est` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin NOT NULL,
  `video_nimi_eng` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin NOT NULL,
  `userId` int(20) unsigned DEFAULT 0,
  `soundcloud` varchar(255) DEFAULT NULL,
  `vimeo` varchar(255) DEFAULT NULL,
  `spotify` varchar(255) DEFAULT NULL,
  `bandcamp` varchar(255) DEFAULT NULL,
  `err` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  FULLTEXT KEY `nimi` (`nimi`)
) ENGINE=MyISAM AUTO_INCREMENT=302 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Table structure for table `heliloojad_teosed`
--

DROP TABLE IF EXISTS `heliloojad_teosed`;
CREATE TABLE `heliloojad_teosed` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `teosed_id` int(10) NOT NULL,
  `heliloojad_id` int(10) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `teosed_id` (`teosed_id`),
  KEY `heliloojad_id` (`heliloojad_id`)
) ENGINE=MyISAM AUTO_INCREMENT=36348 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Table structure for table `instrumendid`
--

DROP TABLE IF EXISTS `instrumendid`;
CREATE TABLE `instrumendid` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lyhend` varchar(120) NOT NULL,
  `nimi` varchar(255) NOT NULL,
  `nimi_eng` varchar(255) NOT NULL,
  `teised_nimed` longtext DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_instrumendid_lyhend` (`lyhend`)
) ENGINE=InnoDB AUTO_INCREMENT=118 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `teosed`
--

DROP TABLE IF EXISTS `teosed`;
CREATE TABLE `teosed` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `aasta` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin NOT NULL,
  `zanr` int(10) NOT NULL,
  `pikkus` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin NOT NULL,
  `fail` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin NOT NULL,
  `fail_nimi_est` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin NOT NULL,
  `fail_nimi_eng` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin NOT NULL,
  `pdf_tekst_est` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `pdf_tekst_eng` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `pdf` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin NOT NULL,
  `tootekood` int(1) NOT NULL DEFAULT 1,
  `video` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `youtube` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `pdf_poes` tinyint(1) NOT NULL DEFAULT 1,
  `userId` int(11) NOT NULL,
  `staatus` int(1) NOT NULL DEFAULT 1,
  `news` tinyint(1) NOT NULL DEFAULT 0,
  `news_priority` int(11) NOT NULL DEFAULT 0,
  `naita_eesti_pealkirja` smallint(1) unsigned NOT NULL,
  `soundcloud` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `vimeo` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `spotify` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `bandcamp` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `err` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `zanr` (`zanr`),
  KEY `userId` (`userId`),
  KEY `staatus` (`staatus`),
  KEY `news` (`news`),
  KEY `naita_eesti_pealkirja` (`naita_eesti_pealkirja`)
) ENGINE=MyISAM AUTO_INCREMENT=35418 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Table structure for table `teosed_koosseisud`
--

DROP TABLE IF EXISTS `teosed_koosseisud`;
CREATE TABLE `teosed_koosseisud` (
  `teosed_id` int(11) NOT NULL,
  `pealkiri` varchar(255) NOT NULL,
  `koosseis_tekst` text DEFAULT 'NULL',
  `intrumentatsioon` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT 'NULL',
  PRIMARY KEY (`teosed_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

--
-- Table structure for table `teosed_tekstid`
--

DROP TABLE IF EXISTS `teosed_tekstid`;
CREATE TABLE `teosed_tekstid` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `teosed_id` int(10) NOT NULL,
  `keel` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_estonian_ci NOT NULL,
  `pealkiri` text CHARACTER SET utf8mb3 COLLATE utf8mb3_estonian_ci NOT NULL,
  `ppealkiri` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `seletusrida` text CHARACTER SET utf8mb3 COLLATE utf8mb3_estonian_ci NOT NULL,
  `koosseis` text CHARACTER SET utf8mb3 COLLATE utf8mb3_estonian_ci NOT NULL,
  `tekstiAutor` text CHARACTER SET utf8mb3 COLLATE utf8mb3_estonian_ci NOT NULL,
  `libreto` text CHARACTER SET utf8mb3 COLLATE utf8mb3_estonian_ci NOT NULL,
  `koreograafia` text CHARACTER SET utf8mb3 COLLATE utf8mb3_estonian_ci NOT NULL,
  `esiettekanne` text CHARACTER SET utf8mb3 COLLATE utf8mb3_estonian_ci NOT NULL,
  `eesti_esiettekanne` text CHARACTER SET utf8mb3 COLLATE utf8mb3_estonian_ci NOT NULL,
  `tellija` text CHARACTER SET utf8mb3 COLLATE utf8mb3_estonian_ci NOT NULL,
  `kirjastaja` text CHARACTER SET utf8mb3 COLLATE utf8mb3_estonian_ci NOT NULL,
  `levitaja` text CHARACTER SET utf8mb3 COLLATE utf8mb3_estonian_ci NOT NULL,
  `cd` text CHARACTER SET utf8mb3 COLLATE utf8mb3_estonian_ci NOT NULL,
  `lisainfo` text CHARACTER SET utf8mb3 COLLATE utf8mb3_estonian_ci NOT NULL,
  `teose_tekstid` text CHARACTER SET utf8mb3 COLLATE utf8mb3_estonian_ci NOT NULL,
  `lisatekst` text CHARACTER SET utf8mb3 COLLATE utf8mb3_estonian_ci NOT NULL,
  `puhendus` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `oarv` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `opealkirjad` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `algussonad` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `lisamarkused` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `teose_id` (`teosed_id`),
  KEY `keel` (`keel`),
  FULLTEXT KEY `pealkiri` (`pealkiri`),
  FULLTEXT KEY `seletusrida` (`seletusrida`),
  FULLTEXT KEY `koosseis` (`koosseis`),
  FULLTEXT KEY `tekstiAutor` (`tekstiAutor`),
  FULLTEXT KEY `libreto` (`libreto`),
  FULLTEXT KEY `koreograafia` (`koreograafia`),
  FULLTEXT KEY `esiettekanne` (`esiettekanne`),
  FULLTEXT KEY `eesti_esiettekanne` (`eesti_esiettekanne`),
  FULLTEXT KEY `tellija` (`tellija`),
  FULLTEXT KEY `kirjastaja` (`kirjastaja`),
  FULLTEXT KEY `levitaja` (`levitaja`),
  FULLTEXT KEY `cd` (`cd`),
  FULLTEXT KEY `lisainfo` (`lisainfo`),
  FULLTEXT KEY `teose_tekstid` (`teose_tekstid`),
  FULLTEXT KEY `lisatekst` (`lisatekst`)
) ENGINE=MyISAM AUTO_INCREMENT=70812 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;


--
-- Table structure for table `teosed_zanrid`
--

DROP TABLE IF EXISTS `teosed_zanrid`;
CREATE TABLE `teosed_zanrid` (
  `teoseId` int(11) NOT NULL,
  `zanrId` int(11) NOT NULL,
  KEY `teoseId` (`teoseId`,`zanrId`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Table structure for table `tooted_kategooriad`
--

DROP TABLE IF EXISTS `tooted_kategooriad`;
CREATE TABLE `tooted_kategooriad` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `mid` int(11) NOT NULL DEFAULT 0,
  `vanem` int(11) NOT NULL DEFAULT 0,
  `korval_menyy` enum('0','1') NOT NULL DEFAULT '1',
  `nimi` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_estonian_ci NOT NULL,
  `folder` varchar(255) NOT NULL,
  `taust` varchar(255) NOT NULL,
  `nimi_rus` varchar(255) NOT NULL DEFAULT '',
  `nimi_eng` varchar(255) NOT NULL DEFAULT '',
  `nimi_est` varchar(255) NOT NULL DEFAULT '',
  `prioriteet` int(11) NOT NULL DEFAULT 0,
  `peidetud` enum('0','1') NOT NULL DEFAULT '0',
  `prefix` varchar(16) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin NOT NULL DEFAULT '',
  `pilt` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  FULLTEXT KEY `nimi` (`nimi`)
) ENGINE=MyISAM AUTO_INCREMENT=243 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;



### Structure of instrumentation json in teosed_koosseisud:

{
  "total_player_count": 0, // keep 0 if orchestra or choir or other otherwise unknown
  "electronics": {
    "type": "phonogram|live|fixed_media|electronics",
    "details": "optional description"
  }
  "has_vocal": false,
  "ensembles": [
    {
      "ensemble_id": "string_orchestra",
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


## Example searches

SELECT 
  teosed_koosseisud.teosed_id, 
  pealkiri, 
  heliloojad.nimi
FROM teosed_koosseisud 
JOIN 
    heliloojad_teosed ON teosed_koosseisud.teosed_id = heliloojad_teosed.teosed_id
JOIN heliloojad 
    ON heliloojad_teosed.heliloojad_id = heliloojad.id  
WHERE JSON_SEARCH(intrumentatsioon, 'all', 'vn', NULL, '$.parts[*].instrument_id') IS NOT NULL;


Veel näiteid (boolean):

SELECT title, JSON_VALUE(instrumentation, "$.total_player_count") AS Players
FROM teosed_koosseisud where !JSON_VALUE(instrumentation, "$.has_vocal");


Look by instrumentation: duos flute-harp: 

SELECT 
  teosed_koosseisud.teosed_id,
  heliloojad.nimi,
  pealkiri,
  teosed_koosseisud.koosseis_tekst 
FROM teosed_koosseisud 
JOIN heliloojad_teosed 
  ON teosed_koosseisud.teosed_id = heliloojad_teosed.teosed_id
JOIN heliloojad 
  ON heliloojad_teosed.heliloojad_id = heliloojad.id  
WHERE 
  -- Requirement 1: Total player count must be 2
  JSON_EXTRACT(intrumentatsioon, '$.total_player_count') = 2
  
  -- Requirement 2: Must contain Flute ('fl')
  AND JSON_OVERLAPS(JSON_EXTRACT(intrumentatsioon, '$.parts[*].instrument_id'), '"fl"')
  
  -- Requirement 3: Must contain Harp ('hp')
  AND JSON_OVERLAPS(JSON_EXTRACT(intrumentatsioon, '$.parts[*].instrument_id'), '"hp"');

  Or look for flute duos:
  ...
  WHERE 
    JSON_EXTRACT(intrumentatsioon, '$.total_player_count') = 2
    
    AND JSON_LENGTH(JSON_EXTRACT(intrumentatsioon, '$.parts')) = 1
    
    AND JSON_VALUE(intrumentatsioon, '$.parts[0].instrument_id') = 'fl';


