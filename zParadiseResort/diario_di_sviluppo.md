# Diario di Sviluppo e Guida Didattica - Paradise Resort 🏝️

Benvenuto nel diario di sviluppo del progetto **Paradise Resort**. Questo documento è stato concepito non solo come registro degli aggiornamenti, ma soprattutto come **guida didattica e di studio** per comprendere a fondo le tecnologie, l'architettura e le scelte di design adottate nel progetto, in particolare l'utilizzo del **Template Engine** fornito dal docente.

---

## 1. Il Template Engine (`template2.inc.php` e `page.inc.php`)

Il progetto non utilizza un framework MVC completo commerciale (come Laravel o Symfony), ma si basa su un **Template Engine nativo in PHP** (`template2.inc.php`) creato dal docente. Questo approccio è eccellente per comprendere i principi fondamentali della separazione tra **Logica di Presentazione (HTML/Viste)** e **Logica di Business/Controllo (PHP)**.

### Come funziona la separazione Header/Footer e Contenuto?
Invece di ripetere l'intestazione (`<head>`, menu di navigazione) e il piè di pagina (`<footer>`) in ogni file PHP, il sistema utilizza il concetto di **Frame** (cornice) e **Block** (blocco di contenuto):

1. **Il Frame (`new_page`)**:
   - Rappresenta lo "scheletro" della pagina (es. `skins/customers/dtml/frame-public.html`).
   - Contiene tutto ciò che è comune a più pagine: tag HTML globali, inclusione dei fogli di stile CSS, script Javascript, l'header con il menu di navigazione e il footer.
   - All'interno del frame è presente un placeholder speciale: `<[body]>`.
2. **Il Blocco (`new_block`)**:
   - Rappresenta il contenuto specifico di una singola pagina (es. il form di registrazione in `register.html` o la dashboard in `login.html`).
   - Il controller PHP carica questo blocco, ne valorizza i placeholder e infine lo "inietta" dentro il `<[body]>` del frame principale:
     ```php
     $skin = new_page($config['skin']);          // Carica frame-public.html
     $block = new_block('register');             // Carica register.html
     $skin->setContent('body', $block->get());   // Inietta il blocco nel <[body]>
     $skin->close();                             // Stampa la pagina finale
     ```

### I Placeholder e la Tag Syntax (`<[...]>`)
Il template engine scansiona i file HTML (nella cartella `dtml/`) alla ricerca di tag delimitati da `<[` e `]>`. I principali tipi di tag sono:
- **Variabili/Placeholder semplici:** `<[base]>`, `<[title]>`, `<[error]>`. Vengono sostituiti con i valori passati in PHP tramite `$skin->setContent('nome', 'valore')`.
- **Blocchi Condizionali:**
  - `<[if!empty variabile]> ... <[/if!empty]>`: Il contenuto interno viene mostrato **solo se** la variabile non è vuota. Molto utile per mostrare messaggi di errore/successo o link nell'header per utenti loggati.
  - `<[ifempty variabile]> ... <[/ifempty]>`: Il contenuto interno viene mostrato **solo se** la variabile è vuota (es. per mostrare i pulsanti "Accedi" e "Registrati").
- **Cicli/Foreach:** Per liste dinamiche (es. elenco stanze o servizi), gestiti dal template engine ripetendo blocchi di codice per ogni elemento di un array.

---

## 2. Risoluzione degli Errori di Path (CSS, JS, Immagini)

Durante i primi tentativi di avvio di `register.php`, il browser mostrava una pagina completamente priva di formattazione grafica ed errori nella console del tipo:
- `GET http://progetto/zParadiseResort/assets/css/nice-select.css net::ERR_NAME_NOT_RESOLVED`
- `GET http://localhost:8085/progetto/zParadiseResort///progetto/zParadiseResort/assets/js/vendor/modernizr-3.5.0.min.js net::ERR_ABORTED 404 (Not Found)`

### Analisi Tecnica delle Cause e Come abbiamo Risolto

#### A. Il doppio slash e i "Protocol-Relative URLs" (`//progetto/...`)
Nel file `frame-public.html` originale, i link erano stati scritti nel seguente modo:
```html
<!-- ERRATO -->
<link rel="stylesheet" href="/<[base]>/assets/css/bootstrap.min.css">
```
In `include/config.inc.php`, la configurazione globale stabilisce:
```php
'base' => '/progetto/zParadiseResort', // Inizia GIA' con uno slash!
```
Quando il template engine sostituiva `<[base]>`, il risultato in HTML diventava:
```html
<link rel="stylesheet" href="//progetto/zParadiseResort/assets/css/bootstrap.min.css">
```
**Perché questo causa `ERR_NAME_NOT_RESOLVED`?**
Nelle specifiche HTML/RFC degli URL, un percorso che inizia con due slash (`//`) viene interpretato come **Protocol-Relative URL** (URL relativo al protocollo, es. `//cdn.example.com/lib.js`). Il browser mantiene il protocollo attuale (`http:` o `https:`) ma interpreta la prima parola dopo i due slash come **Nome di Dominio (Host)**! Il browser tentava quindi di connettersi al server DNS per cercare l'host `progetto`, che ovviamente non esiste sulla rete locale.

#### B. Lo slash triplo e i percorsi relativi (`.///progetto/...`)
Per gli script Javascript era stato scritto:
```html
<!-- ERRATO -->
<script src=".//<[base]>/assets/js/vendor/modernizr-3.5.0.min.js"></script>
```
Con la sostituzione di `<[base]>`, questo diventava `.///progetto/zParadiseResort/...`. Il punto e slash iniziale (`./`) indica "parti dalla cartella corrente". Il browser concatenava quindi l'URL attuale con `.///progetto/...`, generating un percorso inesistente con tre slash e restituendo **404 Not Found**.

#### C. La posizione reale della cartella `assets/` all'interno delle Skin
Oltre agli errori di sintassi degli slash, c'era un errore di architettura: nel nostro progetto (così come nel progetto di riferimento `progettoFed`), la cartella `assets/` non si trova nella cartella radice del progetto, ma **all'interno della specifica skin**:
`c:\xampp\htdocs\progetto\zParadiseResort\skins\customers\assets\`

Questo permette di avere più temi grafici (es. `customers` per gli ospiti, `administration` per gli admin) ognuno con i propri stili e script indipendenti.

**La Soluzione Corretta:**
Abbiamo rimosso gli slash e i punti extra e abbiamo puntato direttamente alla cartella della skin utilizzando i placeholder `<[base]>` e `<[skin]>`:
```html
<!-- CORRETTO -->
<link rel="stylesheet" href="<[base]>/skins/<[skin]>/assets/css/bootstrap.min.css">
<script src="<[base]>/skins/<[skin]>/assets/js/vendor/modernizr-3.5.0.min.js"></script>
```
In questo modo, passando `$skin->setContent('skin', $config['skin']);` dal controller PHP, l'URL generato sarà perfettamente valido: `/progetto/zParadiseResort/skins/customers/assets/css/bootstrap.min.css`.

---

## 3. Architettura di Autenticazione e Controllo Accessi (ACL)

La sicurezza e la gestione degli utenti in Paradise Resort si basano su un'architettura **RBAC (Role-Based Access Control) e Service-Based**, implementata tramite il file `include/auth.inc.php` e le tabelle relazionali del database.

### Lo Schema delle Tabelle di Sicurezza (`sql/creazione.sql`)
Invece di avere una singola colonna "ruolo" nella tabella utenti, il database è normalizzato per supportare permessi granulari:
1. `users`: Contiene le anagrafiche e le credenziali (con password protette da `password_hash()` in PHP che genera hash Bcrypt).
2. `gruppi`: Definisce i gruppi di utenti (`Admin`, `Receptionist`, `Guest`).
3. `services`: Elenca le singole funzionalità/pagine del sistema (es. `admin_dashboard.php`, `profile.php`).
4. **Tabelle di Giunzione:**
   - `user_gruppi`: Associa un utente a uno o più gruppi (relazione Molti-a-Molti).
   - `group_services`: Associa ogni gruppo alle pagine (servizi) a cui ha diritto di accedere.

### Il Flusso di Login e Caricamento Servizi (`login.php` e `auth.inc.php`)
Quando un utente effettua il login:
1. Si verifica la password con `password_verify($password, $row['password'])`.
2. Si chiama la funzione `load_user_services($userId)`, che esegue una query `JOIN` tra `user_gruppi`, `group_services` e `services` per estrarre l'elenco esatto di tutte le pagine accessibili dall'utente.
3. Questi permessi vengono salvati in sessione (`$_SESSION['user']['services']`).
4. In base al gruppo principale (`Admin`, `Receptionist` o `Guest`), l'utente viene reindirizzato alla sua area dedicata.

### Correzione del Bug SQL in `login.php`
Nel file `login.php` originale era presente un bug critico alla riga 21:
```php
// ERRATO: La colonna 'name' non esiste nella tabella users!
$stmt = db()->prepare('SELECT id, email, name, password FROM users WHERE email = ?');
```
La tabella `users` possiede `first_name` e `last_name`. Per preservare la compatibilità con il resto del codice che si aspetta una variabile `name` in sessione, abbiamo corretto la query utilizzando la funzione SQL `CONCAT`:
```php
// CORRETTO: Uniamo first_name e last_name in un alias 'name'
$stmt = db()->prepare('SELECT id, email, first_name, last_name, CONCAT(first_name, " ", last_name) AS name, password FROM users WHERE email = ?');
```

---

## 4. Riepilogo Incremento 1: Registrazione e Login/Logout

In questo primo ciclo di sviluppo incrementale abbiamo completato con successo:
1. **Risoluzione completa dei percorsi asset** e ripristino dell'estetica premium del resort sulla skin `customers`.
2. **Integrazione dell'Header e Navigazione Dinamica** in `frame-public.html`: ora il menu mostra automaticamente i link "Accedi / Registrati" per i visitatori, e "Ciao, [Nome] / Esci" per gli utenti autenticati.
3. **Creazione di `index.php` nella root**: il punto di ingresso principale per gli ospiti e punto di atterraggio dopo il login/logout.
4. **Completamento User Story 1 (Registrazione Ospite)**: Form sicuro che mappa automaticamente i nuovi registrati nel gruppo `Guest` (id 3 nel db).
5. **Completamento User Story 2 (Login & Logout)**: Accesso sicuro con verifica hash Bcrypt, caricamento permessi in sessione e disconnessione pulita.

---
*Documento mantenuto e aggiornato durante lo sviluppo di Paradise Resort.*
