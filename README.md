# Course Completion Report Plugin for Moodle v1.0.0

[![Made by AtlasTech](https://img.shields.io/badge/Made%20by-AtlasTech-blue)](https://atlastech.it)

Plugin Moodle per generare report di completamento attività con campi personalizzati utente.

## Funzionalità

- **Campi personalizzati utente**: Matricola e Department come prime colonne
- **Filtri per corso**: Filtra per categoria e cerca per nome corso
- **Report completamento**: Mostra lo stato di completamento per ogni attività
- **Export Excel e CSV**: Scarica i report in formato Excel o CSV
- **Anteprima report**: Visualizza i dati prima del download

## Requisiti

- Moodle 4.3 o superiore
- PHP 8.0 o superiore
- Campi personalizzati utente configurati:
  - `matricola` - Campo per la matricola utente
  - `department` - Campo per il dipartimento utente

## Installazione

1. Copiare la cartella `course_completion_report` in `/local/` della tua installazione Moodle
2. Visitare "Amministrazione sito > Notifiche" per completare l'installazione
3. Configurare i permessi per i ruoli desiderati

## Configurazione Campi Utente

Prima di usare il plugin, assicurarsi che i campi personalizzati utente siano configurati:

1. Andare su "Amministrazione sito > Utenti > Campi profilo utente"
2. Creare un campo con shortname `matricola`
3. Creare un campo con shortname `department`

## Utilizzo

1. Accedere a `/local/course_completion_report/index.php`
2. Usare i filtri per trovare il corso desiderato
3. Cliccare su "Anteprima" per vedere i dati
4. Scaricare in formato Excel o CSV

## Struttura Report

Il report include le seguenti colonne:
- **Matricola**: Campo personalizzato utente
- **Department**: Campo personalizzato utente
- **Nome**: Nome dell'utente
- **Cognome**: Cognome dell'utente
- **[Attività 1]**: Completato / Non completato
- **[Attività 2]**: Completato / Non completato
- ...

## Licenza

GNU GPL v3 or later
