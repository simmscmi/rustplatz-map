<?php

session_start();
if(!isset($_SESSION["csrfToken"])) {
    $_SESSION["csrfToken"] = bin2hex(random_bytes(16));
}
?><!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="css/mini-dark.min.css">
        <link rel="stylesheet" href="css/animate.min.css">
        <script src="js/vue.global.prod.js"></script>
        <title>Rustplatz – Karte</title>
        <link rel="stylesheet" href="css/main.css">
        <script>
            const csrfToken = <?= json_encode($_SESSION["csrfToken"]) ?>;
        </script>
    </head>

    <script id="template" type="text/html">
        <main>
            <div class="backdrop" v-if="showBackdrop" @click.stop="closeOverlays" />

            <div class="scaler">
                <button title="Gesamtliste der Einträge" @click.stop="openEntryList">☰</button>
                <input type="number" 
                    title="Zoomfaktor [%]"
                    min="100"
                    max="300"
                    step="5"
                    v-model="scale">
            </div>

            <div class="entry-list overlay" v-if="showEntryList">
                <div class="heading">
                    Liste aller {{ allItemsSorted.length }} Einträge
                    <span class="close-button" title="Schließen!" @click.stop="showEntryList = null">X</span>
                </div>

                <table class="striped hoverable">
                    <thead>
                        <tr>
                            <th>
                                <span
                                    @click.stop="showEntryList.sortKey = 'pq'"
                                    :class="{ sorted : showEntryList.sortKey == 'pq' }">Planquadrat</span>
                            </th>
                            <th>
                                <span
                                    @click.stop="showEntryList.sortKey = 'title'"
                                    :class="{ sorted : showEntryList.sortKey == 'title' }">Titel</span>
                                <input
                                    class="entry-list-search-input"
                                    ref="entryListSearchInput"
                                    type="text"
                                    v-model="showEntryList.q"
                                    placeholder="suche…">
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr v-for="i in allItemsSorted" :key="i.id" @click.stop="itemSelected(i)">
                            <td>
                                {{ i.col }}{{ i.row }}
                            </td>
                            <td>
                                {{ i.title }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="entries overlay" v-if="showEntriesForCoords">
                <div class="heading">
                    Einträge für Planquadrat {{ showEntriesForCoords.c }}{{ showEntriesForCoords.r }}
                    <span class="close-button" title="Schließen!" @click.stop="showEntriesForCoords = null">X</span>
                </div>

                <div class="existing-entries" v-if="!newEntry">
                    <p v-if="itemsToShow.length == 0">
                        Keine Einträge bisher.
                    </p>

                    <ol v-if="itemsToShow.length" class="item-list">
                        <li
                            v-for="e in itemsToShow"
                            :class="{ 'has-details' : e.description }"
                            :key="e.id"
                            @click.stop="e.showDescription = !!!e.showDescription">
                            <span class="title">
                                {{ e.title }}
                            </span>

                            <div class="description" v-if="e.description && e.showDescription">
                                {{ e.description }}
                            </div>
                        </li>
                    </ol>
                </div>

                <div class="new-entry" v-if="newEntry">
                    <div class="row">
                        <div class="col-sm-12 col-md-4">
                            <label for="title">Titel</label>
                        </div>
                        <div class="col-sm-12 col-md-8">
                            <input
                                type="text"
                                ref="newEntryTitle"
                                id="title"
                                placeholder="z.B. 'Basis von Team X'" v-model="newEntry.title" />
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-12 col-md-4">
                            <label for="description">Beschreibung</label>
                        </div>
                        <div class="col-sm-12 col-md-8">
                            <textarea id="description" v-model="newEntry.description"></textarea>
                        </div>
                    </div>
                </div>

                <div class="footer">
                    <button type="button" class="primary" @click.stop="createNewEntry" v-if="!newEntry">
                        Eintrag hinzufügen…
                    </button>
                    <button type="button" class="secondary" :disabled="busy || !newEntryValid" @click.stop="saveNewEntry" v-if="newEntry">
                        Eintrag speichern!
                    </button>
                </div>
            </div>

            <div class="info overlay" v-if="showInfo">
                <div class="heading">
                    Informationen zur Seite
                    <span class="close-button" title="Schließen!" @click.stop="showInfo = false">X</span>
                </div>

                <div class="page-info">
                    <?php readfile(__DIR__ . "/pageinfo.html"); ?>
                </div>
            </div>

            <div class="map-container" ref="mapContainer">
                <div ref="map" class="map" :style="{ width : (scale ) + '%' }">
                    <div class="monument-layer">
                        <div
                            class="monument"
                            v-for="(m, midx) in monuments"
                            :title="m.displayName"
                            :key="midx"
                            :style="monumentStyle(m)" />
                    </div>

                    <div class="grid">
                        <div
                            class="grid-row "
                            v-for="(r, ridx) in gridRows"
                            :key="ridx"
                            :style="gridRowStyles">
                            <div
                                @animationend="highlightCoords = null"
                                class="grid-cell animate__animated"
                                @dblclick.stop="onCellDblclick(r, c)"
                                @click.stop="onCellClick(r, c)"
                                v-for="(c, cidx) in gridCols"
                                :key="cidx"
                                :class="gridCellClasses(r, c)"
                                :style="gridCellStyles">
                                <span class="coords">
                                    {{ c }}{{ r }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="info-button">
                <span title="Informationen zu dieser Seite…" @click.stop="showInfo = true">ⓘ</span>
            </div>            
        </main>
    </script>

    <body>
        <div id="app" />
    </body>

    <script src="js/main.js?<?php echo time(); ?>"></script>
</html>
