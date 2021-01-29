# Introduzione

Meteonet è il backend di meteogargano che gestisce i dati in arrivo dalle stazioni meteorologiche.
Si occupa di caricare i dati, salvarli in un database e metterli a disposizione tramite API RESTful.

Nasce come plugin per wordpress, quindi presenta anche una parte di frontend in Javascript, che non
è più utilizzata. 

Il software è stato sviluppato nel 2012-2013 e alcune delle tecnologie e dei linguaggi usati
sono obsoleti.

# Descrizione originale

## Cos'è MeteoNET?

E' un plugin per Wordpress (famoso gestore di contenuti web) per la gestione di reti composte da stazioni meteorologiche e fotocamere ottimizzato per
funzionare su server web non dedicati, ovvero all'interno di gran parte dei siti web in shared hosting (in configurazione base).
Consiste in una interfaccia grafica e in un insieme di script installati sul server (d'ora in poi chiamati 
'modulo server' che gestiscono i dati conservati all'interno di una base dati e la comunicazione tra l'interfaccia e la base dati.
Attualmente l'integrazione in Wordpress è volutamente ridotta all'osso, in maniera tale da utilizzare il software anche al
di fuori di Wordpress con poche semplici e modifiche.

## Come funziona MeteoNET?

Il modulo si occupa di recuperare tutti i dati pervenuti in una cartella del server attraverso il protocollo FTP, li
inserisce all'interno di una tabella del database (gli scatti in una tabella, i record meteorologic in un'altra),
calcola al volo le statistiche giornaliere e salva gli ultimi dati meteorologici
in ordine di tempo in una <i>memoria cache</i> temporanea. Tale insieme di operazioni di aggiornamento dati
viene eseguita prima di ogni richiesta o ad intervalli di mezz'ora tramite un'operazione pianificata o <i>cronjob</i>.
Il tempo che intercorre tra una operazione e l'altra non può essere minore di un minuto. Ogni 24 ore viene eseguita l'operazione
di computazione dei dati, cio&grave; il calcolo dei valori statistici di media, minima e massima di ogni campo su intervalli 
temporali di 5 minuti e di 24 ore e cancella i record originali. I dati coinvolti riguardo quelli a partire dal giorno precedente.
Questa operazione riduce i dati memorizzati all'interno del database e velocizza la visualizzati dei dati di archivio 
ed il calcolo delle statistiche mensili e annuali. Inoltre vengono cancellati tutti gli scatti del giorno precedente ad eccezione 
del primo di ogni ora del giorno.
A seconda della richiesta che perviene dall'interfaccia grafica, il modulo web esegue le opportune richieste al database e le rimanda 
all'interfaccia.
Quando la rete contiene terminali che non sono configurabili per mandare i dati direttamente al server, Meteonet mette a disposizione
un modulo chiamato 'router', da installare in un server dedicato perché, sebbene non avido di risorse, richiede di fare continuamente delle
richieste ai vari terminali. 

## Come nasce MeteoNET e perché si presenta così?

Meteonet nasce per la gestione di una rete meteorologica di stazioni e webcam ubicate sul Promontorio del Gargano (consultare il sito
www.meteogargano.org per ulteriori informazioni), come alternativa all'utilizzo di interfacce separate tipiche dei siti amatoriali e 
dei portali pi&ugrave; grandi come Wunderground. Tali portali, sebbene hanno il vantaggio di far ottenere grande visibilità alle
proprie stazioni e renderle raggiungibili a un vasto pubblico, non si adattavano bene agli scopi del nostro progetto perché troppo 
dispersivi. A ciò si aggiungeva la volontà da parte mia di sperimentare alcune librerie e di fare un lavoretto originale.
Il motivo per cui il software si presenta come plugin per Wordpress è che nel ristrutturare il portale scelsi Wordpress e volevo
evitare di fare modifiche "sporche" al codice sorgente di Wordpress. Sebbene negli intenti iniziali il plugin avrebbe dovuto gestire
un'interfaccia mista html/javascript, ho scelto di svincolare l'interfaccia dalla struttura del sito utilizzando una modalità a finestra
il cui contenuto viene gestito e generato esclusivamente a <i>runtime</i>.

## Posso utilizzare Meteonet per la mia rete meteorologica?

La mia intenzione è di utilizzare per questo programma una licenza permissiva, quindi il software sarà open-source. 
Tuttavia mi occorre un po' di tempo per pulire il codice e pubblicarlo. 

## Quali funzionalità vanno aggiunte o migliorate? 
Bisogna aggiungere la funzionalità di visualizzare grafici, di interpolare tra loro i dati e di visualizzare mappe 2D interpolate.

## Credits
Autore: Filippo Gurgoglione. Email: ziofil@gmail.com
Principali librerie utilizzate: PHP; OpenGD; jQuery; jQuery plugins: jQuery timer, flexigrid.

## Changelog
 - ver. 0.1.1: correzione bugs vari; miglioramento interfaccia grafica; gestione dati non corretti; aggiunta note nella pagina informazioni stazione (18/12/12)</li>
 - ver. 0.2: aggiunta gestione stringhe dati software Open2300; correzione bugs (16/01/2013)</li>
 - ver. 0.3: aggiunta manipolazione immagini webcam (01/03/2013)</li>
