(function() {
    function debounce(fn) {
        if(fn.debounceHandle) {
            window.clearTimeout(fn.debounceHandle);
        }
        fn.debounceHandle = window.setTimeout(fn, 300);
    }

    Vue.createApp({
        template : "#template",
        data : () => ({
            scale : 100,
            monuments : null,
            lastUpdatedAt : null,
            items : null,
            showEntriesForCoords : null,
            newEntry : null,
            busy : false,
            showInfo : false,
            showEntryList : false,
            itemAvailabilityMap : { },
            highlightCoords : null,
        }),
        created() {
            this.loadData();
            this.loadItems();
            window.setInterval(this.loadItems, 2 * 60 * 1000);
            document.addEventListener("keydown", this.onKeyDown);
        },
        mounted() {
            this.handleResize();
            window.addEventListener("resize", () => debounce(this.handleResize));
            this.$refs.mapContainer.addEventListener("wheel", (ev) => {
                if(ev.deltaY < 0) {
                    this.scale += 5;
                    ev.preventDefault();
                    this.$nextTick(() => ev.target.scrollIntoView({ block: "start", inline: "start" }));
                }
                if(ev.deltaY > 0) {
                    this.scale -= 5;
                    ev.preventDefault();
                }
            });
        },
        methods : {
            openEntryList() {
                this.showEntryList = {
                    q : "",
                    sortKey : 'title'
                };
                this.$nextTick(() => {
                    this.$refs.entryListSearchInput.focus();
                });
            },
            itemSelected(i) {
                this.showEntryList = false;
                this.$nextTick(() => {
                    this.highlightCoords = {
                        row : i.row,
                        col : i.col
                    };
                });
            },
            closeOverlays() {
                if(this.showEntriesForCoords) {
                    this.showEntriesForCoords = null;
                }
                if(this.showInfo) {
                    this.showInfo = false;
                }
                if(this.showEntryList) {
                    this.showEntryList = false;
                }
            },
            onKeyDown(ev) {
                switch(ev.keyCode) {
                case 27: //ESC
                    this.closeOverlays();
                    break;
                }
            },
            saveNewEntry() {
                if(!this.newEntryValid) return;
                this.busy = true;
                fetch("backend/items/", {
                    method : "POST",
                    headers : {
                        "X-CSRF-TOKEN" : csrfToken || '',
                    },
                    body : JSON.stringify({
                        data : this.newEntry
                    })
                })
                    .then(d => d.json())
                    .then(d => {
                        this.busy = false;
                        this.newEntry = null;
                        if(d.ok) {
                            this.loadItems();
                        } else {
                            alert(d.msg);
                        }
                    })
                    .catch(_ => this.busy = false);
            },
            createNewEntry() {
                this.newEntry = {
                    title : "",
                    description : "",
                    col : this.showEntriesForCoords.c,
                    row : this.showEntriesForCoords.r,
                };
                this.$nextTick(() => this.$refs.newEntryTitle.focus());
            },
            gridCellClasses(r, c) {
                let retval = [];
                if(this.itemAvailabilityMap[`${c}${r}`]) {
                    retval.push("has-entries");
                }
                if(this.highlightCoords) {
                    if((this.highlightCoords.row == r) && (this.highlightCoords.col == c)) {
                        retval.push("animate__tada");
                        retval.push("highlight");
                    }
                }
                return retval;
            },
            cellItems(r, c) {
                if(!this.items) return [];
                return this.items.filter(i => (i.row == r) && (i.col == c));
            },
            onCellDblclick(r, c) {
            },
            onCellClick(r, c) {
                this.showEntriesForCoords = { r : r, c : c};
            },
            handleResize() {
                let h = window.innerHeight;
                let w = window.innerWidth;

                if(w < h) {
                    h = w;
                }

                this.$refs.mapContainer.style.height = h + "px";
                this.$refs.mapContainer.style.width = h + "px";
            },
            loadItems() {
                fetch("backend/items/").then(d => d.json()).then(d => {
                    this.items = d.items;
                    this.lastUpdatedAt = new Date();
                });
            },
            loadData() {
                fetch("monuments-5000-2.json").then(d => d.json()).then(d => {
                    this.monuments = d.filter(i => {
                        return i.displayName != null;
                    });
                });
            },
            monumentStyle(m) {
                let retval = { };
                retval.left = ((m.position.x + 2500) / 50) + "%";
                retval.top = -((m.position.z - 2500) / 50) + "%";
                return retval;
            },
        },
        computed : {
            allItemsSorted() {
                if(!this.items) return [ ];
                let idx = (i) => {
                    let retval = "";
                    if(i.col.length == 1) {
                        retval = `-${i.col}`;
                    } else {
                        retval = i.col;
                    }
                    if(i.row < 10) {
                        retval = `${retval}-0${i.row}`;
                    } else {
                        retval = `${retval}-${i.row}`;
                    }
                    return retval;
                };
                let retval = this.items.sort((a, b) => {
                    switch(this.showEntryList.sortKey) {
                    case "pq":
                        return idx(a).localeCompare(idx(b));
                    default:
                        return a.title.toLowerCase().localeCompare(b.title.toLowerCase());
                    }
                });

                if(this.showEntryList.q) {
                    const regex = new RegExp(`(${this.showEntryList.q})`, "ig");
                    return retval.filter(r => r.title.match(regex));
                } else {
                    return retval;
                }
            },
            showBackdrop() {
                return this.showEntriesForCoords || this.showInfo || this.showEntryList;
            },
            newEntryValid() {
                let retval = true;
                retval &= (this.newEntry != null) && (this.newEntry.title.length > 0);
                return retval;
            },
            itemsToShow() {
                if(!this.showEntriesForCoords) return null;
                return this.cellItems(this.showEntriesForCoords.r, this.showEntriesForCoords.c);
            },
            gridRows() {
                return Array.from(Array(32).keys()).filter((v,idx) => v);
            },
            gridCols() {
                return Array.from(Array(34).keys()).map(v => {
                    let retval = "";
                    for(let i = 2; (i > 0); i--) {
                        let stellenwert = Math.pow(26, (i - 1))
                        let stelle = Math.floor(v / stellenwert);
                        if(i == 2){
                            if(stelle > 0) {
                                retval += String.fromCharCode(64 + stelle);
                            }
                        } else {
                            retval += String.fromCharCode(65 + stelle);
                        }
                        v -= (stelle * stellenwert);
                    }
                    return retval;
                });
            },
            gridRowStyles() {
                return {
                    "height" : (100.0 / this.gridRows.length) + "%",
                };
            },
            gridCellStyles() {
                return {
                    "width" : (100.0 / this.gridCols.length) + "%",
                };
            },
        },
        watch : {
            scale(newV, oldV) {
                if(newV == oldV) return;
                if(newV < 100) {
                    this.scale = 100;
                }
                if(newV > 300) {
                    this.scale = 300;
                }
            },
            showEntriesForCoords(newV) {
                if(!newV) {
                    this.newEntry = null;
                }
            },
            items(newV) {
                this.itemAvailabilityMap = { };
                if(!newV) return;
                newV.forEach(i => {
                    this.itemAvailabilityMap[`${i.col}${i.row}`] = i;
                });
            },
            "showEntryList.q"(newV) {
            },
        },
    }).mount("#app");
})();