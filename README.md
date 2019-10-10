# mood
mood API implementation

# POSSIBILI IMPLEMENTAZIONI
- unificare alive e extend, far si che se passo un parametro a alive allora questa estenda anche la sessione della durata impostata
- oppure parsare la risposta della UPDATE fatta da extend, non serve verificare se esiste o meno la sessione, tanto se non c'è in db UPDATE 0 righe

# mold

TODO:
* Indivuduato un pattern comune spostare ciò che ora è nei metodi entrypoint (create, delete, etc)
* in modo che nella classe estesa basta definire l'azione "Unit" (permettendo così di chiamarla col nome senza unit)
* Può darsi che questa sia la configurazione ottimale senza dover stravolgere il tutto... da valutare

definire tutti i metodi non "unit" secondo lo schema nel file ActionSkeleton.php
definire tutti i metodi "unit" secondo lo schema nel file ActionSkeleton.php

    baseFieldMap Model of $this->tblIdentifier
    ['myFieldIdentifier'=>['foreignFieldIdentifier',['fieldType',['fieldPropertyKey'=>fieldPropertyValue]],['option']]]