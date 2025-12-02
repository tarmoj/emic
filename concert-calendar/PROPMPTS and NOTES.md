
# 2. Events to json

Create a python script events_to_json.py tha analyses text file 'test-events.txt' and stores the information in json format. 
The events are separated by "####" in the file.
Use Gemini API for the language analyses. The API_KEY is set in the env. variable GEMINI_API_KEY
Save the  output file as test-events.json

Set the following context for Gemini:
'''
Analyse given musical event, that is mostly a concert, and return the information in json format:  

{
    "title": "",
    "date": "",
    "time": "",
    "location": "",
    "performers": "",
    "program:" "",
    "description": "",
    "tickest": "",
    "link": "",
    "other_info": ""
}

For the date and time format use something that will be easy to convert MySQL date format later.

The input is text in Estonian. Keep the language for the entries. 
The information is presented mostly as follows:
title:  first row
date: second row
location: third row
The other fields can come in different order.
info: is usually a link, preceded by 'Lisainfo:' in Estonian

All fields must not be filled. If there is doubt, return string that starts with "PROBLEMS FOUND:\n", add the event and comments about problematic bits.

'''

send the info by event to event a function that deals with Gemini API. If call was successful, store the events in format:
{
    "events": []
}

if a problem was found (return string starts with 'PROBLEMS') append it to file 'problems.txt', separate events with '####'

# 1. GET EVENTS:

Aim: Gather data about all concerts/event on the pages:
https://www.emic.ee/muusikasundmuste-kalender&year={year} 
where year is 2014..2025

to into text files, naming them events-{year}.txt, separate events by delimiter '####'

Create a Python script to accomplish this task.

Use either Selenium or https requests (perhaps wiser?) to get the contents.

## Structure of the pages:

Each page by the year has a list of events  in divs <div class="post-item-excerpt">
where there is a child element <h2> with link to the entire post like:
<h2 class="post-title"><a href="?sisu=syndmus&amp;mid=209&amp;id=15&amp;lang=est">



Example of one event excerpt:

<div class="post-item-excerpt">
                        <h2 class="post-title"><a href="?sisu=syndmus&amp;mid=209&amp;id=15&amp;lang=est">Kontsert „Õhtuvalgel hing on valla võluilma väele”</a></h2>
                        <div class="post-date">
                            11.12.2014
                             Koht: Metodisti kirik                             Kell: 18:00                                                    </div>
                        <p>
                            11. detsembril kell 18 toimub Tallinna Metodisti kirikus Tallinna Muusikakeskkooli kontsert „Õhtuvalgel hing on valla võluilma väele”. Kontserdil tuleb esiettekandele Pärt Uusbergi teos „Kolm talvepilti“, mis sündis heliloojal koostöös dirigent Ingrid Kõrvitsaga.
                        </p>
                        <div class="text-right"><a href="?sisu=syndmus&amp;mid=209&amp;id=15&amp;lang=est">Loe edasi</a></div>
</div>


Open that link (or make a https request) and get contents. 



### Structure of an event page

The info is in div with id  "main-content".

Ignore the <h1> Muusikasündmuste Kalender,

append other information (inner html) to the related text file (except link "Tagasi")


Example content:

<div id="main-content" class="col-md-8" role="main">

<h1 class="entry-title">Muusikasündmuste kalender</h1>
<h2>Remy Martin’i ja Eesti Kontserdi uusaastakontsert</h2>
<p class="meta">
    01.01.2026
    <br>Koht: Estonia kontserdisaal, Tallinn    <br>Kell: 18:00</p>
<div class="event-content">
    <span style="color:#231f20">Hans Christian Aavik (viiul)</span><br>
<span style="color:#231f20">Elina Nechayeva (sopran)</span><br>
<span style="color:#231f20">Martin Kuuskmann (fagott)</span><br>
<span style="color:#231f20">Eesti Rahvusmeeskoor</span><br>
<span style="color:#231f20">Eesti Riiklik Sümfooniaorkester</span><br>
<span style="color:#231f20">Dirigent Neeme Järvi</span><br>
<br>
<span style="color:#231f20">Õhtujuht Mait Malmsten</span><br>
<br>
<span style="color:#231f20">Teeme pidulikul ja värvikal kontserdil tagasivaate maestro 75-aastasele dirigendikarjäärile, alates avamängust Johann Straussi operetile „Öö Veneetsias“ oma debüüdil 1950. aastal Estonia teatris kuni kõige uuemate muusikaüllatusteni. Tuletame meelde loomingulisi kaasteelisi, nagu Helju Koppa, Uve Uustalu, Lemmo Erendy, ja räägime teatrilegendidest läbi aja – Alfred Mering, Kalju Vaha, Hugo Malmsten …</span><br>
<br>
<span style="color:#231f20">Pilet:&nbsp;</span><strong><a href="https://concert.ee/kontsert/remy-martini-ja-eesti-kontserdi-uusaastakontsert-4/?shop_provider=erso" style="box-sizing:inherit; color:#54785c; text-decoration:none; outline:none !important">Eesti Kontsert</a></strong><br>
<span style="color:#231f20">Lisainfo:&nbsp;</span><strong><a href="https://erso.ee/kontserdid/remy-martini-ja-eesti-kontserdi-uusaastakontsert-4/" style="box-sizing:inherit; color:#54785c; text-decoration:none; outline:none !important">ERSO</a></strong><br>
&nbsp;
    <div class="clear"></div>
    <div class="event-image"><img src="failid/genPictures/thumb_x_300/1761731314.jpg" alt="image"></div></div>
<br>
<a href="muusikasundmuste-kalender">Tagasi</a>

</div>


