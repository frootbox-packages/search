---
title: Suche auf der Website
---
Besucher durchsuchen Deine Website mit dem Block **Suche**. Er findet
veröffentlichte Seiten, News, Termine und die Inhalte anderer Module in
der Sprache des Besuchers, die besten Treffer zuerst. Entwürfe tauchen
nie auf.

## Die Suche einrichten

Leg eine Seite an, z. B. „Suche“, und setz den Block **Suche** darauf.
Der Kopf des Themes zeigt dann auf jeder Seite einen Link dorthin. In
eine Navigation musst Du die Seite nicht aufnehmen.

## Was gefunden wird

- Titel, Beschreibung und der Text aller Blöcke einer Seite.
- Titel, Anriss und Text von News und Terminen, bei Terminen auch der
  Veranstaltungsort.
- Jedes Wort muss vorkommen, Wortteile zählen: „röst“ findet „Rösterei“.

Nicht gefunden werden gesperrte Seiten und Seiten, die Du in ihren
Einstellungen unter **Suche auf der Website** auslässt, auf Wunsch mit
ihren Unterseiten, und Seiten in einer Sprache, die nur die Inhalte
einer anderen zeigen.

## Nach Änderungen

Die Suche folgt jeder veröffentlichten Änderung sofort. Hat ein
Entwickler die Datenbank direkt geändert, baut
`bin/frootbox search:rebuild --website` den Index neu auf.
