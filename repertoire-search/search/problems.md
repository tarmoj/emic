# Problems in search implementation


 Many mistakes in Estonian text.

 Why is all filters open returning only 1149 works?

 Why "LAVAMUUSIKA" returns 5 works but EMIC page 8 (test more!)

 Search by Pealkiri/Title" - return error:
 Error: HTTP 500
    fetchJson http://localhost:8000/js/search.js:27
 statusText: "Internal Server Error"
 options: '{"genreId":0,"composerId":0,"title":"Sonaat","keyword":"","bornYearFrom":1845,"bornYearTo":2026,"compositionYearFrom":1845,"compositionYearTo":2026,"durationFrom":0,"durationTo":480,"performersFrom":0,"performersTo":100,"soloistsFrom":0,"soloistsTo":20,"onlySelectedInstruments":false,"selectedInstruments":[],"page":1,"perPage":50}'    
FIXED

Koosseis (instrumentation) return error:
Error. Response: 
Response { type: "basic", url: "http://localhost:8000/api/instruments.php?q=fl", redirected: false, status: 500, ok: false, statusText: "Internal Server Error", headers: Headers(5), body: ReadableStream, bodyUsed: false }
 ./api/instruments.php?q=fl 


Is SQL right? Looking for instrumentation fl, vn, gtr:
(search.php):


When composer and instrumentation are given, it is not filtered by composer. There is no WHERE condition

If I look for pieces for one flute, it return also flute + orchestra. Check JSON, for example
Eespere, René - Flöödikontsert nr. 1 

'Soloists' is not consistent in data. Reanalyze? Drop?

URL to emic page does not work.


