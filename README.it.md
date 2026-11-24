# Course Completion Report for Moodle

🇬🇧 [English](README.md) · 🇮🇹 **Italiano**

[![Made by AtlasTech](https://img.shields.io/badge/Made%20by-AtlasTech-blue)](https://atlastech.it)
[![Latest release](https://img.shields.io/github/v/release/canardu1/course_completion_report-for-moodle)](https://github.com/canardu1/course_completion_report-for-moodle/releases/latest)
[![Moodle 4.3+](https://img.shields.io/badge/Moodle-4.3%2B-F98012?logo=moodle&logoColor=white)](https://moodle.org)

Plugin Moodle (`local_course_completion_report`) per generare report di completamento delle attività di un corso, con coorti, date di inizio e fine e attestati, esportabili in Excel o CSV.

## Funzionalità

- **Elenco corsi** filtrabile per categoria e per nome.
- **Report per corso** con, per ogni discente e ogni attività con completamento attivo:
  - **Stato**: *Completato*, *Non superato* (completata con voto insufficiente) o *Non completato*;
  - **Data inizio**: primo accesso all'attività (dal log di Moodle);
  - **Data fine**: data di completamento.
- **Coorti** del discente come prima colonna.
- **Attestati** emessi con il plugin [Custom certificate](https://moodle.org/plugins/mod_customcert) (se installato): sì/no e data.
- **Filtri utenti**: coorte, cognome, solo iscrizioni attive.
- **Anteprima** a schermo con riepilogo e tasso di completamento.
- **Export Excel e CSV** (CSV con separatore `;` e BOM UTF-8 per Excel).
- Interfaccia in **italiano e inglese**.

## Requisiti

- Moodle 4.3 o superiore (Moodle 5.x non ancora verificato)
- Facoltativo: `mod_customcert` per la colonna attestati

## Installazione

### Dallo zip della release (consigliato)

1. Scaricare `local_course_completion_report-<versione>.zip` dall'[ultima release](https://github.com/canardu1/course_completion_report-for-moodle/releases/latest).
2. Andare su **Amministrazione del sito → Plugin → Installa plugin**, caricare lo zip e seguire i passaggi.

### Manualmente

1. Copiare i file del plugin in `<moodle>/local/course_completion_report/` (la cartella **deve** chiamarsi `course_completion_report`).
2. Visitare **Amministrazione del sito → Notifiche** per completare l'installazione.

## Permessi

| Capability | Ruolo predefinito |
|---|---|
| `local/course_completion_report:view` | Manager |
| `local/course_completion_report:download` | Manager |

Il report è **a livello di sito**: chi ha accesso vede tutti i corsi e tutti i loro iscritti. Per questo i permessi sono verificati nel contesto di sistema e sono assegnati di default solo ai manager (gli amministratori hanno sempre accesso).

## Utilizzo

1. Aprire **Amministrazione del sito → Report → Report Completamento Corsi**, oppure `/local/course_completion_report/index.php`.
2. Filtrare e selezionare un corso.
3. Usare i filtri utenti, poi **Anteprima** oppure **Scarica Excel / CSV**.

## Note

- La **data inizio** viene letta dal log standard di Moodle: se il sito cancella i log più vecchi (*Gestione log → Conserva i log per*), per quelle attività la data non sarà più disponibile.
- Date e orari sono espressi nel fuso **Europe/Rome**.
- Il CSV è protetto dalla *CSV injection*: i valori che iniziano con `=`, `+`, `-` o `@` vengono preceduti da un apostrofo, così non diventano formule quando il file viene aperto.

## Changelog

### 1.11.0 — 2026-11-24
- **Fix:** le attività completate con esito negativo ora risultano *Non superato* invece di *Completato*, e non hanno data di fine.
- **Sicurezza:** export CSV protetto dalla CSV injection.
- **Prestazioni:** il report viene calcolato con poche query invece di 3 per ogni discente e attività; anche l'elenco corsi usa una sola query.
- Nuovo filtro **Solo iscrizioni attive**.
- Testi spostati nei file di lingua, con traduzione inglese e italiana.
- Aggiunto il Privacy API; permessi predefiniti limitati ai manager; requisito minimo corretto a Moodle 4.3.
- Corretto il taglio dei nomi attività con lettere accentate nell'anteprima.

### 1.10.0 e precedenti
- Coorti al posto dei campi profilo, filtri per coorte e cognome, attestati customcert, date di inizio e fine.

## Licenza

GNU GPL v3 or later — http://www.gnu.org/copyleft/gpl.html
